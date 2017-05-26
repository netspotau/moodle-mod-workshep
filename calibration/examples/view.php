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
 * Review all example assessments for a given user
 *
 * @package    mod
 * @subpackage workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once(dirname(dirname(dirname(__FILE__))).'/locallib.php');

$id         = required_param('id', PARAM_INT);  // workshep id
$uid        = required_param('uid', PARAM_INT); // user id
$reviewer   = $DB->get_record('user', array('id' => $uid), '*', MUST_EXIST);
$workshep   = $DB->get_record('workshep', array('id' => $id), '*', MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $workshep->course), '*', MUST_EXIST);
$cm         = get_coursemodule_from_instance('workshep', $workshep->id, $course->id, false, MUST_EXIST);

require_login($course, false, $cm);
if (isguestuser()) {
    print_error('guestsarenotallowed');
}
$workshep = new workshep($workshep, $cm, $course);

$assessments = $workshep->get_examples_for_reviewer($reviewer->id);
$references = $workshep->get_examples_for_manager();

$PAGE->set_url($workshep->calibration_instance()->user_calibration_url($reviewer->id));
$PAGE->set_title($workshep->name);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('assessingexample', 'workshep')); //todo

$canmanage  = has_capability('mod/workshep:manageexamples', $workshep->context);
$isreviewer = ($USER->id == $reviewer->id);

if ($isreviewer or $canmanage) {
    // such a user can continue
} else {
    print_error('nopermissions', 'error', $workshep->view_url(), 'assess example submission');
}

//todo: stop reviewer from viewing before assessment is closed

// load the grading strategy logic
$strategy = $workshep->grading_strategy_instance();

// output starts here
$output = $PAGE->get_renderer('workshepcalibration_examples');      // workshep renderer
echo $output->header();

$calibration = $workshep->calibration_instance();
$breakdown = $calibration->prepare_grade_breakdown($uid);
if(!empty($breakdown)) {
    echo $output->heading('Grade breakdown');
    echo $output->render($breakdown);
}

$output = $PAGE->get_renderer('mod_workshep');      // workshep renderer
echo $output->heading(get_string('exampleassessments', 'workshep', fullname($reviewer)), 2);

foreach($assessments as $k => $v) {

    $reference = $workshep->get_assessment_by_id($references[$k]->assessmentid);
    $mformreference = $strategy->get_assessment_form($PAGE->url, 'assessment', $reference, false);

    $mformassessment = null;
    if (!empty($v->assessmentid)) {
        $assessment = $workshep->get_assessment_by_id($v->assessmentid);
        $mformassessment = $strategy->get_assessment_form($PAGE->url, 'assessment', $assessment, false);

        $options = array(
            'showreviewer'  => true,
            'showauthor'    => false,
            'showform'      => true,
        );

        $exassessment = $workshep->prepare_example_assessment($assessment, $mformassessment, $options);
        $exassessment->reference_form = $mformreference;
        $exassessment->reference_assessment = new workshep_assessment($workshep, $reference);

        echo $output->render($exassessment);
    }

}

echo $output->single_button($workshep->view_url(),get_string('continue', 'moodle'));

echo $output->footer();
