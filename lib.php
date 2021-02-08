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
 * Library of workshep module functions needed by Moodle core and other subsystems
 *
 * All the functions neeeded by Moodle core, gradebook, file subsystem etc
 * are placed here.
 *
 * @package    mod_workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');

define('WORKSHEP_EVENT_TYPE_SUBMISSION_OPEN',   'opensubmission');
define('WORKSHEP_EVENT_TYPE_SUBMISSION_CLOSE',  'closesubmission');
define('WORKSHEP_EVENT_TYPE_ASSESSMENT_OPEN',   'openassessment');
define('WORKSHEP_EVENT_TYPE_ASSESSMENT_CLOSE',  'closeassessment');

////////////////////////////////////////////////////////////////////////////////
// Moodle core API                                                            //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the information if the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function workshep_supports($feature) {
    switch($feature) {
        case FEATURE_GRADE_HAS_GRADE:   return true;
        case FEATURE_GROUPS:            return true;
        case FEATURE_GROUPINGS:         return true;
        case FEATURE_MOD_INTRO:         return true;
        case FEATURE_BACKUP_MOODLE2:    return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_SHOW_DESCRIPTION:  return true;
        case FEATURE_PLAGIARISM:        return true;
        default:                        return null;
    }
}

/**
 * Saves a new instance of the workshep into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will save a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $workshep An object from the form in mod_form.php
 * @return int The id of the newly inserted workshep record
 */
function workshep_add_instance(stdclass $workshep) {
    global $CFG, $DB;
    require_once(dirname(__FILE__) . '/locallib.php');

    $workshep->phase                 = workshep::PHASE_SETUP;
    $workshep->timecreated           = time();
    $workshep->timemodified          = $workshep->timecreated;
    $workshep->useexamples           = (int)!empty($workshep->useexamples);
    $workshep->usepeerassessment     = 1;
    $workshep->useselfassessment     = (int)!empty($workshep->useselfassessment);
    $workshep->usecalibration        = (int)!empty($workshep->usecalibration);
    $workshep->autorecalculate       = (int)!empty($workshep->autorecalculate);
    $workshep->latesubmissions       = (int)!empty($workshep->latesubmissions);
    $workshep->phaseswitchassessment = (int)!empty($workshep->phaseswitchassessment);
    $workshep->teammode              = (int)!empty($workshep->teammode);
    $workshep->nosubmissionrequired  = (int)!empty($workshep->nosubmissionrequired);
    $workshep->examplescompare       = (int)!empty($workshep->examplescompare);
    $workshep->examplesreassess      = (int)!empty($workshep->examplesreassess);
    $workshep->evaluation            = 'best';

    $plugindefaults = get_config('workshepcalibration_examples');
    if (empty($workshep->calibrationcomparison)) {
        $workshep->calibrationcomparison = $plugindefaults->accuracy;
    }

    if (empty($workshep->calibrationconsistency)) {
        $workshep->calibrationconsistency = $plugindefaults->consistence;
    }

    if ($workshep->usecalibration) {

        //here's a fun fact: disabling a checkbox stops it from being submitted with the form
        $workshep->useexamples = true;
        switch($workshep->calibrationphase) {
            case workshep::PHASE_SETUP:
                $workshep->examplesmode = workshep::EXAMPLES_BEFORE_SUBMISSION;
                break;
            case workshep::PHASE_SUBMISSION:
                $workshep->examplesmode = workshep::EXAMPLES_BEFORE_ASSESSMENT;
                break;
        }

        $workshep->evaluation = 'calibrated';
    }

    if (isset($workshep->gradinggradepass)) {
        $workshep->gradinggradepass = (float)unformat_float($workshep->gradinggradepass);
    }

    if (isset($workshep->submissiongradepass)) {
        $workshep->submissiongradepass = (float)unformat_float($workshep->submissiongradepass);
    }

    if (isset($workshep->submissionfiletypes)) {
        $filetypesutil = new \core_form\filetypes_util();
        $submissionfiletypes = $filetypesutil->normalize_file_types($workshep->submissionfiletypes);
        $workshep->submissionfiletypes = implode(' ', $submissionfiletypes);
    }

    if (isset($workshep->overallfeedbackfiletypes)) {
        $filetypesutil = new \core_form\filetypes_util();
        $overallfeedbackfiletypes = $filetypesutil->normalize_file_types($workshep->overallfeedbackfiletypes);
        $workshep->overallfeedbackfiletypes = implode(' ', $overallfeedbackfiletypes);
    }

    // insert the new record so we get the id
    $workshep->id = $DB->insert_record('workshep', $workshep);

    // we need to use context now, so we need to make sure all needed info is already in db
    $cmid = $workshep->coursemodule;
    $DB->set_field('course_modules', 'instance', $workshep->id, array('id' => $cmid));
    $context = context_module::instance($cmid);

    // process the custom wysiwyg editors
    if ($draftitemid = $workshep->instructauthorseditor['itemid']) {
        $workshep->instructauthors = file_save_draft_area_files($draftitemid, $context->id, 'mod_workshep', 'instructauthors',
                0, workshep::instruction_editors_options($context), $workshep->instructauthorseditor['text']);
        $workshep->instructauthorsformat = $workshep->instructauthorseditor['format'];
    }

    if ($draftitemid = $workshep->instructreviewerseditor['itemid']) {
        $workshep->instructreviewers = file_save_draft_area_files($draftitemid, $context->id, 'mod_workshep', 'instructreviewers',
                0, workshep::instruction_editors_options($context), $workshep->instructreviewerseditor['text']);
        $workshep->instructreviewersformat = $workshep->instructreviewerseditor['format'];
    }

    if ($draftitemid = $workshep->conclusioneditor['itemid']) {
        $workshep->conclusion = file_save_draft_area_files($draftitemid, $context->id, 'mod_workshep', 'conclusion',
                0, workshep::instruction_editors_options($context), $workshep->conclusioneditor['text']);
        $workshep->conclusionformat = $workshep->conclusioneditor['format'];
    }

    // re-save the record with the replaced URLs in editor fields
    $DB->update_record('workshep', $workshep);

    // create gradebook items
    workshep_grade_item_update($workshep);
    workshep_grade_item_category_update($workshep);

    // create calendar events
    workshep_calendar_update($workshep, $workshep->coursemodule);
    if (!empty($workshep->completionexpected)) {
        \core_completion\api::update_completion_date_event($cmid, 'workshep', $workshep->id, $workshep->completionexpected);
    }

    return $workshep->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $workshep An object from the form in mod_form.php
 * @return bool success
 */
function workshep_update_instance(stdclass $workshep) {
    global $CFG, $DB;
    require_once(dirname(__FILE__) . '/locallib.php');

    $workshep->timemodified          = time();
    $workshep->id                    = $workshep->instance;
    $workshep->useexamples           = (int)!empty($workshep->useexamples);
    $workshep->usepeerassessment     = 1;
    $workshep->useselfassessment     = (int)!empty($workshep->useselfassessment);
    $workshep->latesubmissions       = (int)!empty($workshep->latesubmissions);
    $workshep->phaseswitchassessment = (int)!empty($workshep->phaseswitchassessment);
    $workshep->teammode              = (int)!empty($workshep->teammode);
    $workshep->nosubmissionrequired  = (int)!empty($workshep->nosubmissionrequired);
    $workshep->examplescompare       = (int)!empty($workshep->examplescompare);
    $workshep->examplesreassess      = (int)!empty($workshep->examplesreassess);
    $workshep->usecalibration        = (int)!empty($workshep->usecalibration);
    $workshep->autorecalculate       = (int)!empty($workshep->autorecalculate);

    $plugindefaults = get_config('workshepcalibration_examples');
    if (empty($workshep->calibrationcomparison)) {
        $workshep->calibrationcomparison = $plugindefaults->accuracy;
    }

    if (empty($workshep->calibrationconsistency)) {
        $workshep->calibrationconsistency = $plugindefaults->consistence;
    }

    if (isset($workshep->gradinggradepass)) {
        $workshep->gradinggradepass = (float)unformat_float($workshep->gradinggradepass);
    }

    if (isset($workshep->submissiongradepass)) {
        $workshep->submissiongradepass = (float)unformat_float($workshep->submissiongradepass);
    }

    if (isset($workshep->submissionfiletypes)) {
        $filetypesutil = new \core_form\filetypes_util();
        $submissionfiletypes = $filetypesutil->normalize_file_types($workshep->submissionfiletypes);
        $workshep->submissionfiletypes = implode(' ', $submissionfiletypes);
    }

    if (isset($workshep->overallfeedbackfiletypes)) {
        $filetypesutil = new \core_form\filetypes_util();
        $overallfeedbackfiletypes = $filetypesutil->normalize_file_types($workshep->overallfeedbackfiletypes);
        $workshep->overallfeedbackfiletypes = implode(' ', $overallfeedbackfiletypes);
    }

    // todo - if the grading strategy is being changed, we may want to replace all aggregated peer grades with nulls


    if ($workshep->usecalibration) {

        //here's a fun fact: disabling a checkbox stops it from being submitted with the form
        $workshep->useexamples = true;
        switch($workshep->calibrationphase) {
            case workshep::PHASE_SETUP:
                $workshep->examplesmode = workshep::EXAMPLES_BEFORE_SUBMISSION;
                break;
            case workshep::PHASE_SUBMISSION:
                $workshep->examplesmode = workshep::EXAMPLES_BEFORE_ASSESSMENT;
                break;
        }

        $oldcalibration = $DB->get_field('workshep', 'usecalibration', array('id' => $workshep->id));
        if ($oldcalibration == false) {
            //turning on calibration - we want to switch to the calibrated evaluation plugin
            $workshep->evaluation = 'calibrated';
        }
    }

    $DB->update_record('workshep', $workshep);
    $context = context_module::instance($workshep->coursemodule);

    // process the custom wysiwyg editors
    if ($draftitemid = $workshep->instructauthorseditor['itemid']) {
        $workshep->instructauthors = file_save_draft_area_files($draftitemid, $context->id, 'mod_workshep', 'instructauthors',
                0, workshep::instruction_editors_options($context), $workshep->instructauthorseditor['text']);
        $workshep->instructauthorsformat = $workshep->instructauthorseditor['format'];
    }

    if ($draftitemid = $workshep->instructreviewerseditor['itemid']) {
        $workshep->instructreviewers = file_save_draft_area_files($draftitemid, $context->id, 'mod_workshep', 'instructreviewers',
                0, workshep::instruction_editors_options($context), $workshep->instructreviewerseditor['text']);
        $workshep->instructreviewersformat = $workshep->instructreviewerseditor['format'];
    }

    if ($draftitemid = $workshep->conclusioneditor['itemid']) {
        $workshep->conclusion = file_save_draft_area_files($draftitemid, $context->id, 'mod_workshep', 'conclusion',
                0, workshep::instruction_editors_options($context), $workshep->conclusioneditor['text']);
        $workshep->conclusionformat = $workshep->conclusioneditor['format'];
    }

    if ($workshep->usecalibration) {
        $workshep->calibrationphase = $workshep->calibrationphase;
    } else {
        $workshep->calibrationphase = 0;
    }

    // re-save the record with the replaced URLs in editor fields
    $DB->update_record('workshep', $workshep);

    // update gradebook items
    workshep_grade_item_update($workshep);
    workshep_grade_item_category_update($workshep);

    // update calendar events
    workshep_calendar_update($workshep, $workshep->coursemodule);
    $completionexpected = (!empty($workshep->completionexpected)) ? $workshep->completionexpected : null;
    \core_completion\api::update_completion_date_event($workshep->coursemodule, 'workshep', $workshep->id, $completionexpected);

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function workshep_delete_instance($id) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if (! $workshep = $DB->get_record('workshep', array('id' => $id))) {
        return false;
    }

    // delete all associated aggregations
    $DB->delete_records('workshep_aggregations', array('workshepid' => $workshep->id));

    // get the list of ids of all submissions
    $submissions = $DB->get_records('workshep_submissions', array('workshepid' => $workshep->id), '', 'id');

    // get the list of all allocated assessments
    $assessments = $DB->get_records_list('workshep_assessments', 'submissionid', array_keys($submissions), '', 'id');

    // delete the associated records from the workshep core tables
    $DB->delete_records_list('workshep_grades', 'assessmentid', array_keys($assessments));
    $DB->delete_records_list('workshep_assessments', 'id', array_keys($assessments));
    $DB->delete_records_list('workshep_submissions', 'id', array_keys($submissions));

    // call the static clean-up methods of all available subplugins
    $strategies = core_component::get_plugin_list('workshepform');
    foreach ($strategies as $strategy => $path) {
        require_once($path.'/lib.php');
        $classname = 'workshep_'.$strategy.'_strategy';
        call_user_func($classname.'::delete_instance', $workshep->id);
    }

    $allocators = core_component::get_plugin_list('workshepallocation');
    foreach ($allocators as $allocator => $path) {
        require_once($path.'/lib.php');
        $classname = 'workshep_'.$allocator.'_allocator';
        call_user_func($classname.'::delete_instance', $workshep->id);
    }

    $evaluators = core_component::get_plugin_list('workshepeval');
    foreach ($evaluators as $evaluator => $path) {
        require_once($path.'/lib.php');
        $classname = 'workshep_'.$evaluator.'_evaluation';
        call_user_func($classname.'::delete_instance', $workshep->id);
    }

    // delete the calendar events
    $events = $DB->get_records('event', array('modulename' => 'workshep', 'instance' => $workshep->id));
    foreach ($events as $event) {
        $event = calendar_event::load($event);
        $event->delete();
    }

    // finally remove the workshep record itself
    $DB->delete_records('workshep', array('id' => $workshep->id));

    // gradebook cleanup
    grade_update('mod/workshep', $workshep->course, 'mod', 'workshep', $workshep->id, 0, null, array('deleted' => true));
    grade_update('mod/workshep', $workshep->course, 'mod', 'workshep', $workshep->id, 1, null, array('deleted' => true));

    return true;
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every workshep event in the site is checked, else
 * only workshep events belonging to the course specified are checked.
 *
 * @param  integer $courseid The Course ID.
 * @param int|stdClass $instance workshep module instance or ID.
 * @param int|stdClass $cm Course module object or ID.
 * @return bool Returns true if the calendar events were successfully updated.
 */
function workshep_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $DB;

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('workshep', array('id' => $instance), '*', MUST_EXIST);
        }
        if (isset($cm)) {
            if (!is_object($cm)) {
                $cm = (object)array('id' => $cm);
            }
        } else {
            $cm = get_coursemodule_from_instance('workshep', $instance->id);
        }
        workshep_calendar_update($instance, $cm->id);
        return true;
    }

    if ($courseid) {
        // Make sure that the course id is numeric.
        if (!is_numeric($courseid)) {
            return false;
        }
        if (!$worksheps = $DB->get_records('workshep', array('course' => $courseid))) {
            return false;
        }
    } else {
        if (!$worksheps = $DB->get_records('workshep')) {
            return false;
        }
    }
    foreach ($worksheps as $workshep) {
        if (!$cm = get_coursemodule_from_instance('workshep', $workshep->id, $courseid, false)) {
            continue;
        }
        workshep_calendar_update($workshep, $cm->id);
    }
    return true;
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function workshep_get_view_actions() {
    return array('view', 'view all', 'view submission', 'view example');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function workshep_get_post_actions() {
    return array('add', 'add assessment', 'add example', 'add submission',
                 'update', 'update assessment', 'update example', 'update submission');
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course The course record.
 * @param stdClass $user The user record.
 * @param cm_info|stdClass $mod The course module info object or record.
 * @param stdClass $workshep The workshep instance record.
 * @return stdclass|null
 */
function workshep_user_outline($course, $user, $mod, $workshep) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    $grades = grade_get_grades($course->id, 'mod', 'workshep', $workshep->id, $user->id);

    $submissiongrade = null;
    $assessmentgrade = null;

    $info = '';
    $time = 0;

    if (!empty($grades->items[0]->grades)) {
        $submissiongrade = reset($grades->items[0]->grades);
        $info .= get_string('submissiongrade', 'workshep') . ': ' . $submissiongrade->str_long_grade . html_writer::empty_tag('br');
        $time = max($time, $submissiongrade->dategraded);
    }
    if (!empty($grades->items[1]->grades)) {
        $assessmentgrade = reset($grades->items[1]->grades);
        $info .= get_string('gradinggrade', 'workshep') . ': ' . $assessmentgrade->str_long_grade;
        $time = max($time, $assessmentgrade->dategraded);
    }

    if (!empty($info) and !empty($time)) {
        $return = new stdclass();
        $return->time = $time;
        $return->info = $info;
        return $return;
    }

    return null;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course The course record.
 * @param stdClass $user The user record.
 * @param cm_info|stdClass $mod The course module info object or record.
 * @param stdClass $workshep The workshep instance record.
 * @return string HTML
 */
function workshep_user_complete($course, $user, $mod, $workshep) {
    global $CFG, $DB, $OUTPUT;
    require_once(dirname(__FILE__).'/locallib.php');
    require_once($CFG->libdir.'/gradelib.php');

    $workshep   = new workshep($workshep, $mod, $course);
    $grades     = grade_get_grades($course->id, 'mod', 'workshep', $workshep->id, $user->id);

    if (!empty($grades->items[0]->grades)) {
        $submissiongrade = reset($grades->items[0]->grades);
        $info = get_string('submissiongrade', 'workshep') . ': ' . $submissiongrade->str_long_grade;
        echo html_writer::tag('li', $info, array('class'=>'submissiongrade'));
    }
    if (!empty($grades->items[1]->grades)) {
        $assessmentgrade = reset($grades->items[1]->grades);
        $info = get_string('gradinggrade', 'workshep') . ': ' . $assessmentgrade->str_long_grade;
        echo html_writer::tag('li', $info, array('class'=>'gradinggrade'));
    }

    if (has_capability('mod/workshep:viewallsubmissions', $workshep->context)) {
        $canviewsubmission = true;
        if (groups_get_activity_groupmode($workshep->cm) == SEPARATEGROUPS) {
            // user must have accessallgroups or share at least one group with the submission author
            if (!has_capability('moodle/site:accessallgroups', $workshep->context)) {
                $usersgroups = groups_get_activity_allowed_groups($workshep->cm);
                $authorsgroups = groups_get_all_groups($workshep->course->id, $user->id, $workshep->cm->groupingid, 'g.id');
                $sharedgroups = array_intersect_key($usersgroups, $authorsgroups);
                if (empty($sharedgroups)) {
                    $canviewsubmission = false;
                }
            }
        }
        if ($canviewsubmission and $submission = $workshep->get_submission_by_author($user->id)) {
            $title      = format_string($submission->title);
            $url        = $workshep->submission_url($submission->id);
            $link       = html_writer::link($url, $title);
            $info       = get_string('submission', 'workshep').': '.$link;
            echo html_writer::tag('li', $info, array('class'=>'submission'));
        }
    }

    if (has_capability('mod/workshep:viewallassessments', $workshep->context)) {
        if ($assessments = $workshep->get_assessments_by_reviewer($user->id)) {
            foreach ($assessments as $assessment) {
                $a = new stdclass();
                $a->submissionurl = $workshep->submission_url($assessment->submissionid)->out();
                $a->assessmenturl = $workshep->assess_url($assessment->id)->out();
                $a->submissiontitle = s($assessment->submissiontitle);
                echo html_writer::tag('li', get_string('assessmentofsubmission', 'workshep', $a));
            }
        }
    }
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in workshep activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @param stdClass $course
 * @param bool $viewfullnames
 * @param int $timestart
 * @return boolean
 */
function workshep_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;

    $authoramefields = get_all_user_name_fields(true, 'author', null, 'author');
    $reviewerfields = get_all_user_name_fields(true, 'reviewer', null, 'reviewer');

    $sql = "SELECT s.id AS submissionid, s.title AS submissiontitle, s.timemodified AS submissionmodified,
                   author.id AS authorid, $authoramefields, a.id AS assessmentid, a.timemodified AS assessmentmodified,
                   reviewer.id AS reviewerid, $reviewerfields, cm.id AS cmid
              FROM {workshep} w
        INNER JOIN {course_modules} cm ON cm.instance = w.id
        INNER JOIN {modules} md ON md.id = cm.module
        INNER JOIN {workshep_submissions} s ON s.workshepid = w.id
        INNER JOIN {user} author ON s.authorid = author.id
         LEFT JOIN {workshep_assessments} a ON a.submissionid = s.id
         LEFT JOIN {user} reviewer ON a.reviewerid = reviewer.id
             WHERE cm.course = ?
                   AND md.name = 'workshep'
                   AND s.example = 0
                   AND (s.timemodified > ? OR a.timemodified > ?)
          ORDER BY s.timemodified";

    $rs = $DB->get_recordset_sql($sql, array($course->id, $timestart, $timestart));

    $modinfo = get_fast_modinfo($course); // reference needed because we might load the groups

    $submissions = array(); // recent submissions indexed by submission id
    $assessments = array(); // recent assessments indexed by assessment id
    $users       = array();

    foreach ($rs as $activity) {
        if (!array_key_exists($activity->cmid, $modinfo->cms)) {
            // this should not happen but just in case
            continue;
        }

        $cm = $modinfo->cms[$activity->cmid];
        if (!$cm->uservisible) {
            continue;
        }

        // remember all user names we can use later
        if (empty($users[$activity->authorid])) {
            $u = new stdclass();
            $users[$activity->authorid] = username_load_fields_from_object($u, $activity, 'author');
        }
        if ($activity->reviewerid and empty($users[$activity->reviewerid])) {
            $u = new stdclass();
            $users[$activity->reviewerid] = username_load_fields_from_object($u, $activity, 'reviewer');
        }

        $context = context_module::instance($cm->id);
        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($activity->submissionmodified > $timestart and empty($submissions[$activity->submissionid])) {
            $s = new stdclass();
            $s->title = $activity->submissiontitle;
            $s->authorid = $activity->authorid;
            $s->timemodified = $activity->submissionmodified;
            $s->cmid = $activity->cmid;
            if ($activity->authorid == $USER->id || has_capability('mod/workshep:viewauthornames', $context)) {
                $s->authornamevisible = true;
            } else {
                $s->authornamevisible = false;
            }

            // the following do-while wrapper allows to break from deeply nested if-statements
            do {
                if ($s->authorid === $USER->id) {
                    // own submissions always visible
                    $submissions[$activity->submissionid] = $s;
                    break;
                }

                if (has_capability('mod/workshep:viewallsubmissions', $context)) {
                    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                        if (isguestuser()) {
                            // shortcut - guest user does not belong into any group
                            break;
                        }

                        // this might be slow - show only submissions by users who share group with me in this cm
                        if (!$modinfo->get_groups($cm->groupingid)) {
                            break;
                        }
                        $authorsgroups = groups_get_all_groups($course->id, $s->authorid, $cm->groupingid);
                        if (is_array($authorsgroups)) {
                            $authorsgroups = array_keys($authorsgroups);
                            $intersect = array_intersect($authorsgroups, $modinfo->get_groups($cm->groupingid));
                            if (empty($intersect)) {
                                break;
                            } else {
                                // can see all submissions and shares a group with the author
                                $submissions[$activity->submissionid] = $s;
                                break;
                            }
                        }

                    } else {
                        // can see all submissions from all groups
                        $submissions[$activity->submissionid] = $s;
                    }
                }
            } while (0);
        }

        if ($activity->assessmentmodified > $timestart and empty($assessments[$activity->assessmentid])) {
            $a = new stdclass();
            $a->submissionid = $activity->submissionid;
            $a->submissiontitle = $activity->submissiontitle;
            $a->reviewerid = $activity->reviewerid;
            $a->timemodified = $activity->assessmentmodified;
            $a->cmid = $activity->cmid;
            if ($activity->reviewerid == $USER->id || has_capability('mod/workshep:viewreviewernames', $context)) {
                $a->reviewernamevisible = true;
            } else {
                $a->reviewernamevisible = false;
            }

            // the following do-while wrapper allows to break from deeply nested if-statements
            do {
                if ($a->reviewerid === $USER->id) {
                    // own assessments always visible
                    $assessments[$activity->assessmentid] = $a;
                    break;
                }

                if (has_capability('mod/workshep:viewallassessments', $context)) {
                    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                        if (isguestuser()) {
                            // shortcut - guest user does not belong into any group
                            break;
                        }

                        // this might be slow - show only submissions by users who share group with me in this cm
                        if (!$modinfo->get_groups($cm->groupingid)) {
                            break;
                        }
                        $reviewersgroups = groups_get_all_groups($course->id, $a->reviewerid, $cm->groupingid);
                        if (is_array($reviewersgroups)) {
                            $reviewersgroups = array_keys($reviewersgroups);
                            $intersect = array_intersect($reviewersgroups, $modinfo->get_groups($cm->groupingid));
                            if (empty($intersect)) {
                                break;
                            } else {
                                // can see all assessments and shares a group with the reviewer
                                $assessments[$activity->assessmentid] = $a;
                                break;
                            }
                        }

                    } else {
                        // can see all assessments from all groups
                        $assessments[$activity->assessmentid] = $a;
                    }
                }
            } while (0);
        }
    }
    $rs->close();

    $shown = false;

    if (!empty($submissions)) {
        $shown = true;
        echo $OUTPUT->heading(get_string('recentsubmissions', 'workshep'), 3);
        foreach ($submissions as $id => $submission) {
            $link = new moodle_url('/mod/workshep/submission.php', array('id'=>$id, 'cmid'=>$submission->cmid));
            if ($submission->authornamevisible) {
                $author = $users[$submission->authorid];
            } else {
                $author = null;
            }
            print_recent_activity_note($submission->timemodified, $author, $submission->title, $link->out(), false, $viewfullnames);
        }
    }

    if (!empty($assessments)) {
        $shown = true;
        echo $OUTPUT->heading(get_string('recentassessments', 'workshep'), 3);
        core_collator::asort_objects_by_property($assessments, 'timemodified');
        foreach ($assessments as $id => $assessment) {
            $link = new moodle_url('/mod/workshep/assessment.php', array('asid' => $id));
            if ($assessment->reviewernamevisible) {
                $reviewer = $users[$assessment->reviewerid];
            } else {
                $reviewer = null;
            }
            print_recent_activity_note($assessment->timemodified, $reviewer, $assessment->submissiontitle, $link->out(), false, $viewfullnames);
        }
    }

    if ($shown) {
        return true;
    }

    return false;
}

/**
 * Returns all activity in course worksheps since a given time
 *
 * @param array $activities sequentially indexed array of objects
 * @param int $index
 * @param int $timestart
 * @param int $courseid
 * @param int $cmid
 * @param int $userid defaults to 0
 * @param int $groupid defaults to 0
 * @return void adds items into $activities and increases $index
 */
function workshep_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id'=>$courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];

    $params = array();
    if ($userid) {
        $userselect = "AND (author.id = :authorid OR reviewer.id = :reviewerid)";
        $params['authorid'] = $userid;
        $params['reviewerid'] = $userid;
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = "AND (authorgroupmembership.groupid = :authorgroupid OR reviewergroupmembership.groupid = :reviewergroupid)";
        $groupjoin   = "LEFT JOIN {groups_members} authorgroupmembership ON authorgroupmembership.userid = author.id
                        LEFT JOIN {groups_members} reviewergroupmembership ON reviewergroupmembership.userid = reviewer.id";
        $params['authorgroupid'] = $groupid;
        $params['reviewergroupid'] = $groupid;
    } else {
        $groupselect = "";
        $groupjoin   = "";
    }

    $params['cminstance'] = $cm->instance;
    $params['submissionmodified'] = $timestart;
    $params['assessmentmodified'] = $timestart;

    $authornamefields = get_all_user_name_fields(true, 'author', null, 'author');
    $reviewerfields = get_all_user_name_fields(true, 'reviewer', null, 'reviewer');

    $sql = "SELECT s.id AS submissionid, s.title AS submissiontitle, s.timemodified AS submissionmodified,
                   author.id AS authorid, $authornamefields, author.picture AS authorpicture, author.imagealt AS authorimagealt,
                   author.email AS authoremail, a.id AS assessmentid, a.timemodified AS assessmentmodified,
                   reviewer.id AS reviewerid, $reviewerfields, reviewer.picture AS reviewerpicture,
                   reviewer.imagealt AS reviewerimagealt, reviewer.email AS revieweremail
              FROM {workshep_submissions} s
        INNER JOIN {workshep} w ON s.workshepid = w.id
        INNER JOIN {user} author ON s.authorid = author.id
         LEFT JOIN {workshep_assessments} a ON a.submissionid = s.id
         LEFT JOIN {user} reviewer ON a.reviewerid = reviewer.id
        $groupjoin
             WHERE w.id = :cminstance
                   AND s.example = 0
                   $userselect $groupselect
                   AND (s.timemodified > :submissionmodified OR a.timemodified > :assessmentmodified)
          ORDER BY s.timemodified ASC, a.timemodified ASC";

    $rs = $DB->get_recordset_sql($sql, $params);

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $context         = context_module::instance($cm->id);
    $grader          = has_capability('moodle/grade:viewall', $context);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $context);
    $viewauthors     = has_capability('mod/workshep:viewauthornames', $context);
    $viewreviewers   = has_capability('mod/workshep:viewreviewernames', $context);

    $submissions = array(); // recent submissions indexed by submission id
    $assessments = array(); // recent assessments indexed by assessment id
    $users       = array();

    foreach ($rs as $activity) {

        // remember all user names we can use later
        if (empty($users[$activity->authorid])) {
            $u = new stdclass();
            $additionalfields = explode(',', user_picture::fields());
            $u = username_load_fields_from_object($u, $activity, 'author', $additionalfields);
            $users[$activity->authorid] = $u;
        }
        if ($activity->reviewerid and empty($users[$activity->reviewerid])) {
            $u = new stdclass();
            $additionalfields = explode(',', user_picture::fields());
            $u = username_load_fields_from_object($u, $activity, 'reviewer', $additionalfields);
            $users[$activity->reviewerid] = $u;
        }

        if ($activity->submissionmodified > $timestart and empty($submissions[$activity->submissionid])) {
            $s = new stdclass();
            $s->id = $activity->submissionid;
            $s->title = $activity->submissiontitle;
            $s->authorid = $activity->authorid;
            $s->timemodified = $activity->submissionmodified;
            if ($activity->authorid == $USER->id || has_capability('mod/workshep:viewauthornames', $context)) {
                $s->authornamevisible = true;
            } else {
                $s->authornamevisible = false;
            }

            // the following do-while wrapper allows to break from deeply nested if-statements
            do {
                if ($s->authorid === $USER->id) {
                    // own submissions always visible
                    $submissions[$activity->submissionid] = $s;
                    break;
                }

                if (has_capability('mod/workshep:viewallsubmissions', $context)) {
                    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                        if (isguestuser()) {
                            // shortcut - guest user does not belong into any group
                            break;
                        }

                        // this might be slow - show only submissions by users who share group with me in this cm
                        if (!$modinfo->get_groups($cm->groupingid)) {
                            break;
                        }
                        $authorsgroups = groups_get_all_groups($course->id, $s->authorid, $cm->groupingid);
                        if (is_array($authorsgroups)) {
                            $authorsgroups = array_keys($authorsgroups);
                            $intersect = array_intersect($authorsgroups, $modinfo->get_groups($cm->groupingid));
                            if (empty($intersect)) {
                                break;
                            } else {
                                // can see all submissions and shares a group with the author
                                $submissions[$activity->submissionid] = $s;
                                break;
                            }
                        }

                    } else {
                        // can see all submissions from all groups
                        $submissions[$activity->submissionid] = $s;
                    }
                }
            } while (0);
        }

        if ($activity->assessmentmodified > $timestart and empty($assessments[$activity->assessmentid])) {
            $a = new stdclass();
            $a->id = $activity->assessmentid;
            $a->submissionid = $activity->submissionid;
            $a->submissiontitle = $activity->submissiontitle;
            $a->reviewerid = $activity->reviewerid;
            $a->timemodified = $activity->assessmentmodified;
            if ($activity->reviewerid == $USER->id || has_capability('mod/workshep:viewreviewernames', $context)) {
                $a->reviewernamevisible = true;
            } else {
                $a->reviewernamevisible = false;
            }

            // the following do-while wrapper allows to break from deeply nested if-statements
            do {
                if ($a->reviewerid === $USER->id) {
                    // own assessments always visible
                    $assessments[$activity->assessmentid] = $a;
                    break;
                }

                if (has_capability('mod/workshep:viewallassessments', $context)) {
                    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                        if (isguestuser()) {
                            // shortcut - guest user does not belong into any group
                            break;
                        }

                        // this might be slow - show only submissions by users who share group with me in this cm
                        if (!$modinfo->get_groups($cm->groupingid)) {
                            break;
                        }
                        $reviewersgroups = groups_get_all_groups($course->id, $a->reviewerid, $cm->groupingid);
                        if (is_array($reviewersgroups)) {
                            $reviewersgroups = array_keys($reviewersgroups);
                            $intersect = array_intersect($reviewersgroups, $modinfo->get_groups($cm->groupingid));
                            if (empty($intersect)) {
                                break;
                            } else {
                                // can see all assessments and shares a group with the reviewer
                                $assessments[$activity->assessmentid] = $a;
                                break;
                            }
                        }

                    } else {
                        // can see all assessments from all groups
                        $assessments[$activity->assessmentid] = $a;
                    }
                }
            } while (0);
        }
    }
    $rs->close();

    $workshepname = format_string($cm->name, true);

    if ($grader) {
        require_once($CFG->libdir.'/gradelib.php');
        $grades = grade_get_grades($courseid, 'mod', 'workshep', $cm->instance, array_keys($users));
    }

    foreach ($submissions as $submission) {
        $tmpactivity                = new stdclass();
        $tmpactivity->type          = 'workshep';
        $tmpactivity->cmid          = $cm->id;
        $tmpactivity->name          = $workshepname;
        $tmpactivity->sectionnum    = $cm->sectionnum;
        $tmpactivity->timestamp     = $submission->timemodified;
        $tmpactivity->subtype       = 'submission';
        $tmpactivity->content       = $submission;
        if ($grader) {
            $tmpactivity->grade     = $grades->items[0]->grades[$submission->authorid]->str_long_grade;
        }
        if ($submission->authornamevisible and !empty($users[$submission->authorid])) {
            $tmpactivity->user      = $users[$submission->authorid];
        }
        $activities[$index++]       = $tmpactivity;
    }

    foreach ($assessments as $assessment) {
        $tmpactivity                = new stdclass();
        $tmpactivity->type          = 'workshep';
        $tmpactivity->cmid          = $cm->id;
        $tmpactivity->name          = $workshepname;
        $tmpactivity->sectionnum    = $cm->sectionnum;
        $tmpactivity->timestamp     = $assessment->timemodified;
        $tmpactivity->subtype       = 'assessment';
        $tmpactivity->content       = $assessment;
        if ($grader) {
            $tmpactivity->grade     = $grades->items[1]->grades[$assessment->reviewerid]->str_long_grade;
        }
        if ($assessment->reviewernamevisible and !empty($users[$assessment->reviewerid])) {
            $tmpactivity->user      = $users[$assessment->reviewerid];
        }
        $activities[$index++]       = $tmpactivity;
    }
}

/**
 * Print single activity item prepared by {@see workshep_get_recent_mod_activity()}
 */
function workshep_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    global $CFG, $OUTPUT;

    if (!empty($activity->user)) {
        echo html_writer::tag('div', $OUTPUT->user_picture($activity->user, array('courseid'=>$courseid)),
                array('style' => 'float: left; padding: 7px;'));
    }

    if ($activity->subtype == 'submission') {
        echo html_writer::start_tag('div', array('class'=>'submission', 'style'=>'padding: 7px; float:left;'));

        if ($detail) {
            echo html_writer::start_tag('h4', array('class'=>'workshep'));
            $url = new moodle_url('/mod/workshep/view.php', array('id'=>$activity->cmid));
            $name = s($activity->name);
            echo $OUTPUT->image_icon('icon', $name, $activity->type);
            echo ' ' . $modnames[$activity->type];
            echo html_writer::link($url, $name, array('class'=>'name', 'style'=>'margin-left: 5px'));
            echo html_writer::end_tag('h4');
        }

        echo html_writer::start_tag('div', array('class'=>'title'));
        $url = new moodle_url('/mod/workshep/submission.php', array('cmid'=>$activity->cmid, 'id'=>$activity->content->id));
        $name = s($activity->content->title);
        echo html_writer::tag('strong', html_writer::link($url, $name));
        echo html_writer::end_tag('div');

        if (!empty($activity->user)) {
            echo html_writer::start_tag('div', array('class'=>'user'));
            $url = new moodle_url('/user/view.php', array('id'=>$activity->user->id, 'course'=>$courseid));
            $name = fullname($activity->user);
            $link = html_writer::link($url, $name);
            echo get_string('submissionby', 'workshep', $link);
            echo ' - '.userdate($activity->timestamp);
            echo html_writer::end_tag('div');
        } else {
            echo html_writer::start_tag('div', array('class'=>'anonymous'));
            echo get_string('submission', 'workshep');
            echo ' - '.userdate($activity->timestamp);
            echo html_writer::end_tag('div');
        }

        echo html_writer::end_tag('div');
    }

    if ($activity->subtype == 'assessment') {
        echo html_writer::start_tag('div', array('class'=>'assessment', 'style'=>'padding: 7px; float:left;'));

        if ($detail) {
            echo html_writer::start_tag('h4', array('class'=>'workshep'));
            $url = new moodle_url('/mod/workshep/view.php', array('id'=>$activity->cmid));
            $name = s($activity->name);
            echo $OUTPUT->image_icon('icon', $name, $activity->type);
            echo ' ' . $modnames[$activity->type];
            echo html_writer::link($url, $name, array('class'=>'name', 'style'=>'margin-left: 5px'));
            echo html_writer::end_tag('h4');
        }

        echo html_writer::start_tag('div', array('class'=>'title'));
        $url = new moodle_url('/mod/workshep/assessment.php', array('asid'=>$activity->content->id));
        $name = s($activity->content->submissiontitle);
        echo html_writer::tag('em', html_writer::link($url, $name));
        echo html_writer::end_tag('div');

        if (!empty($activity->user)) {
            echo html_writer::start_tag('div', array('class'=>'user'));
            $url = new moodle_url('/user/view.php', array('id'=>$activity->user->id, 'course'=>$courseid));
            $name = fullname($activity->user);
            $link = html_writer::link($url, $name);
            echo get_string('assessmentbyfullname', 'workshep', $link);
            echo ' - '.userdate($activity->timestamp);
            echo html_writer::end_tag('div');
        } else {
            echo html_writer::start_tag('div', array('class'=>'anonymous'));
            echo get_string('assessment', 'workshep');
            echo ' - '.userdate($activity->timestamp);
            echo html_writer::end_tag('div');
        }

        echo html_writer::end_tag('div');
    }

    echo html_writer::empty_tag('br', array('style'=>'clear:both'));
}

/**
 * Regular jobs to execute via cron
 *
 * @return boolean true on success, false otherwise
 */
function workshep_cron() {
    global $CFG, $DB;

    $now = time();

    mtrace(' processing workshep subplugins ...');
    $savedexceptions = cron_execute_plugin_type('workshepallocation', 'workshep allocation methods', true);

    // now when the scheduled allocator had a chance to do its job, check if there
    // are some worksheps to switch into the assessment phase
    $worksheps = $DB->get_records_select("workshep",
        "phase = 20 AND phaseswitchassessment = 1 AND submissionend > 0 AND submissionend < ?", array($now));

    if (!empty($worksheps)) {
        mtrace('Processing automatic assessment phase switch in '.count($worksheps).' workshep(s) ... ', '');
        require_once($CFG->dirroot.'/mod/workshep/locallib.php');
        foreach ($worksheps as $workshep) {
            $cm = get_coursemodule_from_instance('workshep', $workshep->id, $workshep->course, false, MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
            $workshep = new workshep($workshep, $cm, $course);
            $workshep->switch_phase(workshep::PHASE_ASSESSMENT);

            $params = array(
                'objectid' => $workshep->id,
                'context' => $workshep->context,
                'courseid' => $workshep->course->id,
                'other' => array(
                    'workshepphase' => $workshep->phase
                )
            );
            $event = \mod_workshep\event\phase_switched::create($params);
            $event->trigger();

            // disable the automatic switching now so that it is not executed again by accident
            // if the teacher changes the phase back to the submission one
            $DB->set_field('workshep', 'phaseswitchassessment', 0, array('id' => $workshep->id));

            // todo inform the teachers
        }
        mtrace('done');
    }

    // BASE-1581.
    $savedexceptions = array_filter($savedexceptions);
    if ($savedexceptions) {
        return array(false, $savedexceptions);
    }

    return true;
}

/**
 * Is a given scale used by the instance of workshep?
 *
 * The function asks all installed grading strategy subplugins. The workshep
 * core itself does not use scales. Both grade for submission and grade for
 * assessments do not use scales.
 *
 * @param int $workshepid id of workshep instance
 * @param int $scaleid id of the scale to check
 * @return bool
 */
function workshep_scale_used($workshepid, $scaleid) {
    global $CFG; // other files included from here

    $strategies = core_component::get_plugin_list('workshepform');
    foreach ($strategies as $strategy => $strategypath) {
        $strategylib = $strategypath . '/lib.php';
        if (is_readable($strategylib)) {
            require_once($strategylib);
        } else {
            throw new coding_exception('the grading forms subplugin must contain library ' . $strategylib);
        }
        $classname = 'workshep_' . $strategy . '_strategy';
        if (method_exists($classname, 'scale_used')) {
            if (call_user_func_array(array($classname, 'scale_used'), array($scaleid, $workshepid))) {
                // no need to include any other files - scale is used
                return true;
            }
        }
    }

    return false;
}

/**
 * Is a given scale used by any instance of workshep?
 *
 * The function asks all installed grading strategy subplugins. The workshep
 * core itself does not use scales. Both grade for submission and grade for
 * assessments do not use scales.
 *
 * @param int $scaleid id of the scale to check
 * @return bool
 */
function workshep_scale_used_anywhere($scaleid) {
    global $CFG; // other files included from here

    $strategies = core_component::get_plugin_list('workshepform');
    foreach ($strategies as $strategy => $strategypath) {
        $strategylib = $strategypath . '/lib.php';
        if (is_readable($strategylib)) {
            require_once($strategylib);
        } else {
            throw new coding_exception('the grading forms subplugin must contain library ' . $strategylib);
        }
        $classname = 'workshep_' . $strategy . '_strategy';
        if (method_exists($classname, 'scale_used')) {
            if (call_user_func(array($classname, 'scale_used'), $scaleid)) {
                // no need to include any other files - scale is used
                return true;
            }
        }
    }

    return false;
}

/**
 * Returns all other caps used in the module
 *
 * @return array
 */
function workshep_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

////////////////////////////////////////////////////////////////////////////////
// Gradebook API                                                              //
////////////////////////////////////////////////////////////////////////////////

/**
 * Creates or updates grade items for the give workshep instance
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php. Also used by
 * {@link workshep_update_grades()}.
 *
 * @param stdClass $workshep instance object with extra cmidnumber property
 * @param stdClass $submissiongrades data for the first grade item
 * @param stdClass $assessmentgrades data for the second grade item
 * @return void
 */
function workshep_grade_item_update(stdclass $workshep, $submissiongrades=null, $assessmentgrades=null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $a = new stdclass();
    $a->workshepname = clean_param($workshep->name, PARAM_NOTAGS);

    $item = array();
    $item['itemname'] = get_string('gradeitemsubmission', 'workshep', $a);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax']  = $workshep->grade;
    $item['grademin']  = 0;
    grade_update('mod/workshep', $workshep->course, 'mod', 'workshep', $workshep->id, 0, $submissiongrades , $item);

    $item = array();
    $item['itemname'] = get_string('gradeitemassessment', 'workshep', $a);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax']  = $workshep->gradinggrade;
    $item['grademin']  = 0;
    grade_update('mod/workshep', $workshep->course, 'mod', 'workshep', $workshep->id, 1, $assessmentgrades, $item);
}

/**
 * Update workshep grades in the gradebook
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php
 *
 * @category grade
 * @param stdClass $workshep instance object with extra cmidnumber and modname property
 * @param int $userid        update grade of specific user only, 0 means all participants
 * @return void
 */
function workshep_update_grades(stdclass $workshep, $userid=0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    //this is necessary because we need data like the grouping id
    $course     = $DB->get_record('course', array('id' => $workshep->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('workshep', $workshep->id, $course->id, false, MUST_EXIST);
    //todo: this ignores userid

    $submissiongrades = array();
    if($workshep->teammode) {
        $whereuser  = '';
        $whereuserparams = array();
        if ($userid) {
            $groups = groups_get_all_groups($cm->course, $userid, $cm->groupingid);
            if(count($groups) > 1) {
                print_error('teammode_multiplegroupswarning','workshep',new moodle_url('/group/groupings.php',array('id' => $workshep->course->id)),implode($users,', '));
            } else if (count($groups) == 1) {
                $group = key($groups);
                list($whereuser, $whereuserparams) = $DB->get_in_or_equal(array_keys(groups_get_members($group,'u.id','')),SQL_PARAMS_NAMED);
            }
            //if a user isn't in a team for team mode, they can't have submitted anything
        } else {
            $allgroups = groups_get_all_groups($cm->course, 0, $cm->groupingid);
            //todo: on duplicate key error out
            $groupmembers = $DB->get_records_list('groups_members','groupid',array_keys($allgroups),'','userid,groupid');
            //invert this array for use later
            $membergroups = array();
            foreach($groupmembers as $i) {
                $membergroups[$i->groupid][] = $i->userid;
            }
        }

        $params = array('workshepid' => $workshep->id, 'userid' => $userid) + $whereuserparams;
        $sql = 'SELECT authorid, grade, gradeover, gradeoverby, feedbackauthor, feedbackauthorformat, timemodified, timegraded
                  FROM {workshep_submissions}
                 WHERE workshepid = :workshepid AND example=0 ' . $whereuser . ' ORDER BY timemodified DESC';

        $records = $DB->get_records_sql($sql, $params);
        $submissions = array();

        //this hinges on ORDER BY timemodified DESC
        if ( isset($allgroups) ) {
            foreach($records as $r) {
                $grp = $groupmembers[$r->authorid]->groupid;
                if (isset($submissions[$grp])) continue;
                $submissions[$grp] = $r;
            }
        }

//        print_r($submissions);


        foreach($submissions as $grp => $s) {
            $members = $membergroups[$grp];
            foreach($members as $m) {
                $grade = new stdclass();
                $grade->userid = $m;
                if (!is_null($s->gradeover)) {
                    $grade->rawgrade = grade_floatval($workshep->grade * $s->gradeover / 100);
                    $grade->usermodified = $s->gradeoverby;
                } else {
                    $grade->rawgrade = grade_floatval($workshep->grade * $s->grade / 100);
                }
                $grade->feedback = $s->feedbackauthor;
                $grade->feedbackformat = $s->feedbackauthorformat;
                $grade->datesubmitted = $s->timemodified;
                $grade->dategraded = $s->timegraded;
                $submissiongrades[$m] = $grade;
            }
        }
    } else {
        $whereuser = $userid ? ' AND authorid = :userid' : '';
        $params = array('workshepid' => $workshep->id, 'userid' => $userid);
        $sql = 'SELECT authorid, grade, gradeover, gradeoverby, feedbackauthor, feedbackauthorformat, timemodified, timegraded
                  FROM {workshep_submissions}
                 WHERE workshepid = :workshepid AND example=0' . $whereuser;
        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {
            $grade = new stdclass();
            $grade->userid = $record->authorid;
            if (!is_null($record->gradeover)) {
                $grade->rawgrade = grade_floatval($workshep->grade * $record->gradeover / 100);
                $grade->usermodified = $record->gradeoverby;
            } else {
                $grade->rawgrade = grade_floatval($workshep->grade * $record->grade / 100);
            }
            $grade->feedback = $record->feedbackauthor;
            $grade->feedbackformat = $record->feedbackauthorformat;
            $grade->datesubmitted = $record->timemodified;
            $grade->dategraded = $record->timegraded;
            $submissiongrades[$record->authorid] = $grade;
        }
    }

    $whereuser = $userid ? ' AND userid = :userid' : '';
    $params = array('workshepid' => $workshep->id, 'userid' => $userid);
    $sql = 'SELECT userid, gradinggrade, timegraded
              FROM {workshep_aggregations}
             WHERE workshepid = :workshepid' . $whereuser;
    $records = $DB->get_records_sql($sql, $params);
    $assessmentgrades = array();
    foreach ($records as $record) {
        $grade = new stdclass();
        $grade->userid = $record->userid;
        $grade->rawgrade = grade_floatval($workshep->gradinggrade * $record->gradinggrade / 100);
        $grade->dategraded = $record->timegraded;
        $assessmentgrades[$record->userid] = $grade;
    }

    $teameval_plugin = core_plugin_manager::instance()->get_plugin_info('local_teameval');
    if ($teameval_plugin && $cm && !empty($submissiongrades)) {
        // TODO: This is not ideal. It turns out a workshop can exist without a corresponding coursemodule.
        // But TE depends pretty heavily on using the coursemodule as a representative of its host plugin.
        // Possible solution: fire off an adhoc task?
        $evaluationcontext = workshep_get_evaluation_context($cm);
        $submissiongrades = $evaluationcontext->update_grades($submissiongrades);
    }

    workshep_grade_item_update($workshep, $submissiongrades, $assessmentgrades);
}

/**
 * Update the grade items categories if they are changed via mod_form.php
 *
 * We must do it manually here in the workshep module because modedit supports only
 * single grade item while we use two.
 *
 * @param stdClass $workshep An object from the form in mod_form.php
 */
function workshep_grade_item_category_update($workshep) {

    $gradeitems = grade_item::fetch_all(array(
        'itemtype'      => 'mod',
        'itemmodule'    => 'workshep',
        'iteminstance'  => $workshep->id,
        'courseid'      => $workshep->course));

    if (!empty($gradeitems)) {
        foreach ($gradeitems as $gradeitem) {
            if ($gradeitem->itemnumber == 0) {
                if (isset($workshep->submissiongradepass) &&
                        $gradeitem->gradepass != $workshep->submissiongradepass) {
                    $gradeitem->gradepass = $workshep->submissiongradepass;
                    $gradeitem->update();
                }
                if ($gradeitem->categoryid != $workshep->gradecategory) {
                    $gradeitem->set_parent($workshep->gradecategory);
                }
            } else if ($gradeitem->itemnumber == 1) {
                if (isset($workshep->gradinggradepass) &&
                        $gradeitem->gradepass != $workshep->gradinggradepass) {
                    $gradeitem->gradepass = $workshep->gradinggradepass;
                    $gradeitem->update();
                }
                if ($gradeitem->categoryid != $workshep->gradinggradecategory) {
                    $gradeitem->set_parent($workshep->gradinggradecategory);
                }
            }
        }
    }
}

////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area workshep_intro for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @package  mod_workshep
 * @category files
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function workshep_get_file_areas($course, $cm, $context) {
    $areas = array();
    $areas['instructauthors']          = get_string('areainstructauthors', 'workshep');
    $areas['instructreviewers']        = get_string('areainstructreviewers', 'workshep');
    $areas['submission_content']       = get_string('areasubmissioncontent', 'workshep');
    $areas['submission_attachment']    = get_string('areasubmissionattachment', 'workshep');
    $areas['conclusion']               = get_string('areaconclusion', 'workshep');
    $areas['overallfeedback_content']  = get_string('areaoverallfeedbackcontent', 'workshep');
    $areas['overallfeedback_attachment'] = get_string('areaoverallfeedbackattachment', 'workshep');

    return $areas;
}

/**
 * Serves the files from the workshep file areas
 *
 * Apart from module intro (handled by pluginfile.php automatically), workshep files may be
 * media inserted into submission content (like images) and submission attachments. For these two,
 * the fileareas submission_content and submission_attachment are used.
 * Besides that, areas instructauthors, instructreviewers and conclusion contain the media
 * embedded using the mod_form.php.
 *
 * @package  mod_workshep
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the workshep's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function workshep_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    if ($filearea === 'instructauthors' or $filearea === 'instructreviewers' or $filearea === 'conclusion') {
        // The $args are supposed to contain just the path, not the item id.
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_workshep/$filearea/0/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            send_file_not_found();
        }
        send_stored_file($file, null, 0, $forcedownload, $options);

    } else if ($filearea === 'submission_content' or $filearea === 'submission_attachment') {
        $itemid = (int)array_shift($args);
        if (!$workshep = $DB->get_record('workshep', array('id' => $cm->instance))) {
            return false;
        }
        if (!$submission = $DB->get_record('workshep_submissions', array('id' => $itemid, 'workshepid' => $workshep->id))) {
            return false;
        }

        // make sure the user is allowed to see the file
        if (empty($submission->example)) {
            if ($USER->id != $submission->authorid) {
                if ($submission->published == 1 and $workshep->phase == 50
                        and has_capability('mod/workshep:viewpublishedsubmissions', $context)) {
                    // Published submission, we can go (workshep does not take the group mode
                    // into account in this case yet).
                } else if (!$DB->record_exists('workshep_assessments', array('submissionid' => $submission->id, 'reviewerid' => $USER->id))) {
                    if (!has_capability('mod/workshep:viewallsubmissions', $context)) {
                        send_file_not_found();
                    } else {
                        $gmode = groups_get_activity_groupmode($cm, $course);
                        if ($gmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                            // check there is at least one common group with both the $USER
                            // and the submission author
                            $sql = "SELECT 'x'
                                      FROM {workshep_submissions} s
                                      JOIN {user} a ON (a.id = s.authorid)
                                      JOIN {groups_members} agm ON (a.id = agm.userid)
                                      JOIN {user} u ON (u.id = ?)
                                      JOIN {groups_members} ugm ON (u.id = ugm.userid)
                                     WHERE s.example = 0 AND s.workshepid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
                            $params = array($USER->id, $workshep->id, $submission->id);
                            if (!$DB->record_exists_sql($sql, $params)) {
                                send_file_not_found();
                            }
                        }
                    }
                }
            }
        }

        $fs = get_file_storage();
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_workshep/$filearea/$itemid/$relativepath";
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }
        // finally send the file
        // these files are uploaded by students - forcing download for security reasons
        send_stored_file($file, 0, 0, true, $options);

    } else if ($filearea === 'overallfeedback_content' or $filearea === 'overallfeedback_attachment') {
        $itemid = (int)array_shift($args);
        if (!$workshep = $DB->get_record('workshep', array('id' => $cm->instance))) {
            return false;
        }
        if (!$assessment = $DB->get_record('workshep_assessments', array('id' => $itemid))) {
            return false;
        }
        if (!$submission = $DB->get_record('workshep_submissions', array('id' => $assessment->submissionid, 'workshepid' => $workshep->id))) {
            return false;
        }

        if ($USER->id == $assessment->reviewerid) {
            // Reviewers can always see their own files.
        } else if ($USER->id == $submission->authorid and $workshep->phase == 50) {
            // Authors can see the feedback once the workshep is closed.
        } else if (!empty($submission->example) and $assessment->weight == 1) {
            // Reference assessments of example submissions can be displayed.
        } else if (!has_capability('mod/workshep:viewallassessments', $context)) {
            send_file_not_found();
        } else {
            $gmode = groups_get_activity_groupmode($cm, $course);
            if ($gmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                // Check there is at least one common group with both the $USER
                // and the submission author.
                $sql = "SELECT 'x'
                          FROM {workshep_submissions} s
                          JOIN {user} a ON (a.id = s.authorid)
                          JOIN {groups_members} agm ON (a.id = agm.userid)
                          JOIN {user} u ON (u.id = ?)
                          JOIN {groups_members} ugm ON (u.id = ugm.userid)
                         WHERE s.example = 0 AND s.workshepid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
                $params = array($USER->id, $workshep->id, $submission->id);
                if (!$DB->record_exists_sql($sql, $params)) {
                    send_file_not_found();
                }
            }
        }

        $fs = get_file_storage();
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_workshep/$filearea/$itemid/$relativepath";
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }
        // finally send the file
        // these files are uploaded by students - forcing download for security reasons
        send_stored_file($file, 0, 0, true, $options);
    }

    return false;
}

/**
 * File browsing support for workshep file areas
 *
 * @package  mod_workshep
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function workshep_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB, $USER;

    /** @var array internal cache for author names */
    static $submissionauthors = array();

    $fs = get_file_storage();

    if ($filearea === 'submission_content' or $filearea === 'submission_attachment') {

        if (!has_capability('mod/workshep:viewallsubmissions', $context)) {
            return null;
        }

        if (is_null($itemid)) {
            // no itemid (submissionid) passed, display the list of all submissions
            require_once($CFG->dirroot . '/mod/workshep/fileinfolib.php');
            return new workshep_file_info_submissions_container($browser, $course, $cm, $context, $areas, $filearea);
        }

        // make sure the user can see the particular submission in separate groups mode
        $gmode = groups_get_activity_groupmode($cm, $course);

        if ($gmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
            // check there is at least one common group with both the $USER
            // and the submission author (this is not expected to be a frequent
            // usecase so we can live with pretty ineffective one query per submission here...)
            $sql = "SELECT 'x'
                      FROM {workshep_submissions} s
                      JOIN {user} a ON (a.id = s.authorid)
                      JOIN {groups_members} agm ON (a.id = agm.userid)
                      JOIN {user} u ON (u.id = ?)
                      JOIN {groups_members} ugm ON (u.id = ugm.userid)
                     WHERE s.example = 0 AND s.workshepid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
            $params = array($USER->id, $cm->instance, $itemid);
            if (!$DB->record_exists_sql($sql, $params)) {
                return null;
            }
        }

        // we are inside some particular submission container

        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        if (!$storedfile = $fs->get_file($context->id, 'mod_workshep', $filearea, $itemid, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_workshep', $filearea, $itemid);
            } else {
                // not found
                return null;
            }
        }

        // Checks to see if the user can manage files or is the owner.
        // TODO MDL-33805 - Do not use userid here and move the capability check above.
        if (!has_capability('moodle/course:managefiles', $context) && $storedfile->get_userid() != $USER->id) {
            return null;
        }

        // let us display the author's name instead of itemid (submission id)

        if (isset($submissionauthors[$itemid])) {
            $topvisiblename = $submissionauthors[$itemid];

        } else {

            $userfields = get_all_user_name_fields(true, 'u');
            $sql = "SELECT s.id, $userfields
                      FROM {workshep_submissions} s
                      JOIN {user} u ON (s.authorid = u.id)
                     WHERE s.example = 0 AND s.workshepid = ?";
            $params = array($cm->instance);
            $rs = $DB->get_recordset_sql($sql, $params);

            foreach ($rs as $submissionauthor) {
                $title = s(fullname($submissionauthor)); // this is generally not unique...
                $submissionauthors[$submissionauthor->id] = $title;
            }
            $rs->close();

            if (!isset($submissionauthors[$itemid])) {
                // should not happen
                return null;
            } else {
                $topvisiblename = $submissionauthors[$itemid];
            }
        }

        $urlbase = $CFG->wwwroot . '/pluginfile.php';
        // do not allow manual modification of any files!
        return new file_info_stored($browser, $context, $storedfile, $urlbase, $topvisiblename, true, true, false, false);
    }

    if ($filearea === 'overallfeedback_content' or $filearea === 'overallfeedback_attachment') {

        if (!has_capability('mod/workshep:viewallassessments', $context)) {
            return null;
        }

        if (is_null($itemid)) {
            // No itemid (assessmentid) passed, display the list of all assessments.
            require_once($CFG->dirroot . '/mod/workshep/fileinfolib.php');
            return new workshep_file_info_overallfeedback_container($browser, $course, $cm, $context, $areas, $filearea);
        }

        // Make sure the user can see the particular assessment in separate groups mode.
        $gmode = groups_get_activity_groupmode($cm, $course);
        if ($gmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
            // Check there is at least one common group with both the $USER
            // and the submission author.
            $sql = "SELECT 'x'
                      FROM {workshep_submissions} s
                      JOIN {user} a ON (a.id = s.authorid)
                      JOIN {groups_members} agm ON (a.id = agm.userid)
                      JOIN {user} u ON (u.id = ?)
                      JOIN {groups_members} ugm ON (u.id = ugm.userid)
                     WHERE s.example = 0 AND s.workshepid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
            $params = array($USER->id, $cm->instance, $itemid);
            if (!$DB->record_exists_sql($sql, $params)) {
                return null;
            }
        }

        // We are inside a particular assessment container.
        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        if (!$storedfile = $fs->get_file($context->id, 'mod_workshep', $filearea, $itemid, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_workshep', $filearea, $itemid);
            } else {
                // Not found
                return null;
            }
        }

        // Check to see if the user can manage files or is the owner.
        if (!has_capability('moodle/course:managefiles', $context) and $storedfile->get_userid() != $USER->id) {
            return null;
        }

        $urlbase = $CFG->wwwroot . '/pluginfile.php';

        // Do not allow manual modification of any files.
        return new file_info_stored($browser, $context, $storedfile, $urlbase, $itemid, true, true, false, false);
    }

    if ($filearea == 'instructauthors' or $filearea == 'instructreviewers' or $filearea == 'conclusion') {
        // always only itemid 0

        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        if (!$storedfile = $fs->get_file($context->id, 'mod_workshep', $filearea, 0, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_workshep', $filearea, 0);
            } else {
                // not found
                return null;
            }
        }
        return new file_info_stored($browser, $context, $storedfile, $urlbase, $areas[$filearea], false, true, true, false);
    }
}

////////////////////////////////////////////////////////////////////////////////
// Navigation API                                                             //
////////////////////////////////////////////////////////////////////////////////

/**
 * Extends the global navigation tree by adding workshep nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the workshep module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function workshep_extend_navigation(navigation_node $navref, stdclass $course, stdclass $module, cm_info $cm) {
    global $CFG;

    if (has_capability('mod/workshep:submit', context_module::instance($cm->id))) {
        $url = new moodle_url('/mod/workshep/submission.php', array('cmid' => $cm->id));
        $mysubmission = $navref->add(get_string('mysubmission', 'workshep'), $url);
        $mysubmission->mainnavonly = true;
    }
}

/**
 * Extends the settings navigation with the Workshop settings

 * This function is called when the context for the page is a workshep module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $workshepnode {@link navigation_node}
 */
function workshep_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $workshepnode=null) {
    global $PAGE;

    //$workshepobject = $DB->get_record("workshep", array("id" => $PAGE->cm->instance));

    if (has_capability('mod/workshep:editdimensions', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/workshep/editform.php', array('cmid' => $PAGE->cm->id));
        $workshepnode->add(get_string('editassessmentform', 'workshep'), $url, settings_navigation::TYPE_SETTING);
    }
    if (has_capability('mod/workshep:allocate', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/workshep/allocation.php', array('cmid' => $PAGE->cm->id));
        $workshepnode->add(get_string('allocate', 'workshep'), $url, settings_navigation::TYPE_SETTING);
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function workshep_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-workshep-*'=>get_string('page-mod-workshep-x', 'workshep'));
    return $module_pagetype;
}

////////////////////////////////////////////////////////////////////////////////
// Calendar API                                                               //
////////////////////////////////////////////////////////////////////////////////

/**
 * Updates the calendar events associated to the given workshep
 *
 * @param stdClass $workshep the workshep instance record
 * @param int $cmid course module id
 */
function workshep_calendar_update(stdClass $workshep, $cmid) {
    global $DB;

    // get the currently registered events so that we can re-use their ids
    $currentevents = $DB->get_records('event', array('modulename' => 'workshep', 'instance' => $workshep->id));

    // the common properties for all events
    $base = new stdClass();
    $base->description  = format_module_intro('workshep', $workshep, $cmid, false);
    $base->courseid     = $workshep->course;
    $base->groupid      = 0;
    $base->userid       = 0;
    $base->modulename   = 'workshep';
    $base->instance     = $workshep->id;
    $base->visible      = instance_is_visible('workshep', $workshep);
    $base->timeduration = 0;

    if ($workshep->submissionstart) {
        $event = clone($base);
        $event->name = get_string('submissionstartevent', 'mod_workshep', $workshep->name);
        $event->eventtype = WORKSHEP_EVENT_TYPE_SUBMISSION_OPEN;
        $event->type = empty($workshep->submissionend) ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
        $event->timestart = $workshep->submissionstart;
        $event->timesort  = $workshep->submissionstart;
        if ($reusedevent = array_shift($currentevents)) {
            $event->id = $reusedevent->id;
        } else {
            // should not be set but just in case
            unset($event->id);
        }
        // update() will reuse a db record if the id field is set
        $eventobj = new calendar_event($event);
        $eventobj->update($event, false);
    }

    if ($workshep->submissionend) {
        $event = clone($base);
        $event->name = get_string('submissionendevent', 'mod_workshep', $workshep->name);
        $event->eventtype = WORKSHEP_EVENT_TYPE_SUBMISSION_CLOSE;
        $event->type      = CALENDAR_EVENT_TYPE_ACTION;
        $event->timestart = $workshep->submissionend;
        $event->timesort  = $workshep->submissionend;
        if ($reusedevent = array_shift($currentevents)) {
            $event->id = $reusedevent->id;
        } else {
            // should not be set but just in case
            unset($event->id);
        }
        // update() will reuse a db record if the id field is set
        $eventobj = new calendar_event($event);
        $eventobj->update($event, false);
    }

    if ($workshep->assessmentstart) {
        $event = clone($base);
        $event->name = get_string('assessmentstartevent', 'mod_workshep', $workshep->name);
        $event->eventtype = WORKSHEP_EVENT_TYPE_ASSESSMENT_OPEN;
        $event->type      = empty($workshep->assessmentend) ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
        $event->timestart = $workshep->assessmentstart;
        $event->timesort  = $workshep->assessmentstart;
        if ($reusedevent = array_shift($currentevents)) {
            $event->id = $reusedevent->id;
        } else {
            // should not be set but just in case
            unset($event->id);
        }
        // update() will reuse a db record if the id field is set
        $eventobj = new calendar_event($event);
        $eventobj->update($event, false);
    }

    if ($workshep->assessmentend) {
        $event = clone($base);
        $event->name = get_string('assessmentendevent', 'mod_workshep', $workshep->name);
        $event->eventtype = WORKSHEP_EVENT_TYPE_ASSESSMENT_CLOSE;
        $event->type      = CALENDAR_EVENT_TYPE_ACTION;
        $event->timestart = $workshep->assessmentend;
        $event->timesort  = $workshep->assessmentend;
        if ($reusedevent = array_shift($currentevents)) {
            $event->id = $reusedevent->id;
        } else {
            // should not be set but just in case
            unset($event->id);
        }
        // update() will reuse a db record if the id field is set
        $eventobj = new calendar_event($event);
        $eventobj->update($event, false);
    }

    // delete any leftover events
    foreach ($currentevents as $oldevent) {
        $oldevent = calendar_event::load($oldevent);
        $oldevent->delete();
    }
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_workshep_core_calendar_provide_event_action(calendar_event $event,
                                                         \core_calendar\action_factory $factory) {

    $cm = get_fast_modinfo($event->courseid)->instances['workshep'][$event->instance];

    return $factory->create_instance(
        get_string('viewworkshepsummary', 'workshep'),
        new \moodle_url('/mod/workshep/view.php', array('id' => $cm->id)),
        1,
        true
    );
}

////////////////////////////////////////////////////////////////////////////////
// Course reset API                                                           //
////////////////////////////////////////////////////////////////////////////////

/**
 * Extends the course reset form with workshep specific settings.
 *
 * @param MoodleQuickForm $mform
 */
function workshep_reset_course_form_definition($mform) {

    $mform->addElement('header', 'workshepheader', get_string('modulenameplural', 'mod_workshep'));

    $mform->addElement('advcheckbox', 'reset_workshep_submissions', get_string('resetsubmissions', 'mod_workshep'));
    $mform->addHelpButton('reset_workshep_submissions', 'resetsubmissions', 'mod_workshep');

    $mform->addElement('advcheckbox', 'reset_workshep_assessments', get_string('resetassessments', 'mod_workshep'));
    $mform->addHelpButton('reset_workshep_assessments', 'resetassessments', 'mod_workshep');
    $mform->disabledIf('reset_workshep_assessments', 'reset_workshep_submissions', 'checked');

    $mform->addElement('advcheckbox', 'reset_workshep_phase', get_string('resetphase', 'mod_workshep'));
    $mform->addHelpButton('reset_workshep_phase', 'resetphase', 'mod_workshep');

    $teameval_plugin = core_plugin_manager::instance()->get_plugin_info('local_teameval');
    if ($teameval_plugin) {
        \mod_workshep\evaluation_context::reset_course_form_definition($mform);
    }
}

/**
 * Provides default values for the workshep settings in the course reset form.
 *
 * @param stdClass $course The course to be reset.
 */
function workshep_reset_course_form_defaults(stdClass $course) {

    $defaults = array(
        'reset_workshep_submissions'    => 1,
        'reset_workshep_assessments'    => 1,
        'reset_workshep_phase'          => 1,
    );

    $teameval_plugin = core_plugin_manager::instance()->get_plugin_info('local_teameval');
    if ($teameval_plugin) {
        $defaults = array_merge($defaults, \mod_workshep\evaluation_context::reset_course_form_defaults());
    }

    return $defaults;
}

/**
 * Performs the reset of all workshep instances in the course.
 *
 * @param stdClass $data The actual course reset settings.
 * @return array List of results, each being array[(string)component, (string)item, (string)error]
 */
function workshep_reset_userdata(stdClass $data) {
    global $CFG, $DB;

    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.
    shift_course_mod_dates('workshep', array('submissionstart', 'submissionend', 'assessmentstart', 'assessmentend'),
        $data->timeshift, $data->courseid);
    $status = array();
    $status[] = array('component' => get_string('modulenameplural', 'workshep'), 'item' => get_string('datechanged'),
        'error' => false);

    if (empty($data->reset_workshep_submissions)
            and empty($data->reset_workshep_assessments)
            and empty($data->reset_workshep_phase) ) {
        // Nothing to do here.
        return $status;
    }

    $worksheprecords = $DB->get_records('workshep', array('course' => $data->courseid));

    if (empty($worksheprecords)) {
        // What a boring course - no worksheps here!
        return $status;
    }

    require_once($CFG->dirroot . '/mod/workshep/locallib.php');

    $course = $DB->get_record('course', array('id' => $data->courseid), '*', MUST_EXIST);

    $teameval_plugin = core_plugin_manager::instance()->get_plugin_info('local_teameval');

    foreach ($worksheprecords as $worksheprecord) {
        $cm = get_coursemodule_from_instance('workshep', $worksheprecord->id, $course->id, false, MUST_EXIST);
        $workshep = new workshep($worksheprecord, $cm, $course);
        $status = array_merge($status, $workshep->reset_userdata($data));
        if ($teameval_plugin) {
            $cminfo = get_fast_modinfo($cm->course)->get_cm($cm->id);
            $evalcontext = new \mod_workshep\evaluation_context($workshep, $cminfo);
            $status = array_merge($status, $evalcontext->reset_userdata($data));
        }
    }

    return $status;
}

function workshep_get_evaluation_context($cm) {
    global $DB, $CFG;

    require_once(dirname(__FILE__).'/locallib.php');

    $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $worksheprecord = $DB->get_record('workshep', array('id' => $cm->instance), '*', MUST_EXIST);

    if ($cm instanceof stdClass) {
        $cmrecord = $cm;
        $cm = get_fast_modinfo($cm->course)->get_cm($cm->id);
    } else if ($cm instanceof cm_info) {
        $cmrecord = $cm->get_course_module_record();
    } else {
        throw new coding_exception("unknown cm type", gettype($cm));
    }

    $workshep = new workshep($worksheprecord, $cmrecord, $course);
    return new \mod_workshep\evaluation_context($workshep, $cm);

}

/**
 * Get icon mapping for font-awesome.
 */
function mod_workshep_get_fontawesome_icon_map() {
    return [
        'mod_workshep:userplan/task-info' => 'fa-info text-info',
        'mod_workshep:userplan/task-todo' => 'fa-square-o',
        'mod_workshep:userplan/task-done' => 'fa-check text-success',
        'mod_workshep:userplan/task-fail' => 'fa-remove text-danger',
    ];
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.4
 */
function workshep_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $DB, $USER;

    $updates = course_check_module_updates_since($cm, $from, array('instructauthors', 'instructreviewers', 'conclusion'), $filter);

    // Check if there are new submissions, assessments or assessments grades in the workshep.
    $updates->submissions = (object) array('updated' => false);
    $updates->assessments = (object) array('updated' => false);
    $updates->assessmentgrades = (object) array('updated' => false);

    $select = 'workshepid = ? AND authorid = ? AND (timecreated > ? OR timegraded > ? OR timemodified > ?)';
    $params = array($cm->instance, $USER->id, $from, $from, $from);
    $submissions = $DB->get_records_select('workshep_submissions', $select, $params, '', 'id');
    if (!empty($submissions)) {
        $updates->submissions->updated = true;
        $updates->submissions->itemids = array_keys($submissions);
    }

    // Get assessments updates (both submissions reviewed by me or reviews by others).
    $select = "SELECT a.id
                 FROM {workshep_assessments} a
                 JOIN {workshep_submissions} s ON a.submissionid = s.id
                 WHERE s.workshepid = ? AND (a.timecreated > ? OR a.timemodified > ?) AND (s.authorid = ? OR a.reviewerid = ?)";
    $params = array($cm->instance, $from, $from, $USER->id, $USER->id);
    $assessments = $DB->get_records_sql($select, $params);
    if (!empty($assessments)) {
        $updates->assessments->updated = true;
        $updates->assessments->itemids = array_keys($assessments);
    }
    // Finally assessment aggregated grades.
    $select = 'workshepid = ? AND userid = ? AND timegraded > ?';
    $params = array($cm->instance, $USER->id, $from);
    $assessmentgrades = $DB->get_records_select('workshep_aggregations', $select, $params, '', 'id');
    if (!empty($assessmentgrades)) {
        $updates->assessmentgrades->updated = true;
        $updates->assessmentgrades->itemids = array_keys($assessmentgrades);
    }

    // Now, teachers should see other students updates.
    $canviewallsubmissions = has_capability('mod/workshep:viewallsubmissions', $cm->context);
    $canviewallassessments = has_capability('mod/workshep:viewallassessments', $cm->context);
    if ($canviewallsubmissions || $canviewallassessments) {

        $insql = '';
        $inparams = array();
        // To filter by users in my groups when separated groups are forced.
        if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS) {
            $groupusers = array_keys(groups_get_activity_shared_group_members($cm));
            if (empty($groupusers)) {
                return $updates;
            }
            list($insql, $inparams) = $DB->get_in_or_equal($groupusers);
        }

        if ($canviewallsubmissions) {
            $updates->usersubmissions = (object) array('updated' => false);
            $select = 'workshepid = ? AND (timecreated > ? OR timegraded > ? OR timemodified > ?)';
            $params = array($cm->instance, $from, $from, $from);
            if (!empty($insql)) {
                $select .= " AND authorid $insql";
                $params = array_merge($params, $inparams);
            }
            $usersubmissions = $DB->get_records_select('workshep_submissions', $select, $params, '', 'id');
            if (!empty($usersubmissions)) {
                $updates->usersubmissions->updated = true;
                $updates->usersubmissions->itemids = array_keys($usersubmissions);
            }
        }

        if ($canviewallassessments) {
            $updates->userassessments = (object) array('updated' => false);
            $select = "SELECT a.id
                         FROM {workshep_assessments} a
                         JOIN {workshep_submissions} s ON a.submissionid = s.id
                        WHERE s.workshepid = ? AND (a.timecreated > ? OR a.timemodified > ?)";
            $params = array($cm->instance, $from, $from);
            if (!empty($insql)) {
                $select .= " AND s.reviewerid $insql";
                $params = array_merge($params, $inparams);
            }
            $userassessments = $DB->get_records_sql($select, $params);
            if (!empty($userassessments)) {
                $updates->userassessments->updated = true;
                $updates->userassessments->itemids = array_keys($userassessments);
            }

            $updates->userassessmentgrades = (object) array('updated' => false);
            $select = 'workshepid = ? AND timegraded > ?';
            $params = array($cm->instance, $USER->id);
            if (!empty($insql)) {
                $select .= " AND userid $insql";
                $params = array_merge($params, $inparams);
            }
            $userassessmentgrades = $DB->get_records_select('workshep_aggregations', $select, $params, '', 'id');
            if (!empty($userassessmentgrades)) {
                $updates->userassessmentgrades->updated = true;
                $updates->userassessmentgrades->itemids = array_keys($userassessmentgrades);
            }
        }
    }
    return $updates;
}

/**
 * Create automatic submissions
 *
 * @return void;
 */
function workshep_create_nosubmissionrequired($workshep) {
    global $DB;

    if ($workshep) {
        $submissionusers = array();
        // Process through the enrolled users and skip the existing submissions
        $enrolledusers = get_enrolled_users($workshep->context);
        $groups = $workshep->get_grouped($enrolledusers);
        foreach ($groups as $groupid => $users) {
            if ($groupid) {
                if ($workshep->teammode) {
                    list($userid, $user) = each($users);
                    $submissionusers[] = $user;
                } else {
                    foreach($users as $userid => $user) {
                        $submissionusers[$userid] = $user;
                    }
                }
            }
        }

        // Re-process if no groups found
        if (empty($submissionusers)) {
            foreach ($groups as $groupid => $users) {
                if ($groupid == 0) {
                    foreach($users as $userid => $user) {
                        $submissionusers[$userid] = $user;
                    }
                 }
             }
         }

        if ($submissionusers) {
            // Grab current submissions and index by userid
            $submissions = $workshep->get_submissions('all');
            $submission_by_userid = array();
            foreach ($submissions as $submission) {
                $submission_by_userid[$submission->authorid] = $submission;
            }

            foreach ($submissionusers as $user) {
                if (!empty($submission_by_userid[$user->id])) {
                    continue;
                }

                // Template for content replacement in submission.
                $template = new stdclass();
                $template->fullname = fullname($user);

                $submission = new stdclass();
                $submission->workshepid           = $workshep->cm->instance;
                $submission->authorid             = $user->id;
                $submission->example              = 0;
                $submission->grade                = null;
                $submission->gradeover            = null;
                $submission->published            = null;
                $submission->feedbackauthor       = null;
                $submission->feedbackauthorformat = editors_get_preferred_format();
                $submission->timecreated          = time();
                $submission->timemodified         = time();
                $submission->title                = get_string('nosubmissionrequired_title', 'workshep', $workshep->name);
                $submission->content              = get_string('nosubmissionrequired_content', 'workshep', $template);
                $submission->contentformat        = FORMAT_HTML; // updated later
                $submission->contenttrust         = 0;           // updated later
                $submission->late                 = 0x0;         // bit mask

                // Event information.
                $params = array(
                    'context' => $workshep->context,
                    'courseid' => $workshep->course->id,
                    'other' => array(
                        'submissiontitle' => $submission->title
                    )
                );

                $submission->id = $DB->insert_record('workshep_submissions', $submission);
                $params['objectid'] = $submission->id;
                $event = \mod_workshep\event\submission_created::create($params);
                $event->trigger();
            }
        }
    }
}
