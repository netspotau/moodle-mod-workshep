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
 * Assess an example submission
 *
 * @package    mod_workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

$asid       = required_param('asid', PARAM_INT);  // assessment id
$assessment = $DB->get_record('workshep_assessments', array('id' => $asid), '*', MUST_EXIST);
$example    = $DB->get_record('workshep_submissions', array('id' => $assessment->submissionid, 'example' => 1), '*', MUST_EXIST);
$workshep   = $DB->get_record('workshep', array('id' => $example->workshepid), '*', MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $workshep->course), '*', MUST_EXIST);
$cm         = get_coursemodule_from_instance('workshep', $workshep->id, $course->id, false, MUST_EXIST);

require_login($course, false, $cm);
if (isguestuser()) {
    print_error('guestsarenotallowed');
}
$workshep = new workshep($workshep, $cm, $course);

$PAGE->set_url($workshep->exassess_url($assessment->id));
$PAGE->set_title($workshep->name);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('assessingexample', 'workshep'));
$currenttab = 'assessment';

$canmanage  = has_capability('mod/workshep:manageexamples', $workshep->context);
$isreviewer = ($USER->id == $assessment->reviewerid);

if ($isreviewer or $canmanage) {
    // such a user can continue
} else {
    print_error('nopermissions', 'error', $workshep->view_url(), 'assess example submission');
}

// only the reviewer is allowed to modify the assessment
if (($canmanage and $assessment->weight == 1) or ($isreviewer and $workshep->assessing_examples_allowed())) {
    $assessmenteditable = true;
} else {
    $assessmenteditable = false;
}

// CALIBRATION
// this stops users from re-assessing example submissions if they're not allowed to
if (!($canmanage and $assessment->weight == 1) and !empty($assessment->grade) and !$workshep->examplesreassess)
{
    $assessmenteditable = false;
}

// load the grading strategy logic
$strategy = $workshep->grading_strategy_instance();

$options = array();
if ($isreviewer and !$workshep->examplesreassess) {
    $options['saveandcontinue'] = false;
}

// load the assessment form and process the submitted data eventually
$mform = $strategy->get_assessment_form($PAGE->url, 'assessment', $assessment, $assessmenteditable, $options);

// Set data managed by the workshep core, subplugins set their own data themselves.
$currentdata = (object)array(
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

    // Let the grading strategy subplugin save its data.
    $rawgrade = $strategy->save_assessment($assessment, $data);

    // Run the auto calibration scores if enabled.
    if ($workshep->usecalibration && $workshep->autorecalculate) {
        $plugindefaults = get_config('workshepcalibration_examples');
        $calibration = $workshep->calibration_instance();
        $calibrationdata = new stdclass();
        $calibrationdata->comparison = $workshep->calibrationcomparison;
        $calibrationdata->consistency = $workshep->calibrationconsistency;
        $calibration->calculate_calibration_scores($calibrationdata);   // updates 'gradinggrade' in {workshep_assessments}
        $workshep->log('update calibration scores');
    }

    // Store the data managed by the workshep core.
    $coredata = (object)array('id' => $assessment->id);
    if (isset($data->feedbackauthor_editor)) {
        $coredata->feedbackauthor_editor = $data->feedbackauthor_editor;
        $coredata = file_postupdate_standard_editor($coredata, 'feedbackauthor', $workshep->overall_feedback_content_options(),
            $workshep->context, 'mod_workshep', 'overallfeedback_content', $assessment->id);
        unset($coredata->feedbackauthor_editor);
    }
    if (isset($data->feedbackauthorattachment_filemanager)) {
        $coredata->feedbackauthorattachment_filemanager = $data->feedbackauthorattachment_filemanager;
        $coredata = file_postupdate_standard_filemanager($coredata, 'feedbackauthorattachment',
            $workshep->overall_feedback_attachment_options(), $workshep->context, 'mod_workshep', 'overallfeedback_attachment',
            $assessment->id);
        unset($coredata->feedbackauthorattachment_filemanager);
        if (empty($coredata->feedbackauthorattachment)) {
            $coredata->feedbackauthorattachment = 0;
        }
    }
    if ($canmanage) {
        // Remember the last one who edited the reference assessment.
        $coredata->reviewerid = $USER->id;
    }
    // Update the assessment data if there is something other than just the 'id'.  
    if (count((array)$coredata) > 1 ) {
        $DB->update_record('workshep_assessments', $coredata);
    }

    if (!is_null($rawgrade) and isset($data->saveandclose)) {
        if ($canmanage) {
            redirect($workshep->view_url());
        } else {
            redirect($workshep->excompare_url($example->id, $assessment->id));
        }
    } else {
        // either it is not possible to calculate the $rawgrade
        // or the reviewer has chosen "Save and continue"
        redirect($PAGE->url);
    }
}

// output starts here
$output = $PAGE->get_renderer('mod_workshep');      // workshep renderer
echo $output->header();
echo $output->heading(format_string($workshep->name));
echo $output->heading(get_string('assessedexample', 'workshep'), 3);

$example = $workshep->get_example_by_id($example->id);     // reload so can be passed to the renderer
echo $output->render($workshep->prepare_example_submission(($example)));

// show instructions for assessing as thay may contain important information
// for evaluating the assessment
if (trim($workshep->instructreviewers)) {
    $instructions = file_rewrite_pluginfile_urls($workshep->instructreviewers, 'pluginfile.php', $PAGE->context->id,
        'mod_workshep', 'instructreviewers', null, workshep::instruction_editors_options($PAGE->context));
    print_collapsible_region_start('', 'workshep-viewlet-instructreviewers', get_string('instructreviewers', 'workshep'));
    echo $output->box(format_text($instructions, $workshep->instructreviewersformat, array('overflowdiv'=>true)), array('generalbox', 'instructions'));
    print_collapsible_region_end();
}

// extend the current assessment record with user details
$assessment = $workshep->get_assessment_by_id($assessment->id);
if ($canmanage and $assessment->weight == 1) {
    $options = array(
        'showreviewer'  => false,
        'showauthor'    => false,
        'showform'      => true,
    );
    $assessment = $workshep->prepare_example_reference_assessment($assessment, $mform, $options);
    $assessment->title = get_string('assessmentreference', 'workshep');
    echo $output->render($assessment);

} else if ($isreviewer) {
    $options = array(
        'showreviewer'  => true,
        'showauthor'    => false,
        'showform'      => true,
    );
    $assessment = $workshep->prepare_example_assessment($assessment, $mform, $options);
    $assessment->title = get_string('assessmentbyyourself', 'workshep');
    echo $output->render($assessment);
    
    if(!$workshep->examplesreassess and !empty($assessment->grade)) {
        echo $output->single_button($workshep->view_url(), get_string('continue', 'moodle'));
    }

} else if ($canmanage) {
    $options = array(
        'showreviewer'  => true,
        'showauthor'    => false,
        'showform'      => true,
        'showweight'    => false,
    );
    $assessment = $workshep->prepare_example_assessment($assessment, $mform, $options);
    echo $output->render($assessment);
}
echo $output->footer();
