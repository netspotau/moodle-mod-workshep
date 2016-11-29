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
 * Prints a particular instance of workshep
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->libdir.'/completionlib.php');

$id         = optional_param('id', 0, PARAM_INT); // course_module ID, or
$w          = optional_param('w', 0, PARAM_INT);  // workshep instance ID
$editmode   = optional_param('editmode', null, PARAM_BOOL);
$page       = optional_param('page', 0, PARAM_INT);
$perpage    = optional_param('perpage', null, PARAM_INT);
$sortby     = optional_param('sortby', 'lastname', PARAM_ALPHA);
$sorthow    = optional_param('sorthow', 'ASC', PARAM_ALPHA);
$eval       = optional_param('eval', null, PARAM_PLUGIN);

if ($id) {
    $cm             = get_coursemodule_from_id('workshep', $id, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $worksheprecord = $DB->get_record('workshep', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $worksheprecord = $DB->get_record('workshep', array('id' => $w), '*', MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $worksheprecord->course), '*', MUST_EXIST);
    $cm             = get_coursemodule_from_instance('workshep', $worksheprecord->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);
require_capability('mod/workshep:view', $PAGE->context);

$workshep = new workshep($worksheprecord, $cm, $course);

// Mark viewed
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$eventdata = array();
$eventdata['objectid']         = $workshep->id;
$eventdata['context']          = $workshep->context;

$PAGE->set_url($workshep->view_url());
$event = \mod_workshep\event\course_module_viewed::create($eventdata);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('workshep', $worksheprecord);
$event->add_record_snapshot('course_modules', $cm);
$event->trigger();

// If the phase is to be switched, do it asap. This just has to happen after triggering
// the event so that the scheduled allocator had a chance to allocate submissions.
if ($workshep->phase == workshep::PHASE_SUBMISSION and $workshep->phaseswitchassessment
        and $workshep->submissionend > 0 and $workshep->submissionend < time()) {
    $workshep->switch_phase(workshep::PHASE_ASSESSMENT);
    // Disable the automatic switching now so that it is not executed again by accident
    // if the teacher changes the phase back to the submission one.
    $DB->set_field('workshep', 'phaseswitchassessment', 0, array('id' => $workshep->id));
    $workshep->phaseswitchassessment = 0;
}

if (!is_null($editmode) && $PAGE->user_allowed_editing()) {
    $USER->editing = $editmode;
}

$PAGE->set_title($workshep->name);
$PAGE->set_heading($course->fullname);
$PAGE->requires->js(new moodle_url('/mod/workshep/view.js'));

if ($perpage and $perpage > 0 and $perpage <= 1000) {
    require_sesskey();
    set_user_preference('workshep_perpage', $perpage);
    redirect($PAGE->url);
}

if ($eval) {
    require_sesskey();
    require_capability('mod/workshep:overridegrades', $workshep->context);
    $workshep->set_grading_evaluation_method($eval);
    redirect($PAGE->url);
}

$output = $PAGE->get_renderer('mod_workshep');
$userplan = new workshep_user_plan($workshep, $USER->id);

/// Output starts here

echo $output->header();
echo $output->heading_with_help(format_string($workshep->name), 'userplan', 'workshep');
echo $output->render($userplan);

switch ($workshep->phase) {
case workshep::PHASE_SETUP:
    if ($workshep->teammode) {
    	$nogroupusers = $workshep->get_ungrouped_users();
    	if (!empty($nogroupusers)) {
    	    $list = array();
    	    foreach ($nogroupusers as $nogroupuser) {
    	        $list[] = fullname($nogroupuser);
    	    }
    	    $a = implode(', ', $list);
    	    echo $output->box(get_string('teammode_ungroupedwarning', 'workshep', $a), 'generalbox warning nogroupusers');
    	}
    }
    
    if (trim($workshep->intro)) {
        print_collapsible_region_start('', 'workshep-viewlet-intro', get_string('introduction', 'workshep'));
        echo $output->box(format_module_intro('workshep', $workshep, $workshep->cm->id), 'generalbox');
        print_collapsible_region_end();
    }
    if ($workshep->useexamples and has_capability('mod/workshep:manageexamples', $PAGE->context)) {
        print_collapsible_region_start('', 'workshep-viewlet-allexamples', get_string('examplesubmissions', 'workshep'));
        echo $output->box_start('generalbox examples');
        if ($workshep->grading_strategy_instance()->form_ready()) {
            $orderby = $workshep->numexamples > 1 ? 'a.grade, s.title, s.id' : 's.title';
            if (! $examples = $workshep->get_examples_for_manager($orderby)) {
                echo $output->container(get_string('noexamples', 'workshep'), 'noexamples');
            }
            if (($workshep->numexamples > 1) && ($workshep->numexamples < count($examples))) {
                $helper = new workshep_random_examples_helper($examples,$workshep->numexamples);
                echo $output->render($helper);
            }
            foreach ($examples as $example) {
                $summary = $workshep->prepare_example_summary($example);
                $summary->editable = true;
                echo $output->render($summary);
            }
            $aurl = new moodle_url($workshep->exsubmission_url(0), array('edit' => 'on'));
            echo $output->single_button($aurl, get_string('exampleadd', 'workshep'), 'get');
        } else {
            echo $output->container(get_string('noexamplesformready', 'workshep'));
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }
    break;
case workshep::PHASE_SUBMISSION:
    if (trim($workshep->instructauthors)) {
        $instructions = file_rewrite_pluginfile_urls($workshep->instructauthors, 'pluginfile.php', $PAGE->context->id,
            'mod_workshep', 'instructauthors', 0, workshep::instruction_editors_options($PAGE->context));
        print_collapsible_region_start('', 'workshep-viewlet-instructauthors', get_string('instructauthors', 'workshep'));
        echo $output->box(format_text($instructions, $workshep->instructauthorsformat, array('overflowdiv'=>true)), array('generalbox', 'instructions'));
        print_collapsible_region_end();
    }

    // does the user have to assess examples before submitting their own work?
    $examplesmust = ($workshep->useexamples and $workshep->examplesmode == workshep::EXAMPLES_BEFORE_SUBMISSION);

    // is the assessment of example submissions considered finished?
    $examplesdone = has_capability('mod/workshep:manageexamples', $workshep->context);
    if ($workshep->assessing_examples_allowed()
            and has_capability('mod/workshep:submit', $workshep->context)
                    and ! has_capability('mod/workshep:manageexamples', $workshep->context)) {
        $examples = $userplan->get_examples();
        $total = count($examples);
        $left = 0;
        // make sure the current user has all examples allocated
        foreach ($examples as $exampleid => $example) {
            if (is_null($example->assessmentid)) {
                $examples[$exampleid]->assessmentid = $workshep->add_allocation($example, $USER->id, 0);
            }
            if (is_null($example->grade)) {
                $left++;
            }
        }
        if ($left > 0 and $workshep->examplesmode != workshep::EXAMPLES_VOLUNTARY) {
            $examplesdone = false;
        } else {
            $examplesdone = true;
        }
        print_collapsible_region_start('', 'workshep-viewlet-examples', get_string('exampleassessments', 'workshep'), false, $examplesdone);
        echo $output->box_start('generalbox exampleassessments');
        if ($total == 0) {
            echo $output->heading(get_string('noexamples', 'workshep'), 3);
        } else {
            foreach ($examples as $example) {
                $summary = $workshep->prepare_example_summary($example);
                echo $output->render($summary);
            }
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }

    if (has_capability('mod/workshep:submit', $PAGE->context) and (!$examplesmust or $examplesdone)) {
        if($workshep->usecalibration && $workshep->calibrationphase < workshep::PHASE_SUBMISSION) {
            $calibrator = $workshep->calibration_instance();
            $calibration_renderer = $PAGE->get_renderer('workshepcalibration_'.$workshep->calibrationmethod);
            print_collapsible_region_start('', 'workshep-viewlet-calibrationresults', get_string('yourcalibration', 'workshep'), '', true);
            echo $output->box_start('generalbox');
            echo $calibration_renderer->render($calibrator->prepare_grade_breakdown($USER->id));
            echo $output->heading(html_writer::link($calibrator->user_calibration_url($USER->id), get_string('yourexplanation','workshep')));
            echo $output->box_end();
            print_collapsible_region_end();
        }
        
        print_collapsible_region_start('', 'workshep-viewlet-ownsubmission', get_string('yoursubmission', 'workshep'));
        echo $output->box_start('generalbox ownsubmission');

        if ($workshep->teammode && is_null($workshep->user_group($USER->id))) {
        	echo $output->box(get_string('teammode_notingroupwarning', 'workshep'), 'generalbox warning nogroupusers');
        } else {
            if ($submission = $workshep->get_submission_by_author($USER->id)) {
                echo $output->render($workshep->prepare_submission_summary($submission, true));
                if ($workshep->modifying_submission_allowed($USER->id)) {
                    $btnurl = new moodle_url($workshep->submission_url(), array('edit' => 'on'));
                    $btntxt = get_string('editsubmission', 'workshep');
                }
            } else {
                echo $output->container(get_string('noyoursubmission', 'workshep'));
                if ($workshep->creating_submission_allowed($USER->id)) {
                    $btnurl = new moodle_url($workshep->submission_url(), array('edit' => 'on'));
                    $btntxt = get_string('createsubmission', 'workshep');
                }
            }
            if (!empty($btnurl)) {
                echo $output->single_button($btnurl, $btntxt, 'get');
            }
        }

        echo $output->box_end();
        print_collapsible_region_end();
    }

    if (has_capability('mod/workshep:viewallsubmissions', $PAGE->context)) {
        $groupmode = groups_get_activity_groupmode($workshep->cm);
        $groupid = groups_get_activity_group($workshep->cm, true);

        if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $workshep->context)) {
            $allowedgroups = groups_get_activity_allowed_groups($workshep->cm);
            if (empty($allowedgroups)) {
                echo $output->container(get_string('groupnoallowed', 'mod_workshep'), 'groupwidget error');
                break;
            }
            if (! in_array($groupid, array_keys($allowedgroups))) {
                echo $output->container(get_string('groupnotamember', 'core_group'), 'groupwidget error');
                break;
            }
        }

        $countsubmissions = $workshep->count_submissions('all', $groupid);
        $perpage = get_user_preferences('workshep_perpage', 10);
        $pagingbar = new paging_bar($countsubmissions, $page, $perpage, $PAGE->url, 'page');

        print_collapsible_region_start('', 'workshep-viewlet-allsubmissions', get_string('allsubmissions', 'workshep', $countsubmissions));
        echo $output->box_start('generalbox allsubmissions');
        echo $output->container(groups_print_activity_menu($workshep->cm, $PAGE->url, true), 'groupwidget');

        if ($countsubmissions == 0) {
            echo $output->container(get_string('nosubmissions', 'workshep'), 'nosubmissions');

        } else {
            if ($workshep->teammode) {
                $submissions = $workshep->get_submissions_grouped('all', $groupid);
            } else {
                $submissions = $workshep->get_submissions('all', $groupid, $page * $perpage, $perpage);
            }
            $shownames = has_capability('mod/workshep:viewauthornames', $workshep->context);
            echo $output->render($pagingbar);
            foreach ($submissions as $submission) {
                echo $output->render($workshep->prepare_submission_summary($submission, $shownames));
            }
            echo $output->render($pagingbar);
            echo $output->perpage_selector($perpage);
        }

        echo $output->box_end();
        print_collapsible_region_end();
    }

    break;

case workshep::PHASE_CALIBRATION:
    
    if (has_capability('mod/workshep:submit', $workshep->context)
            and ! has_capability('mod/workshep:manageexamples', $workshep->context)) {
        $examples = $userplan->get_examples();
        $total = count($examples);
        $left = 0;
        // make sure the current user has all examples allocated
        foreach ($examples as $exampleid => $example) {
            if (is_null($example->assessmentid)) {
                $examples[$exampleid]->assessmentid = $workshep->add_allocation($example, $USER->id, 0);
            }
            if (is_null($example->grade)) {
                $left++;
            }
        }
        if ($left > 0 and $workshep->examplesmode != workshep::EXAMPLES_VOLUNTARY) {
            $examplesdone = false;
        } else {
            $examplesdone = true;
        }
        print_collapsible_region_start('', 'workshep-viewlet-examples', get_string('exampleassessments', 'workshep'), false, $examplesdone);
        echo $output->box_start('generalbox exampleassessments');
        if ($total == 0) {
            echo $output->heading(get_string('noexamples', 'workshep'), 3);
        } else {
            foreach ($examples as $example) {
                $summary = $workshep->prepare_example_summary($example);
                echo $output->render($summary);
            }
        }
        echo $output->box_end();
        print_collapsible_region_end();        
    }
    
    if (has_capability('mod/workshep:overridegrades', $workshep->context)) {
        $calibration = $workshep->calibration_instance();
        $form = $calibration->get_settings_form(new moodle_url($workshep->calibrate_url(),
                compact('sortby', 'sorthow', 'page')));
        $form->display();
        
        $options = new stdclass;
        $options->sortby = $sortby;
        $options->sorthow = $sorthow;
        
        $report = new workshep_calibration_report($workshep, $options);
        echo $output->render($report);
    }

break;
    

case workshep::PHASE_ASSESSMENT:

    $ownsubmissionexists = null;
    if (has_capability('mod/workshep:submit', $PAGE->context)) {
        
        if($workshep->usecalibration and $workshep->calibrationphase < workshep::PHASE_ASSESSMENT) {
            $calibrator = $workshep->calibration_instance();
            $calibration_renderer = $PAGE->get_renderer('workshepcalibration_'.$workshep->calibrationmethod);
            print_collapsible_region_start('', 'workshep-viewlet-calibrationresults', get_string('yourcalibration', 'workshep'), '', true);
            echo $output->box_start('generalbox');
            echo $calibration_renderer->render($calibrator->prepare_grade_breakdown($USER->id));
            echo $output->heading(html_writer::link($calibrator->user_calibration_url($USER->id), get_string('yourexplanation','workshep')));
            echo $output->box_end();
            print_collapsible_region_end();
        }
        
        if ($ownsubmission = $workshep->get_submission_by_author($USER->id)) {
            print_collapsible_region_start('', 'workshep-viewlet-ownsubmission', get_string('yoursubmission', 'workshep'), false, true);
            echo $output->box_start('generalbox ownsubmission');
            echo $output->render($workshep->prepare_submission_summary($ownsubmission, true));
            $ownsubmissionexists = true;
        } else {
            print_collapsible_region_start('', 'workshep-viewlet-ownsubmission', get_string('yoursubmission', 'workshep'));
            echo $output->box_start('generalbox ownsubmission');
            echo $output->container(get_string('noyoursubmission', 'workshep'));
            $ownsubmissionexists = false;
            if ($workshep->creating_submission_allowed($USER->id)) {
                $btnurl = new moodle_url($workshep->submission_url(), array('edit' => 'on'));
                $btntxt = get_string('createsubmission', 'workshep');
            }
        }
        if (!empty($btnurl)) {
            echo $output->single_button($btnurl, $btntxt, 'get');
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }

    if (has_capability('mod/workshep:viewallassessments', $PAGE->context)) {
        $perpage = get_user_preferences('workshep_perpage', 10);
        $groupid = groups_get_activity_group($workshep->cm, true);
		if ($workshep->teammode) {
			$data = $workshep->prepare_grading_report_data_grouped($USER->id, $groupid, $page, $perpage, $sortby, $sorthow);
		} else {
	        $data = $workshep->prepare_grading_report_data($USER->id, $groupid, $page, $perpage, $sortby, $sorthow);
		}
        if ($data) {
            $showauthornames    = has_capability('mod/workshep:viewauthornames', $workshep->context);
            $showreviewernames  = has_capability('mod/workshep:viewreviewernames', $workshep->context);

            // prepare paging bar
            $baseurl = new moodle_url($PAGE->url, array('sortby' => $sortby, 'sorthow' => $sorthow));
            $pagingbar = new paging_bar($data->totalcount, $page, $perpage, $baseurl, 'page');

            // grading report display options
            $reportopts                         = new stdclass();
            $reportopts->showauthornames        = $showauthornames;
            $reportopts->showreviewernames      = $showreviewernames;
            $reportopts->sortby                 = $sortby;
            $reportopts->sorthow                = $sorthow;
            $reportopts->showsubmissiongrade    = false;
            $reportopts->showgradinggrade       = false;

            print_collapsible_region_start('', 'workshep-viewlet-gradereport', get_string('gradesreport', 'workshep'));
            echo $output->box_start('generalbox gradesreport');
            echo $output->container(groups_print_activity_menu($workshep->cm, $PAGE->url, true), 'groupwidget');
            echo $output->render($pagingbar);
            if($workshep->teammode) {
            	echo $output->render(new workshep_grouped_grading_report($data, $reportopts));
            } else {
                echo $output->render(new workshep_grading_report($data, $reportopts));
            }
            echo $output->render($pagingbar);
            echo $output->perpage_selector($perpage);
            echo $output->box_end();
            print_collapsible_region_end();
        }
    }
    if (trim($workshep->instructreviewers)) {
        $instructions = file_rewrite_pluginfile_urls($workshep->instructreviewers, 'pluginfile.php', $PAGE->context->id,
            'mod_workshep', 'instructreviewers', 0, workshep::instruction_editors_options($PAGE->context));
        print_collapsible_region_start('', 'workshep-viewlet-instructreviewers', get_string('instructreviewers', 'workshep'));
        echo $output->box(format_text($instructions, $workshep->instructreviewersformat, array('overflowdiv'=>true)), array('generalbox', 'instructions'));
        print_collapsible_region_end();
    }

    // does the user have to assess examples before assessing other's work?
    $examplesmust = ($workshep->useexamples and $workshep->examplesmode == workshep::EXAMPLES_BEFORE_ASSESSMENT);

    // is the assessment of example submissions considered finished?
    $examplesdone = has_capability('mod/workshep:manageexamples', $workshep->context);

    // can the examples be assessed?
    $examplesavailable = true;

    if (!$examplesdone and $examplesmust and ($ownsubmissionexists === false)) {
        print_collapsible_region_start('', 'workshep-viewlet-examplesfail', get_string('exampleassessments', 'workshep'));
        echo $output->box(get_string('exampleneedsubmission', 'workshep'));
        print_collapsible_region_end();
        $examplesavailable = false;
    }

    if ($workshep->assessing_examples_allowed()
            and has_capability('mod/workshep:submit', $workshep->context)
                and ! has_capability('mod/workshep:manageexamples', $workshep->context)
                    and $examplesavailable) {
        $examples = $userplan->get_examples();
        $total = count($examples);
        $left = 0;
        // make sure the current user has all examples allocated
        foreach ($examples as $exampleid => $example) {
            if (is_null($example->assessmentid)) {
                $examples[$exampleid]->assessmentid = $workshep->add_allocation($example, $USER->id, 0);
            }
            if (is_null($example->grade)) {
                $left++;
            }
        }
        if ($left > 0 and $workshep->examplesmode != workshep::EXAMPLES_VOLUNTARY) {
            $examplesdone = false;
        } else {
            $examplesdone = true;
        }
        print_collapsible_region_start('', 'workshep-viewlet-examples', get_string('exampleassessments', 'workshep'), false, $examplesdone);
        echo $output->box_start('generalbox exampleassessments');
        if ($total == 0) {
            echo $output->heading(get_string('noexamples', 'workshep'), 3);
        } else {
            foreach ($examples as $example) {
                $summary = $workshep->prepare_example_summary($example);
                echo $output->render($summary);
            }
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }
    if (!$examplesmust or $examplesdone) {
        print_collapsible_region_start('', 'workshep-viewlet-assignedassessments', get_string('assignedassessments', 'workshep'));
        if (! $assessments = $workshep->get_assessments_by_reviewer($USER->id)) {
            echo $output->box_start('generalbox assessment-none');
            echo $output->notification(get_string('assignedassessmentsnone', 'workshep'));
            echo $output->box_end();
        } else {
            $shownames = has_capability('mod/workshep:viewauthornames', $PAGE->context);
            foreach ($assessments as $assessment) {
                $submission                     = new stdClass();
                $submission->id                 = $assessment->submissionid;
                $submission->title              = $assessment->submissiontitle;
                $submission->timecreated        = $assessment->submissioncreated;
                $submission->timemodified       = $assessment->submissionmodified;
                $userpicturefields = explode(',', user_picture::fields());
                foreach ($userpicturefields as $userpicturefield) {
                    $prefixedusernamefield = 'author' . $userpicturefield;
                    $submission->$prefixedusernamefield = $assessment->$prefixedusernamefield;
                }

                // transform the submission object into renderable component
                $submission = $workshep->prepare_submission_summary($submission, $shownames);

                if (is_null($assessment->grade)) {
                    $submission->status = 'notgraded';
                    $class = ' notgraded';
                    $buttontext = get_string('assess', 'workshep');
                } else {
                    $submission->status = 'graded';
                    $class = ' graded';
                    $buttontext = get_string('reassess', 'workshep');
                }

                echo $output->box_start('generalbox assessment-summary' . $class);
                echo $output->render($submission);

                $aurl = $workshep->assess_url($assessment->id);
                echo $output->single_button($aurl, $buttontext, 'get');
                echo $output->box_end();
            }
        }
        print_collapsible_region_end();
    }
    break;
case workshep::PHASE_EVALUATION:
    if (has_capability('mod/workshep:viewallassessments', $PAGE->context)) {
        $perpage = get_user_preferences('workshep_perpage', 10);
        $groupid = groups_get_activity_group($workshep->cm, true);
        if ($workshep->teammode) {
        	$data = $workshep->prepare_grading_report_data_grouped($USER->id, $groupid, $page, $perpage, $sortby, $sorthow);
        } else {
         	$data = $workshep->prepare_grading_report_data($USER->id, $groupid, $page, $perpage, $sortby, $sorthow);
        }
        if ($data) {
            $showauthornames    = has_capability('mod/workshep:viewauthornames', $workshep->context);
            $showreviewernames  = has_capability('mod/workshep:viewreviewernames', $workshep->context);

            if (has_capability('mod/workshep:overridegrades', $PAGE->context)) {
                // Print a drop-down selector to change the current evaluation method.
                $availableevaluators = $workshep->limited_available_evaluators_list();
                if (! array_key_exists($workshep->evaluation, $availableevaluators) ) {
                    $workshep->set_grading_evaluation_method(key($availableevaluators));
                }

                $selector = new single_select($PAGE->url, 'eval', $availableevaluators,
                    $workshep->evaluation, false, 'evaluationmethodchooser');
                $selector->set_label(get_string('evaluationmethod', 'mod_workshep'));
                $selector->set_help_icon('evaluationmethod', 'mod_workshep');
                $selector->method = 'post';
                echo $output->render($selector);
                // load the grading evaluator
                $evaluator = $workshep->grading_evaluation_instance();
                $form = $evaluator->get_settings_form(new moodle_url($workshep->aggregate_url(),
                        compact('sortby', 'sorthow', 'page')));
                $form->display();
				
	            if ($evaluator->has_messages()) {
	                $evaluator->display_messages();
	            }
            }

            // prepare paging bar
            $baseurl = new moodle_url($PAGE->url, array('sortby' => $sortby, 'sorthow' => $sorthow));
            $pagingbar = new paging_bar($data->totalcount, $page, $perpage, $baseurl, 'page');

            // grading report display options
            $reportopts                         = new stdclass();
            $reportopts->showauthornames        = $showauthornames;
            $reportopts->showreviewernames      = $showreviewernames;
            $reportopts->sortby                 = $sortby;
            $reportopts->sorthow                = $sorthow;
            $reportopts->showsubmissiongrade    = true;
            $reportopts->showgradinggrade       = true;
            $reportopts->showdiscrepancy        = true;

            print_collapsible_region_start('', 'workshep-viewlet-gradereport', get_string('gradesreport', 'workshep'));
            echo $output->box_start('generalbox gradesreport');
            echo $output->container(groups_print_activity_menu($workshep->cm, $PAGE->url, true), 'groupwidget');
            echo $output->render($pagingbar);
            if($workshep->teammode) {
            	echo $output->render(new workshep_grouped_grading_report($data, $reportopts));
            } else {
                echo $output->render(new workshep_grading_report($data, $reportopts));
            }
            echo $output->render($pagingbar);
            echo $output->perpage_selector($perpage);
            $url = new moodle_url("download.php", array("id" => $cm->id));
            $btn = new single_button($url, get_string('downloadmarks', 'workshep'), 'get');
            echo $output->render($btn);
            echo $output->box_end();
            print_collapsible_region_end();
        }
    }
    if (has_capability('mod/workshep:overridegrades', $workshep->context)) {
        print_collapsible_region_start('', 'workshep-viewlet-cleargrades', get_string('toolbox', 'workshep'), false, true);
        echo $output->box_start('generalbox toolbox');

        // Clear aggregated grades
        $url = new moodle_url($workshep->toolbox_url('clearaggregatedgrades'));
        $btn = new single_button($url, get_string('clearaggregatedgrades', 'workshep'), 'post');
        $btn->add_confirm_action(get_string('clearaggregatedgradesconfirm', 'workshep'));
        echo $output->container_start('toolboxaction');
        echo $output->render($btn);
        echo $output->help_icon('clearaggregatedgrades', 'workshep');
        echo $output->container_end();
        // Clear assessments
        $url = new moodle_url($workshep->toolbox_url('clearassessments'));
        $btn = new single_button($url, get_string('clearassessments', 'workshep'), 'post');
        $btn->add_confirm_action(get_string('clearassessmentsconfirm', 'workshep'));
        echo $output->container_start('toolboxaction');
        echo $output->render($btn);
        echo $output->help_icon('clearassessments', 'workshep');
        echo html_writer::empty_tag('img', array('src' => $output->pix_url('i/risk_dataloss'),
                                                 'title' => get_string('riskdatalossshort', 'admin'),
                                                 'alt' => get_string('riskdatalossshort', 'admin'),
                                                 'class' => 'workshep-risk-dataloss'));
        echo $output->container_end();

        echo $output->box_end();
        print_collapsible_region_end();
    }
    if (has_capability('mod/workshep:submit', $PAGE->context)) {
        
        if($workshep->usecalibration) {
            $calibrator = $workshep->calibration_instance();
            $calibration_renderer = $PAGE->get_renderer('workshepcalibration_'.$workshep->calibrationmethod);
            print_collapsible_region_start('', 'workshep-viewlet-calibrationresults', get_string('yourcalibration', 'workshep'), '', true);
            echo $output->box_start('generalbox');
            echo $calibration_renderer->render($calibrator->prepare_grade_breakdown($USER->id));
            echo $output->heading(html_writer::link($calibrator->user_calibration_url($USER->id), get_string('yourexplanation','workshep')));
            echo $output->box_end();
            print_collapsible_region_end();
        }
        
        print_collapsible_region_start('', 'workshep-viewlet-ownsubmission', get_string('yoursubmission', 'workshep'));
        echo $output->box_start('generalbox ownsubmission');
        if ($submission = $workshep->get_submission_by_author($USER->id)) {
            echo $output->render($workshep->prepare_submission_summary($submission, true));
        } else {
            echo $output->container(get_string('noyoursubmission', 'workshep'));
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }
    if ($assessments = $workshep->get_assessments_by_reviewer($USER->id)) {
        print_collapsible_region_start('', 'workshep-viewlet-assignedassessments', get_string('assignedassessments', 'workshep'));
        $shownames = has_capability('mod/workshep:viewauthornames', $PAGE->context);
        foreach ($assessments as $assessment) {
            $submission                     = new stdclass();
            $submission->id                 = $assessment->submissionid;
            $submission->title              = $assessment->submissiontitle;
            $submission->timecreated        = $assessment->submissioncreated;
            $submission->timemodified       = $assessment->submissionmodified;
            $userpicturefields = explode(',', user_picture::fields());
            foreach ($userpicturefields as $userpicturefield) {
                $prefixedusernamefield = 'author' . $userpicturefield;
                $submission->$prefixedusernamefield = $assessment->$prefixedusernamefield;
            }

            if (is_null($assessment->grade)) {
                $class = ' notgraded';
                $submission->status = 'notgraded';
                $buttontext = get_string('assess', 'workshep');
            } else {
                $class = ' graded';
                $submission->status = 'graded';
                $buttontext = get_string('reassess', 'workshep');
            }
            echo $output->box_start('generalbox assessment-summary' . $class);
            echo $output->render($workshep->prepare_submission_summary($submission, $shownames));
            echo $output->box_end();
        }
        print_collapsible_region_end();
    }
    break;
case workshep::PHASE_CLOSED:
    if (trim($workshep->conclusion)) {
        $conclusion = file_rewrite_pluginfile_urls($workshep->conclusion, 'pluginfile.php', $workshep->context->id,
            'mod_workshep', 'conclusion', 0, workshep::instruction_editors_options($workshep->context));
        print_collapsible_region_start('', 'workshep-viewlet-conclusion', get_string('conclusion', 'workshep'));
        echo $output->box(format_text($conclusion, $workshep->conclusionformat, array('overflowdiv'=>true)), array('generalbox', 'conclusion'));
        print_collapsible_region_end();
    }
    
    $finalgrades = $workshep->get_gradebook_grades($USER->id);
    
    $groupid = groups_get_activity_group($workshep->cm, true);
    if ($workshep->teammode) {
    	$data = $workshep->prepare_grading_report_data_grouped($USER->id, $groupid, 0, 1, $sortby, $sorthow);
    } else {
     	$data = $workshep->prepare_grading_report_data($USER->id, $groupid, 0, 1, $sortby, $sorthow);
    }
    $showauthornames    = has_capability('mod/workshep:viewauthornames', $workshep->context);
    $showreviewernames  = has_capability('mod/workshep:viewreviewernames', $workshep->context);
    
    $reportopts = new stdClass;
    $reportopts->showauthornames        = $showauthornames;
    $reportopts->showreviewernames      = $showreviewernames;
    $reportopts->sortby                 = $sortby;
    $reportopts->sorthow                = $sorthow;
    $reportopts->showsubmissiongrade    = true;
    $reportopts->showgradinggrade       = true;    
    
    if (!empty($finalgrades)) {
        print_collapsible_region_start('', 'workshep-viewlet-yourgrades', get_string('yourgrades', 'workshep'));
        echo $output->box_start('generalbox grades-yourgrades');
        echo $output->render($finalgrades);
        if ($workshep->teammode) {
            echo $output->render(new workshep_grouped_grading_report($data, $reportopts));
        } else {
            echo $output->render(new workshep_grading_report($data, $reportopts));
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }
    if (has_capability('mod/workshep:viewallassessments', $PAGE->context)) {
		
		print_collapsible_region_start('', 'workshep-viewlet-flagging', get_string('submitterflagging', 'workshep'));
		echo $output->box_start('generalbox center');
        
		echo html_writer::checkbox('flaggingon', '1', $workshep->submitterflagging, get_string('flaggingon', 'workshep'), array('onchange' => "set_flagging_on(this, {$cm->id});"));
		
		$url = new moodle_url('flagged_assessments.php', array('id' => $cm->id));
        echo $output->single_button($url, get_string('showflaggedassessments', 'workshep', 1), 'get');

		echo $output->box_end();
		print_collapsible_region_end();
		
        $evaluator = $workshep->grading_evaluation_instance();
		
        if ($evaluator->has_messages()) {
            $evaluator->display_messages();
        }
		
        $perpage = get_user_preferences('workshep_perpage', 10);
        $groupid = groups_get_activity_group($workshep->cm, true);
        $groupmode = groups_get_activity_groupmode($workshep->cm);
        if ($workshep->teammode) {
        	$data = $workshep->prepare_grading_report_data_grouped($USER->id, $groupid, $page, $perpage, $sortby, $sorthow);
        } else {
         	$data = $workshep->prepare_grading_report_data($USER->id, $groupid, $page, $perpage, $sortby, $sorthow);
        }
        if ($data) {
            $showauthornames    = has_capability('mod/workshep:viewauthornames', $workshep->context);
            $showreviewernames  = has_capability('mod/workshep:viewreviewernames', $workshep->context);

            // prepare paging bar
            $baseurl = new moodle_url($PAGE->url, array('sortby' => $sortby, 'sorthow' => $sorthow));
            $pagingbar = new paging_bar($data->totalcount, $page, $perpage, $baseurl, 'page');

            // grading report display options
            $reportopts                         = new stdclass();
            $reportopts->showauthornames        = $showauthornames;
            $reportopts->showreviewernames      = $showreviewernames;
            $reportopts->sortby                 = $sortby;
            $reportopts->sorthow                = $sorthow;
            $reportopts->showsubmissiongrade    = true;
            $reportopts->showgradinggrade       = true;

            print_collapsible_region_start('', 'workshep-viewlet-gradereport', get_string('gradesreport', 'workshep'));
            echo $output->box_start('generalbox gradesreport');
            echo $output->container(groups_print_activity_menu($workshep->cm, $PAGE->url, true), 'groupwidget');
            echo $output->render($pagingbar);
            if($workshep->teammode) {
            	echo $output->render(new workshep_grouped_grading_report($data, $reportopts));
            } else {
                echo $output->render(new workshep_grading_report($data, $reportopts));
            }
            echo $output->render($pagingbar);
            echo $output->perpage_selector($perpage);
            
            $url = new moodle_url("download.php", array("id" => $cm->id));
            $btn = new single_button($url, get_string('downloadmarks', 'workshep'), 'get');
            echo $output->render($btn);
                        
            echo $output->box_end();
            print_collapsible_region_end();
        }
    }
    if (has_capability('mod/workshep:submit', $PAGE->context)) {
        if($workshep->usecalibration) {
            $calibrator = $workshep->calibration_instance();
            $calibration_renderer = $PAGE->get_renderer('workshepcalibration_'.$workshep->calibrationmethod);
            print_collapsible_region_start('', 'workshep-viewlet-calibrationresults', get_string('yourcalibration', 'workshep'), '', true);
            echo $output->box_start('generalbox');
            echo $calibration_renderer->render($calibrator->prepare_grade_breakdown($USER->id));
            echo $output->heading(html_writer::link($calibrator->user_calibration_url($USER->id), get_string('yourexplanation','workshep')));
            echo $output->box_end();
            print_collapsible_region_end();
        }
        
        print_collapsible_region_start('', 'workshep-viewlet-ownsubmission', get_string('yoursubmission', 'workshep'));
        echo $output->box_start('generalbox ownsubmission');
        if ($submission = $workshep->get_submission_by_author($USER->id)) {
            echo $output->render($workshep->prepare_submission_summary($submission, true));
        } else {
            echo $output->container(get_string('noyoursubmission', 'workshep'));
        }

        echo $output->box_end();

        if (!empty($submission->gradeoverby) and strlen(trim($submission->feedbackauthor)) > 0) {
            echo $output->render(new workshep_feedback_author($submission));
        }



        print_collapsible_region_end();
    }
    if (has_capability('mod/workshep:viewpublishedsubmissions', $workshep->context)) {
        $shownames = has_capability('mod/workshep:viewauthorpublished', $workshep->context);
        if ($submissions = $workshep->get_published_submissions()) {
            print_collapsible_region_start('', 'workshep-viewlet-publicsubmissions', get_string('publishedsubmissions', 'workshep'));
            foreach ($submissions as $submission) {
                echo $output->box_start('generalbox submission-summary');
                echo $output->render($workshep->prepare_submission_summary($submission, $shownames));
                echo $output->box_end();
            }
            print_collapsible_region_end();
        }
    }
    if ($assessments = $workshep->get_assessments_by_reviewer($USER->id)) {
        print_collapsible_region_start('', 'workshep-viewlet-assignedassessments', get_string('assignedassessments', 'workshep'));
        $shownames = has_capability('mod/workshep:viewauthornames', $PAGE->context);
        foreach ($assessments as $assessment) {
            $submission                     = new stdclass();
            $submission->id                 = $assessment->submissionid;
            $submission->title              = $assessment->submissiontitle;
            $submission->timecreated        = $assessment->submissioncreated;
            $submission->timemodified       = $assessment->submissionmodified;
            $userpicturefields = explode(',', user_picture::fields());
            foreach ($userpicturefields as $userpicturefield) {
                $prefixedusernamefield = 'author' . $userpicturefield;
                $submission->$prefixedusernamefield = $assessment->$prefixedusernamefield;
            }

            if (is_null($assessment->grade)) {
                $class = ' notgraded';
                $submission->status = 'notgraded';
                $buttontext = get_string('assess', 'workshep');
            } else {
                $class = ' graded';
                $submission->status = 'graded';
                $buttontext = get_string('reassess', 'workshep');
            }
            echo $output->box_start('generalbox assessment-summary' . $class);
            echo $output->render($workshep->prepare_submission_summary($submission, $shownames));
            echo $output->box_end();

            if (strlen(trim($assessment->feedbackreviewer)) > 0) {
                echo $output->render(new workshep_feedback_reviewer($assessment));
            }
        }
        print_collapsible_region_end();
    }
    break;
default:
}

echo $output->footer();
