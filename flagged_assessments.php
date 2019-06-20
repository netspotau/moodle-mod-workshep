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
 * A list of all flagged assessments, and actions to take upon them.
 *
 * @package    mod
 * @subpackage workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

$id         = required_param('id', PARAM_INT); // course_module ID

$cm         = get_coursemodule_from_id('workshep', $id, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$workshep   = $DB->get_record('workshep', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/workshep:viewallassessments', $PAGE->context);

$workshep = new workshep($workshep, $cm, $course);

$flagged_assessments = $workshep->get_flagged_assessments();

if ($_POST) {
    
    foreach($flagged_assessments as $a) {
        if (isset($_POST['assessment_'.$a->id])) {
            
            $record = new stdClass();
            $record->id = $a->id;
            $record->submitterflagged = -1;
            
            $weight = $_POST['assessment_'.$a->id];
            if ($weight == 0) {
                $record->weight = 0;
            }
            
            // TODO: MAKE SURE YOU UNCOMMENT THIS LINE BEFORE COMMITTING
            $DB->update_record('workshep_assessments', $record);
            
        }
    }
    
    redirect($workshep->aggregate_url());
    
}

$PAGE->set_url($workshep->flagged_assessments_url());
$PAGE->set_title($workshep->name);
$PAGE->set_heading($course->fullname);

$output = $PAGE->get_renderer('mod_workshep');
echo $output->header();
echo $output->heading(format_string($workshep->name), 2);

$strategy = $workshep->grading_strategy_instance();

// Moodleforms don't nest or repeat nicely, so we're going to be using bare HTML forms

echo html_writer::start_tag('form', array('action' => $workshep->flagged_assessments_url(), 'method' => 'post'));

foreach($flagged_assessments as $assessment) {
    $mform      = $strategy->get_assessment_form($PAGE->url, 'assessment', $assessment, false);
    $options    = array(
        'showreviewer'  => has_capability('mod/workshep:viewreviewernames', $workshep->context),
        'showauthor'    => has_capability('mod/workshep:viewauthornames', $workshep->context),
        'showform'      => !is_null($assessment->grade),
        'showweight'    => true,
        'showflaggingresolution' => true
    );

    $submission = new stdClass();
    $submission->id = $assessment->submissionid;
    $submission->content = $assessment->submissioncontent;
    $submission->contentformat = $assessment->submissionformat;
    $submission->attachment = $assessment->submissionattachment;
    $submission->authorid = $assessment->authorid;
        
    $displayassessment = $workshep->prepare_assessment_with_submission($assessment, $submission, $mform, $options);
    echo $output->render($displayassessment);
    
    
}

echo $output->container(html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('aggregategrades', 'workshep'))), 'center');

echo html_writer::end_tag('form');

echo $output->footer();
