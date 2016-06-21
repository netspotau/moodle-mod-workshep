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
 * @package   mod_workshep
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_workshep_activity_task
 */

/**
 * Structure step to restore one workshep activity
 */
class restore_workshep_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();

        $userinfo = $this->get_setting_value('userinfo'); // are we including userinfo?

        ////////////////////////////////////////////////////////////////////////
        // XML interesting paths - non-user data
        ////////////////////////////////////////////////////////////////////////

        // root element describing workshep instance
        $workshep = new restore_path_element('workshep', '/activity/workshep');
        $paths[] = $workshep;

        // Apply for 'workshepform' subplugins optional paths at workshep level
        $this->add_subplugin_structure('workshepform', $workshep);

        // Apply for 'workshepeval' subplugins optional paths at workshep level
        $this->add_subplugin_structure('workshepeval', $workshep);

        // example submissions
        $paths[] = new restore_path_element('workshep_examplesubmission',
                       '/activity/workshep/examplesubmissions/examplesubmission');

        // reference assessment of the example submission
        $referenceassessment = new restore_path_element('workshep_referenceassessment',
                                   '/activity/workshep/examplesubmissions/examplesubmission/referenceassessment');
        $paths[] = $referenceassessment;

        // Apply for 'workshepform' subplugins optional paths at referenceassessment level
        $this->add_subplugin_structure('workshepform', $referenceassessment);

        // End here if no-user data has been selected
        if (!$userinfo) {
            return $this->prepare_activity_structure($paths);
        }

        ////////////////////////////////////////////////////////////////////////
        // XML interesting paths - user data
        ////////////////////////////////////////////////////////////////////////

        // assessments of example submissions
        $exampleassessment = new restore_path_element('workshep_exampleassessment',
                                 '/activity/workshep/examplesubmissions/examplesubmission/exampleassessments/exampleassessment');
        $paths[] = $exampleassessment;

        // Apply for 'workshepform' subplugins optional paths at exampleassessment level
        $this->add_subplugin_structure('workshepform', $exampleassessment);

        // submissions
        $paths[] = new restore_path_element('workshep_submission', '/activity/workshep/submissions/submission');

        // allocated assessments
        $assessment = new restore_path_element('workshep_assessment',
                          '/activity/workshep/submissions/submission/assessments/assessment');
        $paths[] = $assessment;

        // Apply for 'workshepform' subplugins optional paths at assessment level
        $this->add_subplugin_structure('workshepform', $assessment);

        // aggregations of grading grades in this workshep
        $paths[] = new restore_path_element('workshep_aggregation', '/activity/workshep/aggregations/aggregation');

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_workshep($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->submissionstart = $this->apply_date_offset($data->submissionstart);
        $data->submissionend = $this->apply_date_offset($data->submissionend);
        $data->assessmentstart = $this->apply_date_offset($data->assessmentstart);
        $data->assessmentend = $this->apply_date_offset($data->assessmentend);

        // insert the workshep record
        $newitemid = $DB->insert_record('workshep', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_workshep_examplesubmission($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->workshepid = $this->get_new_parentid('workshep');
        $data->example = 1;
        $data->authorid = $this->task->get_userid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('workshep_submissions', $data);
        $this->set_mapping('workshep_examplesubmission', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_workshep_referenceassessment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->submissionid = $this->get_new_parentid('workshep_examplesubmission');
        $data->reviewerid = $this->task->get_userid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('workshep_assessments', $data);
        $this->set_mapping('workshep_referenceassessment', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_workshep_exampleassessment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->submissionid = $this->get_new_parentid('workshep_examplesubmission');
        $data->reviewerid = $this->get_mappingid('user', $data->reviewerid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('workshep_assessments', $data);
        $this->set_mapping('workshep_exampleassessment', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_workshep_submission($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->workshepid = $this->get_new_parentid('workshep');
        $data->example = 0;
        $data->authorid = $this->get_mappingid('user', $data->authorid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('workshep_submissions', $data);
        $this->set_mapping('workshep_submission', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_workshep_assessment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->submissionid = $this->get_new_parentid('workshep_submission');
        $data->reviewerid = $this->get_mappingid('user', $data->reviewerid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('workshep_assessments', $data);
        $this->set_mapping('workshep_assessment', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_workshep_aggregation($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->workshepid = $this->get_new_parentid('workshep');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timegraded = $this->apply_date_offset($data->timegraded);

        $newitemid = $DB->insert_record('workshep_aggregations', $data);
    }

    protected function after_execute() {
        // Add workshep related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_workshep', 'intro', null);
        $this->add_related_files('mod_workshep', 'instructauthors', null);
        $this->add_related_files('mod_workshep', 'instructreviewers', null);
        $this->add_related_files('mod_workshep', 'conclusion', null);

        // Add example submission related files, matching by 'workshep_examplesubmission' itemname
        $this->add_related_files('mod_workshep', 'submission_content', 'workshep_examplesubmission');
        $this->add_related_files('mod_workshep', 'submission_attachment', 'workshep_examplesubmission');

        // Add reference assessment related files, matching by 'workshep_referenceassessment' itemname
        $this->add_related_files('mod_workshep', 'overallfeedback_content', 'workshep_referenceassessment');
        $this->add_related_files('mod_workshep', 'overallfeedback_attachment', 'workshep_referenceassessment');

        // Add example assessment related files, matching by 'workshep_exampleassessment' itemname
        $this->add_related_files('mod_workshep', 'overallfeedback_content', 'workshep_exampleassessment');
        $this->add_related_files('mod_workshep', 'overallfeedback_attachment', 'workshep_exampleassessment');

        // Add submission related files, matching by 'workshep_submission' itemname
        $this->add_related_files('mod_workshep', 'submission_content', 'workshep_submission');
        $this->add_related_files('mod_workshep', 'submission_attachment', 'workshep_submission');

        // Add assessment related files, matching by 'workshep_assessment' itemname
        $this->add_related_files('mod_workshep', 'overallfeedback_content', 'workshep_assessment');
        $this->add_related_files('mod_workshep', 'overallfeedback_attachment', 'workshep_assessment');
    }
}
