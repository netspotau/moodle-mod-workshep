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
 * Assess a submission or view the single assessment
 *
 * Assessment id parameter must be passed. The script displays the submission and
 * the assessment form. If the current user is the reviewer and the assessing is
 * allowed, new assessment can be saved.
 * If the assessing is not allowed (for example, the assessment period is over
 * or the current user is eg a teacher), the assessment form is opened
 * in a non-editable mode.
 * The capability 'mod/workshep:peerassess' is intentionally not checked here.
 * The user is considered as a reviewer if the corresponding assessment record
 * has been prepared for him/her (during the allocation). So even a user without the
 * peerassess capability (like a 'teacher', for example) can become a reviewer.
 *
 * @package    mod_workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

$asid       = required_param('asid', PARAM_INT);  // assessment id
$assessment = $DB->get_record('workshep_assessments', array('id' => $asid), '*', MUST_EXIST);
$submission = $DB->get_record('workshep_submissions', array('id' => $assessment->submissionid, 'example' => 0), '*', MUST_EXIST);
$workshep   = $DB->get_record('workshep', array('id' => $submission->workshepid), '*', MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $workshep->course), '*', MUST_EXIST);
$cm         = get_coursemodule_from_instance('workshep', $workshep->id, $course->id, false, MUST_EXIST);

require_login($course, false, $cm);
if (isguestuser()) {
    print_error('guestsarenotallowed');
}
$workshep = new workshep($workshep, $cm, $course);

$PAGE->set_url($workshep->assess_url($assessment->id));
$PAGE->set_title($workshep->name);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('assessingsubmission', 'workshep'));

$canviewallassessments  = has_capability('mod/workshep:viewallassessments', $workshep->context);
$canviewallsubmissions  = has_capability('mod/workshep:viewallsubmissions', $workshep->context);
$cansetassessmentweight = has_capability('mod/workshep:allocate', $workshep->context);
$canoverridegrades      = has_capability('mod/workshep:overridegrades', $workshep->context);
$isreviewer             = ($USER->id == $assessment->reviewerid);
$isauthor               = ($USER->id == $submission->authorid);

$canviewallsubmissions = $canviewallsubmissions && $workshep->check_group_membership($submission->authorid);
if ($workshep->teammode) {
    $isauthor = ($workshep->user_group($submission->authorid)->id == $workshep->user_group($USER->id)->id);
}

if ($canviewallsubmissions) {
    // check this flag against the group membership yet
    if (groups_get_activity_groupmode($workshep->cm) == SEPARATEGROUPS) {
        // user must have accessallgroups or share at least one group with the submission author
        if (!has_capability('moodle/site:accessallgroups', $workshep->context)) {
            $usersgroups = groups_get_activity_allowed_groups($workshep->cm);
            $authorsgroups = groups_get_all_groups($workshep->course->id, $submission->authorid, $workshep->cm->groupingid, 'g.id');
            $sharedgroups = array_intersect_key($usersgroups, $authorsgroups);
            if (empty($sharedgroups)) {
                $canviewallsubmissions = false;
            }
        }
    }
}

if ($isreviewer or $isauthor or ($canviewallassessments and $canviewallsubmissions)) {
    // such a user can continue
} else {
    print_error('nopermissions', 'error', $workshep->view_url(), 'view this assessment');
}

if ($isauthor and !$isreviewer and !$canviewallassessments and $workshep->phase != workshep::PHASE_CLOSED) {
    // authors can see assessments of their work at the end of workshep only
    print_error('nopermissions', 'error', $workshep->view_url(), 'view assessment of own work before workshep is closed');
}

$workshep->check_view_assessment($assessment, $submission);

// only the reviewer is allowed to modify the assessment
if ($isreviewer and $workshep->assessing_allowed($USER->id)) {
    $assessmenteditable = true;
} else {
    $assessmenteditable = false;
}

$output = $PAGE->get_renderer('mod_workshep');      // workshep renderer

// check that all required examples have been assessed by the user
if ($assessmenteditable) {

    list($assessed, $notice) = $workshep->check_examples_assessed_before_assessment($assessment->reviewerid);
    if (!$assessed) {
        echo $output->header();
        echo $output->heading(format_string($workshep->name));
        notice(get_string($notice, 'workshep'), new moodle_url('/mod/workshep/view.php', array('id' => $cm->id)));
        echo $output->footer();
        exit;
    }
}

// load the grading strategy logic
$strategy = $workshep->grading_strategy_instance();

if (is_null($assessment->grade) and !$assessmenteditable) {
    $mform = null;
} else {
    // Are there any other pending assessments to do but this one?
    if ($assessmenteditable) {
        $pending = $workshep->get_pending_assessments_by_reviewer($assessment->reviewerid, $assessment->id);
    } else {
        $pending = array();
    }
    // load the assessment form and process the submitted data eventually
    $mform = $strategy->get_assessment_form($PAGE->url, 'assessment', $assessment, $assessmenteditable,
                                        array('editableweight' => $cansetassessmentweight, 'pending' => !empty($pending)));

    // Set data managed by the workshep core, subplugins set their own data themselves.
    $currentdata = (object)array(
        'weight' => $assessment->weight,
        'feedbackauthor' => $assessment->feedbackauthor,
        'feedbackauthorformat' => $assessment->feedbackauthorformat,
    );
    if ($assessmenteditable and $workshep->overallfeedbackmode) {
        $currentdata = file_prepare_standard_editor($currentdata, 'feedbackauthor', $workshep->overall_feedback_content_options(),
            $workshep->context, 'mod_workshep', 'overallfeedback_content', $assessment->id);
        if ($workshep->overallfeedbackfiles) {
            $currentdata = file_prepare_standard_filemanager($currentdata, 'feedbackauthorattachment',
                $workshep->overall_feedback_attachment_options(), $workshep->context, 'mod_workshep', 'overallfeedback_attachment',
                $assessment->id);
        }
    }
    $mform->set_data($currentdata);

    if ($mform->is_cancelled()) {
        redirect($workshep->view_url());
    } elseif ($assessmenteditable and ($data = $mform->get_data())) {

        // Add or update assessment.
        $rawgrade = $workshep->edit_assessment($assessment, $submission, $data, $strategy);

        // And finally redirect the user's browser.
        if (!is_null($rawgrade) and isset($data->saveandclose)) {
            redirect($workshep->view_url());
        } else if (!is_null($rawgrade) and isset($data->saveandshownext)) {
            $next = reset($pending);
            if (!empty($next)) {
                redirect($workshep->assess_url($next->id));
            } else {
                redirect($PAGE->url); // This should never happen but just in case...
            }
        } else {
            // either it is not possible to calculate the $rawgrade
            // or the reviewer has chosen "Save and continue"
            redirect($PAGE->url);
        }
    }
}

// load the form to override gradinggrade and/or set weight and process the submitted data eventually
if ($canoverridegrades or $cansetassessmentweight) {
    $options = array(
        'editable' => true,
        'editableweight' => $cansetassessmentweight,
        'overridablegradinggrade' => $canoverridegrades,
		'showflaggingresolution' => $cansetassessmentweight and ($assessment->submitterflagged == 1));
    $feedbackform = $workshep->get_feedbackreviewer_form($PAGE->url, $assessment, $options);
    if ($data = $feedbackform->get_data()) {
        $workshep->evaluate_assessment($assessment, $data, $cansetassessmentweight, $canoverridegrades);
        redirect($workshep->view_url());
    }
}

// output starts here
echo $output->header();
echo $output->heading(format_string($workshep->name));
echo $output->heading(get_string('assessedsubmission', 'workshep'), 3);

$submission = $workshep->get_submission_by_id($submission->id);     // reload so can be passed to the renderer
echo $output->render($workshep->prepare_submission($submission, has_capability('mod/workshep:viewauthornames', $workshep->context)));

// show instructions for assessing as they may contain important information
// for evaluating the assessment
if (trim($workshep->instructreviewers)) {
    $instructions = file_rewrite_pluginfile_urls($workshep->instructreviewers, 'pluginfile.php', $PAGE->context->id,
        'mod_workshep', 'instructreviewers', null, workshep::instruction_editors_options($PAGE->context));
    print_collapsible_region_start('', 'workshep-viewlet-instructreviewers', get_string('instructreviewers', 'workshep'));
    echo $output->box(format_text($instructions, $workshep->instructreviewersformat, array('overflowdiv'=>true)), array('generalbox', 'instructions'));
    print_collapsible_region_end();
}

// display example assessments
if ($workshep->usecalibration && (($isreviewer && (($workshep->phase == workshep::PHASE_CLOSED) || ($workshep->phase == workshep::PHASE_EVALUATION))) || $canviewallassessments))
{
    $reviewer = $DB->get_record('user', array('id' => $assessment->reviewerid));
    $calibrator = $workshep->calibration_instance();

    $calibration_renderer = $PAGE->get_renderer('workshepcalibration_'.$workshep->calibrationmethod);
    print_collapsible_region_start('', 'workshep-viewlet-calibrationresults', get_string('calibration', 'workshep'), '', true);
    echo $output->box_start('generalbox');
    $breakdown = $calibrator->prepare_grade_breakdown($reviewer->id);
    echo $calibration_renderer->render($breakdown);
    if (!$breakdown->empty) {
	    echo $output->heading(html_writer::link($calibrator->user_calibration_url($reviewer->id), get_string('explanation','workshep', fullname($reviewer))));
    }
    echo $output->box_end();
    print_collapsible_region_end();

}

// extend the current assessment record with user details
$assessment = $workshep->get_assessment_by_id($assessment->id);

if ($isreviewer) {
    $options    = array(
        'showreviewer'  => true,
        'showauthor'    => has_capability('mod/workshep:viewauthornames', $workshep->context),
        'showform'      => $assessmenteditable or !is_null($assessment->grade),
        'showweight'    => true,
    );
    $assessment = $workshep->prepare_assessment($assessment, $mform, $options);
    $assessment->title = get_string('assessmentbyyourself', 'workshep');
    echo $output->render($assessment);

} else {
    $options    = array(
        'showreviewer'  => has_capability('mod/workshep:viewreviewernames', $workshep->context),
        'showauthor'    => has_capability('mod/workshep:viewauthornames', $workshep->context),
        'showform'      => $assessmenteditable or !is_null($assessment->grade),
        'showweight'    => true,
    );
    $displayassessment = $workshep->prepare_assessment($assessment, $mform, $options);
	
    if ($isauthor and $workshep->submitterflagging) {
        if ($assessment->submitterflagged == 1) {
            //unflag
            $displayassessment->add_action($workshep->flag_url($assessment->id, $PAGE->url, true), get_string('unflagassessment', 'workshep'));
        } else if ($assessment->submitterflagged == 0) {
            //flag for review
            $displayassessment->add_action($workshep->flag_url($assessment->id, $PAGE->url), get_string('flagassessment', 'workshep'));
        }
    }
	
    echo $output->render($displayassessment);
}

if (!$assessmenteditable and $canoverridegrades) {
    $feedbackform->display();
}

echo $output->footer();
