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
 * Provides the {@link workshepform_rubric_privacy_provider_testcase} class.
 *
 * @package     workshepform_rubric
 * @category    test
 * @copyright   2018 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

use core_privacy\local\request\writer;

/**
 * Unit tests for the privacy API implementation.
 *
 * @copyright 2018 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workshepform_rubric_privacy_provider_testcase extends advanced_testcase {

    /**
     * Test {@link workshepform_rubric\privacy\provider::export_assessment_form()} implementation.
     */
    public function test_export_assessment_form() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->generator = $this->getDataGenerator();
        $this->workshepgenerator = $this->generator->get_plugin_generator('mod_workshep');

        $this->course1 = $this->generator->create_course();

        $this->workshep11 = $this->generator->create_module('workshep', [
            'course' => $this->course1,
            'name' => 'Workshop11',
        ]);
        $DB->set_field('workshep', 'phase', 50, ['id' => $this->workshep11->id]);

        $this->dim1 = $DB->insert_record('workshepform_rubric', [
            'workshepid' => $this->workshep11->id,
            'sort' => 1,
            'description' => 'Criterion 1 description',
            'descriptionformat' => FORMAT_MARKDOWN,
        ]);

        $DB->insert_record('workshepform_rubric_levels', [
            'dimensionid' => $this->dim1,
            'grade' => 0,
            'definition' => 'Missing',
            'definitionformat' => FORMAT_PLAIN,
        ]);

        $DB->insert_record('workshepform_rubric_levels', [
            'dimensionid' => $this->dim1,
            'grade' => 1,
            'definition' => 'Poor',
            'definitionformat' => FORMAT_PLAIN,
        ]);

        $DB->insert_record('workshepform_rubric_levels', [
            'dimensionid' => $this->dim1,
            'grade' => 2,
            'definition' => 'Good',
            'definitionformat' => FORMAT_PLAIN,
        ]);

        $this->dim2 = $DB->insert_record('workshepform_rubric', [
            'workshepid' => $this->workshep11->id,
            'sort' => 2,
            'description' => 'Criterion 2 description',
            'descriptionformat' => FORMAT_MARKDOWN,
        ]);

        $DB->insert_record('workshepform_rubric_levels', [
            'dimensionid' => $this->dim2,
            'grade' => 0,
            'definition' => 'Missing',
            'definitionformat' => FORMAT_PLAIN,
        ]);

        $DB->insert_record('workshepform_rubric_levels', [
            'dimensionid' => $this->dim2,
            'grade' => 5,
            'definition' => 'Great',
            'definitionformat' => FORMAT_PLAIN,
        ]);

        $this->student1 = $this->generator->create_user();
        $this->student2 = $this->generator->create_user();

        $this->submission111 = $this->workshepgenerator->create_submission($this->workshep11->id, $this->student1->id);

        $this->assessment1112 = $this->workshepgenerator->create_assessment($this->submission111, $this->student2->id, [
            'grade' => 92,
        ]);

        $DB->insert_record('workshep_grades', [
            'assessmentid' => $this->assessment1112,
            'strategy' => 'rubric',
            'dimensionid' => $this->dim1,
            'grade' => 1,
            'peercomment' => '',
            'peercommentformat' => FORMAT_PLAIN,
        ]);

        $DB->insert_record('workshep_grades', [
            'assessmentid' => $this->assessment1112,
            'strategy' => 'rubric',
            'dimensionid' => $this->dim2,
            'grade' => 5,
            'peercomment' => '',
            'peercommentformat' => FORMAT_PLAIN,
        ]);

        $contextlist = new \core_privacy\local\request\approved_contextlist($this->student2, 'mod_workshep', [
            \context_module::instance($this->workshep11->cmid)->id,
        ]);

        \mod_workshep\privacy\provider::export_user_data($contextlist);

        $writer = writer::with_context(\context_module::instance($this->workshep11->cmid));

        $form = $writer->get_data([
            get_string('myassessments', 'mod_workshep'),
            $this->assessment1112,
            get_string('assessmentform', 'mod_workshep'),
            get_string('pluginname', 'workshepform_rubric'),
        ]);

        $this->assertEquals('Criterion 1 description', $form->criteria[0]->description);
        $this->assertEquals(3, count($form->criteria[0]->levels));
        $this->assertEquals(2, count($form->criteria[1]->levels));
        $this->assertEquals(5, $form->criteria[1]->grade);
    }
}
