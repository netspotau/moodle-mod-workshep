<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * View a single (usually the own) submission, submit own work.
 *
 * @package    mod_workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->dirroot . '/repository/lib.php');

$cmid   = required_param('cmid', PARAM_INT);            // course module id
$id     = optional_param('id', 0, PARAM_INT);           // submission id
$edit   = optional_param('edit', false, PARAM_BOOL);    // open for editing?
$assess = optional_param('assess', false, PARAM_BOOL);  // instant assessment required

$cm     = get_coursemodule_from_id('workshep', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, false, $cm);
if (isguestuser()) {
    print_error('guestsarenotallowed');
}

$worksheprecord = $DB->get_record('workshep', array('id' => $cm->instance), '*', MUST_EXIST);
$workshep = new workshep($worksheprecord, $cm, $course);

$PAGE->set_url($workshep->submission_url(), array('cmid' => $cmid, 'id' => $id));

if ($edit) {
    $PAGE->url->param('edit', $edit);
}

if ($id) { // submission is specified
    $submission = $workshep->get_submission_by_id($id);

    $params = array(
        'objectid' => $submission->id,
        'context' => $workshep->context,
        'courseid' => $workshep->course->id,
        'relateduserid' => $submission->authorid,
        'other' => array(
            'workshepid' => $workshep->id
        )
    );

    $event = \mod_workshep\event\submission_viewed::create($params);
    $event->trigger();

} else { // no submission specified
    if (!$submission = $workshep->get_submission_by_author($USER->id)) {
        $submission = new stdclass();
        $submission->id = null;
        $submission->authorid = $USER->id;
        $submission->example = 0;
        $submission->grade = null;
        $submission->gradeover = null;
        $submission->published = null;
        $submission->feedbackauthor = null;
        $submission->feedbackauthorformat = editors_get_preferred_format();
    }
}

$ownsubmission  = $submission->authorid == $USER->id;
if ($workshep->teammode && !$ownsubmission) {
    $group = $workshep->user_group($submission->authorid);
    $ownsubmission = groups_is_member($group->id,$USER->id);
}
$canviewall     = has_capability('mod/workshep:viewallsubmissions', $workshep->context);
$cansubmit      = has_capability('mod/workshep:submit', $workshep->context);
$canallocate    = has_capability('mod/workshep:allocate', $workshep->context);
$canpublish     = has_capability('mod/workshep:publishsubmissions', $workshep->context);
$canoverride    = (($workshep->phase == workshep::PHASE_EVALUATION) and has_capability('mod/workshep:overridegrades', $workshep->context));
$userassessment = $workshep->get_assessment_of_submission_by_user($submission->id, $USER->id);
$isreviewer     = !empty($userassessment);
$editable       = ($cansubmit and $ownsubmission);
$ispublished    = ($workshep->phase == workshep::PHASE_CLOSED
                    and $submission->published == 1
                    and has_capability('mod/workshep:viewpublishedsubmissions', $workshep->context));

if (empty($submission->id) and !$workshep->creating_submission_allowed($USER->id)) {
    $editable = false;
}
if ($submission->id and !$workshep->modifying_submission_allowed($USER->id)) {
    $editable = false;
}

if ($canviewall) {
    // check this flag against the group membership yet
    if (groups_get_activity_groupmode($workshep->cm) == SEPARATEGROUPS) {
        // user must have accessallgroups or share at least one group with the submission author
        if (!has_capability('moodle/site:accessallgroups', $workshep->context)) {
            $usersgroups = groups_get_activity_allowed_groups($workshep->cm);
            $authorsgroups = groups_get_all_groups($workshep->course->id, $submission->authorid, $workshep->cm->groupingid, 'g.id');
            $sharedgroups = array_intersect_key($usersgroups, $authorsgroups);
            if (empty($sharedgroups)) {
                $canviewall = false;
            }
        }
    }
}

if ($editable and $workshep->useexamples and $workshep->examplesmode == workshep::EXAMPLES_BEFORE_SUBMISSION
        and !has_capability('mod/workshep:manageexamples', $workshep->context)) {
    // check that all required examples have been assessed by the user
    $examples = $workshep->get_examples_for_reviewer($USER->id);
    foreach ($examples as $exampleid => $example) {
        if (is_null($example->grade)) {
            $editable = false;
            break;
        }
    }
}
$edit = ($editable and $edit);

$seenaspublished = false; // is the submission seen as a published submission?

if ($submission->id and ($ownsubmission or $canviewall or $isreviewer)) {
    // ok you can go
} elseif ($submission->id and $ispublished) {
    // ok you can go
    $seenaspublished = true;
} elseif (is_null($submission->id) and $cansubmit) {
    // ok you can go
} else {
    print_error('nopermissions', 'error', $workshep->view_url(), 'view or create submission');
}

if ($assess and $submission->id and !$isreviewer and $canallocate and $workshep->assessing_allowed($USER->id)) {
    require_sesskey();
    $assessmentid = $workshep->add_allocation($submission, $USER->id);
    redirect($workshep->assess_url($assessmentid));
}

if ($edit) {
    require_once(dirname(__FILE__).'/submission_form.php');

    $maxfiles       = $workshep->nattachments;
    $maxbytes       = $workshep->maxbytes;
    $contentopts    = array(
                        'trusttext' => true,
                        'subdirs'   => false,
                        'maxfiles'  => $maxfiles,
                        'maxbytes'  => $maxbytes,
                        'context'   => $workshep->context,
                        'return_types' => FILE_INTERNAL | FILE_EXTERNAL
                      );

    $attachmentopts = array('subdirs' => true, 'maxfiles' => $maxfiles, 'maxbytes' => $maxbytes, 'return_types' => FILE_INTERNAL);
    $submission     = file_prepare_standard_editor($submission, 'content', $contentopts, $workshep->context,
                                        'mod_workshep', 'submission_content', $submission->id);
    $submission     = file_prepare_standard_filemanager($submission, 'attachment', $attachmentopts, $workshep->context,
                                        'mod_workshep', 'submission_attachment', $submission->id);

    $mform          = new workshep_submission_form($PAGE->url, array('current' => $submission, 'workshep' => $workshep,
                                                    'contentopts' => $contentopts, 'attachmentopts' => $attachmentopts));

    if ($mform->is_cancelled()) {
        redirect($workshep->view_url());

    } elseif ($cansubmit and $formdata = $mform->get_data()) {
        if ($formdata->example == 0) {
            // this was used just for validation, it must be set to zero when dealing with normal submissions
            unset($formdata->example);
        } else {
            throw new coding_exception('Invalid submission form data value: example');
        }
        $timenow = time();
        if (is_null($submission->id)) {
            $formdata->workshepid     = $workshep->id;
            $formdata->example        = 0;
            $formdata->authorid       = $USER->id;
            $formdata->timecreated    = $timenow;
            $formdata->feedbackauthorformat = editors_get_preferred_format();
        }
        $formdata->timemodified       = $timenow;
        $formdata->title              = trim($formdata->title);
        $formdata->content            = '';          // updated later
        $formdata->contentformat      = FORMAT_HTML; // updated later
        $formdata->contenttrust       = 0;           // updated later
        $formdata->late               = 0x0;         // bit mask
        if (!empty($workshep->submissionend) and ($workshep->submissionend < time())) {
            $formdata->late = $formdata->late | 0x1;
        }
        if ($workshep->phase == workshep::PHASE_ASSESSMENT) {
            $formdata->late = $formdata->late | 0x2;
        }

        // Event information.
        $params = array(
            'context' => $workshep->context,
            'courseid' => $workshep->course->id,
            'other' => array(
                'submissiontitle' => $formdata->title
            )
        );
        $logdata = null;
        if (is_null($submission->id)) {
            $submission->id = $formdata->id = $DB->insert_record('workshep_submissions', $formdata);
            $params['objectid'] = $submission->id;
            $event = \mod_workshep\event\submission_created::create($params);
            $event->trigger();
        } else {
            if (empty($formdata->id) or empty($submission->id) or ($formdata->id != $submission->id)) {
                throw new moodle_exception('err_submissionid', 'workshep');
            }
        }
        $params['objectid'] = $submission->id;
        // save and relink embedded images and save attachments
        $formdata = file_postupdate_standard_editor($formdata, 'content', $contentopts, $workshep->context,
                                                      'mod_workshep', 'submission_content', $submission->id);
        $formdata = file_postupdate_standard_filemanager($formdata, 'attachment', $attachmentopts, $workshep->context,
                                                           'mod_workshep', 'submission_attachment', $submission->id);
        if (empty($formdata->attachment)) {
            // explicit cast to zero integer
            $formdata->attachment = 0;
        }
        // store the updated values or re-save the new submission (re-saving needed because URLs are now rewritten)
        $DB->update_record('workshep_submissions', $formdata);
        $event = \mod_workshep\event\submission_updated::create($params);
        $event->add_record_snapshot('workshep', $worksheprecord);
        $event->trigger();

        // send submitted content for plagiarism detection
        $fs = get_file_storage();
        $files = $fs->get_area_files($workshep->context->id, 'mod_workshep', 'submission_attachment', $submission->id);

        $params['other']['content'] = $formdata->content;
        $params['other']['pathnamehashes'] = array_keys($files);

        $event = \mod_workshep\event\assessable_uploaded::create($params);
        $event->set_legacy_logdata($logdata);
        $event->trigger();

        redirect($workshep->submission_url($formdata->id));
    }
}

// load the form to override grade and/or publish the submission and process the submitted data eventually
if (!$edit and ($canoverride or $canpublish)) {
    $options = array(
        'editable' => true,
        'editablepublished' => $canpublish,
        'overridablegrade' => $canoverride);
    $feedbackform = $workshep->get_feedbackauthor_form($PAGE->url, $submission, $options);
    if ($data = $feedbackform->get_data()) {
        $data = file_postupdate_standard_editor($data, 'feedbackauthor', array(), $workshep->context);
        $record = new stdclass();
        $record->id = $submission->id;
        if ($canoverride) {
            $record->gradeover = $workshep->raw_grade_value($data->gradeover, $workshep->grade);
            $record->gradeoverby = $USER->id;
            $record->feedbackauthor = $data->feedbackauthor;
            $record->feedbackauthorformat = $data->feedbackauthorformat;
        }
        if ($canpublish) {
            $record->published = !empty($data->published);
        }
        $DB->update_record('workshep_submissions', $record);
        redirect($workshep->view_url());
    }
}

$PAGE->set_title($workshep->name);
$PAGE->set_heading($course->fullname);
if ($edit) {
    $PAGE->navbar->add(get_string('mysubmission', 'workshep'), $workshep->submission_url(), navigation_node::TYPE_CUSTOM);
    $PAGE->navbar->add(get_string('editingsubmission', 'workshep'));
} elseif ($ownsubmission) {
    $PAGE->navbar->add(get_string('mysubmission', 'workshep'));
} else {
    $PAGE->navbar->add(get_string('submission', 'workshep'));
}

// Output starts here
$output = $PAGE->get_renderer('mod_workshep');
echo $output->header();
echo $output->heading(format_string($workshep->name), 2);

// show instructions for submitting as thay may contain some list of questions and we need to know them
// while reading the submitted answer
if (trim($workshep->instructauthors)) {
    $instructions = file_rewrite_pluginfile_urls($workshep->instructauthors, 'pluginfile.php', $PAGE->context->id,
        'mod_workshep', 'instructauthors', 0, workshep::instruction_editors_options($PAGE->context));
    print_collapsible_region_start('', 'workshep-viewlet-instructauthors', get_string('instructauthors', 'workshep'));
    echo $output->box(format_text($instructions, $workshep->instructauthorsformat, array('overflowdiv'=>true)), array('generalbox', 'instructions'));
    print_collapsible_region_end();
}

// if in edit mode, display the form to edit the submission

if ($edit) {
    if (!empty($CFG->enableplagiarism)) {
        require_once($CFG->libdir.'/plagiarismlib.php');
        echo plagiarism_print_disclosure($cm->id);
    }
    $mform->display();
    echo $output->footer();
    die();
}

// else display the submission

if ($submission->id) {
    if ($seenaspublished) {
        $showauthor = has_capability('mod/workshep:viewauthorpublished', $workshep->context);
    } else {
        $showauthor = has_capability('mod/workshep:viewauthornames', $workshep->context);
    }
    echo $output->render($workshep->prepare_submission($submission, $showauthor));
} else {
    echo $output->box(get_string('noyoursubmission', 'workshep'));
}

if ($editable) {
    if ($submission->id) {
        $btnurl = new moodle_url($PAGE->url, array('edit' => 'on', 'id' => $submission->id));
        $btntxt = get_string('editsubmission', 'workshep');
    } else {
        $btnurl = new moodle_url($PAGE->url, array('edit' => 'on'));
        $btntxt = get_string('createsubmission', 'workshep');
    }
    echo $output->single_button($btnurl, $btntxt, 'get');
}

if ($submission->id and !$edit and !$isreviewer and $canallocate and $workshep->assessing_allowed($USER->id)) {
    $url = new moodle_url($PAGE->url, array('assess' => 1));
    echo $output->single_button($url, get_string('assess', 'workshep'), 'post');
}

if (($workshep->phase == workshep::PHASE_CLOSED) and ($ownsubmission or $canviewall)) {
    if (!empty($submission->gradeoverby) and strlen(trim($submission->feedbackauthor)) > 0) {
        echo $output->render(new workshep_feedback_author($submission));
    }
}

// and possibly display the submission's review(s)

if ($isreviewer) {
    // user's own assessment
    $strategy   = $workshep->grading_strategy_instance();
    $mform      = $strategy->get_assessment_form($PAGE->url, 'assessment', $userassessment, false);
    $options    = array(
        'showreviewer'  => true,
        'showauthor'    => $showauthor,
        'showform'      => !is_null($userassessment->grade),
        'showweight'    => true,
    );
    $assessment = $workshep->prepare_assessment($userassessment, $mform, $options);
    $assessment->title = get_string('assessmentbyyourself', 'workshep');

    if ($workshep->assessing_allowed($USER->id)) {
        if (is_null($userassessment->grade)) {
            $assessment->add_action($workshep->assess_url($assessment->id), get_string('assess', 'workshep'));
        } else {
            $assessment->add_action($workshep->assess_url($assessment->id), get_string('reassess', 'workshep'));
        }
    }
    if ($canoverride) {
        $assessment->add_action($workshep->assess_url($assessment->id), get_string('assessmentsettings', 'workshep'));
    }

    echo $output->render($assessment);

    if ($workshep->phase == workshep::PHASE_CLOSED) {
        if (strlen(trim($userassessment->feedbackreviewer)) > 0) {
            echo $output->render(new workshep_feedback_reviewer($userassessment));
        }
    }
}

if (has_capability('mod/workshep:viewallassessments', $workshep->context) or ($ownsubmission and $workshep->assessments_available())) {
    // other assessments
    $strategy       = $workshep->grading_strategy_instance();
    $assessments    = $workshep->get_assessments_of_submission($submission->id);
    $showreviewer   = has_capability('mod/workshep:viewreviewernames', $workshep->context);
    foreach ($assessments as $assessment) {
        if ($assessment->reviewerid == $USER->id) {
            // own assessment has been displayed already
            continue;
        }
        if (is_null($assessment->grade) and !has_capability('mod/workshep:viewallassessments', $workshep->context)) {
            // students do not see peer-assessment that are not graded yet
            continue;
        }
        $mform      = $strategy->get_assessment_form($PAGE->url, 'assessment', $assessment, false);
        $options    = array(
            'showreviewer'  => $showreviewer,
            'showauthor'    => $showauthor,
            'showform'      => !is_null($assessment->grade),
            'showweight'    => true,
        );
        
        $displayassessment = $workshep->prepare_assessment($assessment, $mform, $options);
        if ($canoverride) {
            $displayassessment->add_action($workshep->assess_url($assessment->id), get_string('assessmentsettings', 'workshep'));
        }
        if ($ownsubmission and $workshep->submitterflagging) {
            if ($assessment->submitterflagged == 1) {
                //unflag
                $displayassessment->add_action($workshep->flag_url($assessment->id, $PAGE->url, true), get_string('unflagassessment', 'workshep'));
            } else if ($assessment->submitterflagged == 0) {
                //flag for review
                $displayassessment->add_action($workshep->flag_url($assessment->id, $PAGE->url), get_string('flagassessment', 'workshep'));
            }
        }
        echo $output->render($displayassessment);

        if ($workshep->phase == workshep::PHASE_CLOSED and has_capability('mod/workshep:viewallassessments', $workshep->context)) {
            if (strlen(trim($assessment->feedbackreviewer)) > 0) {
                echo $output->render(new workshep_feedback_reviewer($assessment));
            }
        }
    }
}

if (!$edit and $canoverride) {
    // display a form to override the submission grade
    $feedbackform->display();
}

echo $output->continue_button(new moodle_url($workshep->view_url()));

echo $output->footer();
