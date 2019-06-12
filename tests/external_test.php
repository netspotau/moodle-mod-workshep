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
 * Workshop module external functions tests
 *
 * @package    mod_workshep
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.4
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/workshep/lib.php');

use mod_workshep\external\workshep_summary_exporter;
use mod_workshep\external\submission_exporter;

/**
 * Workshop module external functions tests
 *
 * @package    mod_workshep
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.4
 */
class mod_workshep_external_testcase extends externallib_advanced_testcase {

    /** @var stdClass course object */
    private $course;
    /** @var stdClass workshep object */
    private $workshep;
    /** @var stdClass context object */
    private $context;
    /** @var stdClass cm object */
    private $cm;
    /** @var stdClass student object */
    private $student;
    /** @var stdClass teacher object */
    private $teacher;
    /** @var stdClass student role object */
    private $studentrole;
    /** @var stdClass teacher role object */
    private $teacherrole;

    /**
     * Set up for every test
     */
    public function setUp() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $course = new stdClass();
        $course->groupmode = SEPARATEGROUPS;
        $course->groupmodeforce = true;
        $this->course = $this->getDataGenerator()->create_course($course);
        $this->workshep = $this->getDataGenerator()->create_module('workshep',
            array(
                'course' => $this->course->id,
                'overallfeedbackfiles' => 1,
            )
        );
        $this->context = context_module::instance($this->workshep->cmid);
        $this->cm = get_coursemodule_from_instance('workshep', $this->workshep->id);

        // Add grading strategy data (accumulative is the default).
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $strategy = $workshep->grading_strategy_instance();
        $data = array();
        for ($i = 0; $i < 4; $i++) {
            $data['dimensionid__idx_'.$i] = 0;
            $data['description__idx_'.$i.'_editor'] = array('text' => "Content $i", 'format' => FORMAT_MOODLE);
            $data['grade__idx_'.$i] = 25;
            $data['weight__idx_'.$i] = 25;
        }
        $data['workshepid'] = $workshep->id;
        $data['norepeats'] = 4;
        $data['config_algorithm'] = 0;
        $strategy->save_edit_strategy_form((object) $data);

        // Create users.
        $this->student = self::getDataGenerator()->create_user();
        $this->anotherstudentg1 = self::getDataGenerator()->create_user();
        $this->anotherstudentg2 = self::getDataGenerator()->create_user();
        $this->teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $this->studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, $this->studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($this->anotherstudentg1->id, $this->course->id, $this->studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($this->anotherstudentg2->id, $this->course->id, $this->studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $this->teacherrole->id, 'manual');

        $this->group1 = $this->getDataGenerator()->create_group(array('courseid' => $this->course->id));
        $this->group2 = $this->getDataGenerator()->create_group(array('courseid' => $this->course->id));
        groups_add_member($this->group1, $this->student);
        groups_add_member($this->group1, $this->anotherstudentg1);
        groups_add_member($this->group2, $this->anotherstudentg2);
    }

    /**
     * Test test_mod_workshep_get_worksheps_by_courses
     */
    public function test_mod_workshep_get_worksheps_by_courses() {

        // Create additional course.
        $course2 = self::getDataGenerator()->create_course();

        // Second workshep.
        $record = new stdClass();
        $record->course = $course2->id;
        $workshep2 = self::getDataGenerator()->create_module('workshep', $record);

        // Execute real Moodle enrolment as we'll call unenrol() method on the instance later.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $this->student->id, $this->studentrole->id);

        self::setUser($this->student);

        $returndescription = mod_workshep_external::get_worksheps_by_courses_returns();

        // Create what we expect to be returned when querying the two courses.
        $properties = workshep_summary_exporter::read_properties_definition();
        $expectedfields = array_keys($properties);

        // Add expected coursemodule and data.
        $workshep1 = $this->workshep;
        $workshep1->coursemodule = $workshep1->cmid;
        $workshep1->introformat = 1;
        $workshep1->introfiles = [];
        $workshep1->instructauthorsfiles = [];
        $workshep1->instructauthorsformat = 1;
        $workshep1->instructreviewersfiles = [];
        $workshep1->instructreviewersformat = 1;
        $workshep1->conclusionfiles = [];
        $workshep1->conclusionformat = 1;

        $workshep2->coursemodule = $workshep2->cmid;
        $workshep2->introformat = 1;
        $workshep2->introfiles = [];
        $workshep2->instructauthorsfiles = [];
        $workshep2->instructauthorsformat = 1;
        $workshep2->instructreviewersfiles = [];
        $workshep2->instructreviewersformat = 1;
        $workshep2->conclusionfiles = [];
        $workshep2->conclusionformat = 1;

        foreach ($expectedfields as $field) {
            if (!empty($properties[$field]) && $properties[$field]['type'] == PARAM_BOOL) {
                $workshep1->{$field} = (bool) $workshep1->{$field};
                $workshep2->{$field} = (bool) $workshep2->{$field};
            }
            $expected1[$field] = $workshep1->{$field};
            $expected2[$field] = $workshep2->{$field};
        }

        $expectedworksheps = array($expected2, $expected1);

        // Call the external function passing course ids.
        $result = mod_workshep_external::get_worksheps_by_courses(array($course2->id, $this->course->id));
        $result = external_api::clean_returnvalue($returndescription, $result);

        $this->assertEquals($expectedworksheps, $result['worksheps']);
        $this->assertCount(0, $result['warnings']);

        // Call the external function without passing course id.
        $result = mod_workshep_external::get_worksheps_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedworksheps, $result['worksheps']);
        $this->assertCount(0, $result['warnings']);

        // Unenrol user from second course and alter expected worksheps.
        $enrol->unenrol_user($instance2, $this->student->id);
        array_shift($expectedworksheps);

        // Call the external function without passing course id.
        $result = mod_workshep_external::get_worksheps_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedworksheps, $result['worksheps']);

        // Call for the second course we unenrolled the user from, expected warning.
        $result = mod_workshep_external::get_worksheps_by_courses(array($course2->id));
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('1', $result['warnings'][0]['warningcode']);
        $this->assertEquals($course2->id, $result['warnings'][0]['itemid']);
    }

    /**
     * Test mod_workshep_get_workshep_access_information for students.
     */
    public function test_mod_workshep_get_workshep_access_information_student() {

        self::setUser($this->student);
        $result = mod_workshep_external::get_workshep_access_information($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_workshep_access_information_returns(), $result);
        // Check default values for capabilities.
        $enabledcaps = array('canpeerassess', 'cansubmit', 'canview', 'canviewauthornames', 'canviewauthorpublished',
            'canviewpublishedsubmissions', 'canexportsubmissions');

        foreach ($result as $capname => $capvalue) {
            if (strpos($capname, 'can') !== 0) {
                continue;
            }
            if (in_array($capname, $enabledcaps)) {
                $this->assertTrue($capvalue);
            } else {
                $this->assertFalse($capvalue);
            }
        }
        // Now, unassign some capabilities.
        unassign_capability('mod/workshep:peerassess', $this->studentrole->id);
        unassign_capability('mod/workshep:submit', $this->studentrole->id);
        unset($enabledcaps[0]);
        unset($enabledcaps[1]);
        accesslib_clear_all_caches_for_unit_testing();

        $result = mod_workshep_external::get_workshep_access_information($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_workshep_access_information_returns(), $result);
        foreach ($result as $capname => $capvalue) {
            if (strpos($capname, 'can') !== 0) {
                continue;
            }
            if (in_array($capname, $enabledcaps)) {
                $this->assertTrue($capvalue);
            } else {
                $this->assertFalse($capvalue);
            }
        }

        // Now, specific functionalities.
        $this->assertFalse($result['creatingsubmissionallowed']);
        $this->assertFalse($result['modifyingsubmissionallowed']);
        $this->assertFalse($result['assessingallowed']);
        $this->assertFalse($result['assessingexamplesallowed']);
        $this->assertTrue($result['examplesassessedbeforesubmission']);
        $this->assertTrue($result['examplesassessedbeforeassessment']);

        // Switch phase.
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_SUBMISSION);
        $result = mod_workshep_external::get_workshep_access_information($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_workshep_access_information_returns(), $result);

        $this->assertTrue($result['creatingsubmissionallowed']);
        $this->assertTrue($result['modifyingsubmissionallowed']);
        $this->assertFalse($result['assessingallowed']);
        $this->assertFalse($result['assessingexamplesallowed']);
        $this->assertTrue($result['examplesassessedbeforesubmission']);
        $this->assertTrue($result['examplesassessedbeforeassessment']);

        // Switch to next (to assessment).
        $workshep->switch_phase(workshep::PHASE_ASSESSMENT);
        $result = mod_workshep_external::get_workshep_access_information($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_workshep_access_information_returns(), $result);

        $this->assertFalse($result['creatingsubmissionallowed']);
        $this->assertFalse($result['modifyingsubmissionallowed']);
        $this->assertTrue($result['assessingallowed']);
        $this->assertFalse($result['assessingexamplesallowed']);
        $this->assertTrue($result['examplesassessedbeforesubmission']);
        $this->assertTrue($result['examplesassessedbeforeassessment']);
    }

    /**
     * Test mod_workshep_get_workshep_access_information for teachers.
     */
    public function test_mod_workshep_get_workshep_access_information_teacher() {

        self::setUser($this->teacher);
        $result = mod_workshep_external::get_workshep_access_information($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_workshep_access_information_returns(), $result);
        // Check default values.
        $disabledcaps = array('canpeerassess', 'cansubmit');

        foreach ($result as $capname => $capvalue) {
            if (strpos($capname, 'can') !== 0) {
                continue;
            }
            if (in_array($capname, $disabledcaps)) {
                $this->assertFalse($capvalue);
            } else {
                $this->assertTrue($capvalue);
            }
        }

        // Now, specific functionalities.
        $this->assertTrue($result['creatingsubmissionallowed']); // Allow because of submit on behalf of others process.
        $this->assertTrue($result['modifyingsubmissionallowed']); // Allow because of submit on behalf of others process.
        $this->assertFalse($result['assessingallowed']);
        $this->assertFalse($result['assessingexamplesallowed']);
    }

    /**
     * Test mod_workshep_get_user_plan for students.
     */
    public function test_mod_workshep_get_user_plan_student() {

        self::setUser($this->student);
        $result = mod_workshep_external::get_user_plan($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_user_plan_returns(), $result);

        $this->assertCount(0, $result['userplan']['examples']);  // No examples given.
        $this->assertCount(5, $result['userplan']['phases']);  // Always 5 phases.
        $this->assertEquals(workshep::PHASE_SETUP, $result['userplan']['phases'][0]['code']);  // First phase always setup.
        $this->assertTrue($result['userplan']['phases'][0]['active']); // First phase "Setup" active in new worksheps.

        // Switch phase.
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_SUBMISSION);

        $result = mod_workshep_external::get_user_plan($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_user_plan_returns(), $result);

        $this->assertEquals(workshep::PHASE_SUBMISSION, $result['userplan']['phases'][1]['code']);
        $this->assertTrue($result['userplan']['phases'][1]['active']); // We are now in submission phase.
    }

    /**
     * Test mod_workshep_get_user_plan for teachers.
     */
    public function test_mod_workshep_get_user_plan_teacher() {

        self::setUser($this->teacher);
        $result = mod_workshep_external::get_user_plan($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_user_plan_returns(), $result);

        $this->assertCount(0, $result['userplan']['examples']);  // No examples given.
        $this->assertCount(5, $result['userplan']['phases']);  // Always 5 phases.
        $this->assertEquals(workshep::PHASE_SETUP, $result['userplan']['phases'][0]['code']);  // First phase always setup.
        $this->assertTrue($result['userplan']['phases'][0]['active']); // First phase "Setup" active in new worksheps.
        $this->assertCount(4, $result['userplan']['phases'][0]['tasks']);  // For new empty worksheps, always 4 tasks.

        foreach ($result['userplan']['phases'][0]['tasks'] as $task) {
            if ($task['code'] == 'intro' || $task['code'] == 'instructauthors' || $task['code'] == 'editform') {
                $this->assertEquals(1, $task['completed']);
            } else {
                $this->assertEmpty($task['completed']);
            }
        }

        // Do some of the tasks asked - switch phase.
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_SUBMISSION);

        $result = mod_workshep_external::get_user_plan($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_user_plan_returns(), $result);
        foreach ($result['userplan']['phases'][0]['tasks'] as $task) {
            if ($task['code'] == 'intro' || $task['code'] == 'instructauthors' || $task['code'] == 'editform' ||
                    $task['code'] == 'switchtonextphase') {
                $this->assertEquals(1, $task['completed']);
            } else {
                $this->assertEmpty($task['completed']);
            }
        }

        $result = mod_workshep_external::get_user_plan($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_user_plan_returns(), $result);

        $this->assertEquals(workshep::PHASE_SUBMISSION, $result['userplan']['phases'][1]['code']);
        $this->assertTrue($result['userplan']['phases'][1]['active']); // We are now in submission phase.
    }

    /**
     * Test test_view_workshep invalid id.
     */
    public function test_view_workshep_invalid_id() {
        $this->expectException('moodle_exception');
        mod_workshep_external::view_workshep(0);
    }

    /**
     * Test test_view_workshep user not enrolled.
     */
    public function test_view_workshep_user_not_enrolled() {
        // Test not-enrolled user.
        $usernotenrolled = self::getDataGenerator()->create_user();
        $this->setUser($usernotenrolled);
        $this->expectException('moodle_exception');
        mod_workshep_external::view_workshep($this->workshep->id);
    }

    /**
     * Test test_view_workshep user student.
     */
    public function test_view_workshep_user_student() {
        // Test user with full capabilities.
        $this->setUser($this->student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_workshep_external::view_workshep($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::view_workshep_returns(), $result);
        $this->assertTrue($result['status']);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_workshep\event\course_module_viewed', $event);
        $this->assertEquals($this->context, $event->get_context());
        $moodleworkshep = new \moodle_url('/mod/workshep/view.php', array('id' => $this->cm->id));
        $this->assertEquals($moodleworkshep, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test test_view_workshep user missing capabilities.
     */
    public function test_view_workshep_user_missing_capabilities() {
        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        assign_capability('mod/workshep:view', CAP_PROHIBIT, $this->studentrole->id, $this->context->id);
        // Empty all the caches that may be affected  by this change.
        accesslib_clear_all_caches_for_unit_testing();
        course_modinfo::clear_instance_cache();

        $this->setUser($this->student);
        $this->expectException('moodle_exception');
        mod_workshep_external::view_workshep($this->workshep->id);
    }

    /**
     * Test test_add_submission.
     */
    public function test_add_submission() {
        $fs = get_file_storage();

        // Test user with full capabilities.
        $this->setUser($this->student);

        $title = 'Submission title';
        $content = 'Submission contents';

        // Create a file in a draft area for inline attachments.
        $draftidinlineattach = file_get_unused_draft_itemid();
        $usercontext = context_user::instance($this->student->id);
        $filenameimg = 'shouldbeanimage.txt';
        $filerecordinline = array(
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => $draftidinlineattach,
            'filepath'  => '/',
            'filename'  => $filenameimg,
        );
        $fs->create_file_from_string($filerecordinline, 'image contents (not really)');

        // Create a file in a draft area for regular attachments.
        $draftidattach = file_get_unused_draft_itemid();
        $filerecordattach = $filerecordinline;
        $attachfilename = 'attachment.txt';
        $filerecordattach['filename'] = $attachfilename;
        $filerecordattach['itemid'] = $draftidattach;
        $fs->create_file_from_string($filerecordattach, 'simple text attachment');

        // Switch to submission phase.
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_SUBMISSION);

        $result = mod_workshep_external::add_submission($this->workshep->id, $title, $content, FORMAT_MOODLE, $draftidinlineattach,
            $draftidattach);
        $result = external_api::clean_returnvalue(mod_workshep_external::add_submission_returns(), $result);
        $this->assertEmpty($result['warnings']);

        // Check submission created.
        $submission = $workshep->get_submission_by_author($this->student->id);
        $this->assertTrue($result['status']);
        $this->assertEquals($result['submissionid'], $submission->id);
        $this->assertEquals($title, $submission->title);
        $this->assertEquals($content, $submission->content);

        // Check files.
        $contentfiles = $fs->get_area_files($this->context->id, 'mod_workshep', 'submission_content', $submission->id);
        $this->assertCount(2, $contentfiles);
        foreach ($contentfiles as $file) {
            if ($file->is_directory()) {
                continue;
            } else {
                $this->assertEquals($filenameimg, $file->get_filename());
            }
        }
        $contentfiles = $fs->get_area_files($this->context->id, 'mod_workshep', 'submission_attachment', $submission->id);
        $this->assertCount(2, $contentfiles);
        foreach ($contentfiles as $file) {
            if ($file->is_directory()) {
                continue;
            } else {
                $this->assertEquals($attachfilename, $file->get_filename());
            }
        }
    }

    /**
     * Test test_add_submission invalid phase.
     */
    public function test_add_submission_invalid_phase() {
        $this->setUser($this->student);

        $this->expectException('moodle_exception');
        mod_workshep_external::add_submission($this->workshep->id, 'Test');
    }

    /**
     * Test test_add_submission empty title.
     */
    public function test_add_submission_empty_title() {
        $this->setUser($this->student);

        // Switch to submission phase.
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_SUBMISSION);

        $this->expectException('moodle_exception');
        mod_workshep_external::add_submission($this->workshep->id, '');
    }

    /**
     * Test test_add_submission already added.
     */
    public function test_add_submission_already_added() {
        $this->setUser($this->student);

        // Switch to submission phase.
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_SUBMISSION);

        // Create the submission.
        $result = mod_workshep_external::add_submission($this->workshep->id, 'My submission');
        $result = external_api::clean_returnvalue(mod_workshep_external::add_submission_returns(), $result);

        // Try to create it again.
        $result = mod_workshep_external::add_submission($this->workshep->id, 'My submission');
        $result = external_api::clean_returnvalue(mod_workshep_external::add_submission_returns(), $result);
        $this->assertFalse($result['status']);
        $this->assertArrayNotHasKey('submissionid', $result);
        $this->assertCount(1, $result['warnings']); // BASE-2801: Multiple submissions per user are not allowed.
        $this->assertEquals('fielderror', $result['warnings'][0]['warningcode']);
        $this->assertEquals('title', $result['warnings'][0]['item']);
    }

    /**
     * Helper method to create a submission for testing for the given user.
     *
     * @param int $user the submission will be created by this student.
     * @return int the submission id
     */
    protected function create_test_submission($user) {
        // Test user with full capabilities.
        $this->setUser($user);

        $title = 'Submission title';
        $content = 'Submission contents';

        // Create a file in a draft area for inline attachments.
        $fs = get_file_storage();
        $draftidinlineattach = file_get_unused_draft_itemid();
        $usercontext = context_user::instance($this->student->id);
        $filenameimg = 'shouldbeanimage.txt';
        $filerecordinline = array(
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => $draftidinlineattach,
            'filepath'  => '/',
            'filename'  => $filenameimg,
        );
        $fs->create_file_from_string($filerecordinline, 'image contents (not really)');

        // Create a file in a draft area for regular attachments.
        $draftidattach = file_get_unused_draft_itemid();
        $filerecordattach = $filerecordinline;
        $attachfilename = 'attachment.txt';
        $filerecordattach['filename'] = $attachfilename;
        $filerecordattach['itemid'] = $draftidattach;
        $fs->create_file_from_string($filerecordattach, 'simple text attachment');

        // Switch to submission phase.
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_SUBMISSION);

        $result = mod_workshep_external::add_submission($this->workshep->id, $title, $content, FORMAT_MOODLE, $draftidinlineattach,
            $draftidattach);
        return $result['submissionid'];
    }

    /**
     * Test test_update_submission.
     */
    public function test_update_submission() {

        // Create the submission that will be updated.
        $submissionid = $this->create_test_submission($this->student);

        // Test user with full capabilities.
        $this->setUser($this->student);

        $title = 'Submission new title';
        $content = 'Submission new contents';

        // Create a different file in a draft area for inline attachments.
        $fs = get_file_storage();
        $draftidinlineattach = file_get_unused_draft_itemid();
        $usercontext = context_user::instance($this->student->id);
        $filenameimg = 'shouldbeanimage_new.txt';
        $filerecordinline = array(
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => $draftidinlineattach,
            'filepath'  => '/',
            'filename'  => $filenameimg,
        );
        $fs->create_file_from_string($filerecordinline, 'image contents (not really)');

        // Create a different file in a draft area for regular attachments.
        $draftidattach = file_get_unused_draft_itemid();
        $filerecordattach = $filerecordinline;
        $attachfilename = 'attachment_new.txt';
        $filerecordattach['filename'] = $attachfilename;
        $filerecordattach['itemid'] = $draftidattach;
        $fs->create_file_from_string($filerecordattach, 'simple text attachment');

        $result = mod_workshep_external::update_submission($submissionid, $title, $content, FORMAT_MOODLE, $draftidinlineattach,
            $draftidattach);
        $result = external_api::clean_returnvalue(mod_workshep_external::update_submission_returns(), $result);
        $this->assertEmpty($result['warnings']);

        // Check submission updated.
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $submission = $workshep->get_submission_by_id($submissionid);
        $this->assertTrue($result['status']);
        $this->assertEquals($title, $submission->title);
        $this->assertEquals($content, $submission->content);

        // Check files.
        $contentfiles = $fs->get_area_files($this->context->id, 'mod_workshep', 'submission_content', $submission->id);
        $this->assertCount(2, $contentfiles);
        foreach ($contentfiles as $file) {
            if ($file->is_directory()) {
                continue;
            } else {
                $this->assertEquals($filenameimg, $file->get_filename());
            }
        }
        $contentfiles = $fs->get_area_files($this->context->id, 'mod_workshep', 'submission_attachment', $submission->id);
        $this->assertCount(2, $contentfiles);
        foreach ($contentfiles as $file) {
            if ($file->is_directory()) {
                continue;
            } else {
                $this->assertEquals($attachfilename, $file->get_filename());
            }
        }
    }

    /**
     * Test test_update_submission belonging to other user.
     */
    public function test_update_submission_of_other_user() {
        // Create the submission that will be updated.
        $submissionid = $this->create_test_submission($this->student);

        $this->setUser($this->teacher);

        $this->expectException('moodle_exception');
        mod_workshep_external::update_submission($submissionid, 'Test');
    }

    /**
     * Test test_update_submission invalid phase.
     */
    public function test_update_submission_invalid_phase() {
        // Create the submission that will be updated.
        $submissionid = $this->create_test_submission($this->student);

        $this->setUser($this->student);

        // Switch to assessment phase.
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_ASSESSMENT);

        $this->expectException('moodle_exception');
        mod_workshep_external::update_submission($submissionid, 'Test');
    }

    /**
     * Test test_update_submission empty title.
     */
    public function test_update_submission_empty_title() {
        // Create the submission that will be updated.
        $submissionid = $this->create_test_submission($this->student);

        $this->setUser($this->student);

        $this->expectException('moodle_exception');
        mod_workshep_external::update_submission($submissionid, '');
    }

    /**
     * Test test_delete_submission.
     */
    public function test_delete_submission() {

        // Create the submission that will be deleted.
        $submissionid = $this->create_test_submission($this->student);

        $this->setUser($this->student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_workshep_external::delete_submission($submissionid);
        $result = external_api::clean_returnvalue(mod_workshep_external::delete_submission_returns(), $result);
        $this->assertEmpty($result['warnings']);
        $this->assertTrue($result['status']);
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $submission = $workshep->get_submission_by_author($this->student->id);
        $this->assertFalse($submission);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking event.
        $this->assertInstanceOf('\mod_workshep\event\submission_deleted', $event);
        $this->assertEquals($this->context, $event->get_context());
    }

    /**
     * Test test_delete_submission_with_assessments.
     */
    public function test_delete_submission_with_assessments() {

        // Create the submission that will be deleted.
        $submissionid = $this->create_test_submission($this->student);

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $workshepgenerator->create_assessment($submissionid, $this->teacher->id, array(
            'weight' => 3,
            'grade' => 95.00000,
        ));

        $this->setUser($this->student);
        $this->expectException('moodle_exception');
        mod_workshep_external::delete_submission($submissionid);
    }

    /**
     * Test test_delete_submission_invalid_phase.
     */
    public function test_delete_submission_invalid_phase() {

        // Create the submission that will be deleted.
        $submissionid = $this->create_test_submission($this->student);

        // Switch to assessment phase.
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_ASSESSMENT);

        $this->setUser($this->student);
        $this->expectException('moodle_exception');
        mod_workshep_external::delete_submission($submissionid);
    }

    /**
     * Test test_delete_submission_as_teacher.
     */
    public function test_delete_submission_as_teacher() {

        // Create the submission that will be deleted.
        $submissionid = $this->create_test_submission($this->student);

        $this->setUser($this->teacher);
        $result = mod_workshep_external::delete_submission($submissionid);
        $result = external_api::clean_returnvalue(mod_workshep_external::delete_submission_returns(), $result);
        $this->assertEmpty($result['warnings']);
        $this->assertTrue($result['status']);
    }

    /**
     * Test test_delete_submission_other_user.
     */
    public function test_delete_submission_other_user() {

        $anotheruser = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($anotheruser->id, $this->course->id, $this->studentrole->id, 'manual');
        // Create the submission that will be deleted.
        $submissionid = $this->create_test_submission($this->student);

        $this->setUser($anotheruser);
        $this->expectException('moodle_exception');
        mod_workshep_external::delete_submission($submissionid);
    }

    /**
     * Test test_get_submissions_student.
     */
    public function test_get_submissions_student() {

        // Create a couple of submissions with files.
        $firstsubmissionid = $this->create_test_submission($this->student);  // Create submission with files.
        $secondsubmissionid = $this->create_test_submission($this->anotherstudentg1->id);

        $this->setUser($this->student);
        $result = mod_workshep_external::get_submissions($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submissions_returns(), $result);
        // We should get just our submission.
        $this->assertCount(1, $result['submissions']);
        $this->assertEquals(1, $result['totalcount']);
        $this->assertEquals($firstsubmissionid, $result['submissions'][0]['id']);
        $this->assertCount(1, $result['submissions'][0]['contentfiles']); // Check we retrieve submission text files.
        $this->assertCount(1, $result['submissions'][0]['attachmentfiles']); // Check we retrieve attachment files.
        // We shoul not see the grade or feedback information.
        $properties = submission_exporter::properties_definition();
        foreach ($properties as $attribute => $settings) {
            if (!empty($settings['optional'])) {
                if (isset($result['submissions'][0][$attribute])) {
                    echo "error $attribute";
                }
                $this->assertFalse(isset($result['submissions'][0][$attribute]));
            }
        }
    }

    /**
     * Test test_get_submissions_published_student.
     */
    public function test_get_submissions_published_student() {

        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_CLOSED);
        // Create a couple of submissions with files.
        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $submission = array('published' => 1);
        $submissionid = $workshepgenerator->create_submission($this->workshep->id, $this->anotherstudentg1->id, $submission);

        $this->setUser($this->student);
        $result = mod_workshep_external::get_submissions($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submissions_returns(), $result);
        // We should get just our submission.
        $this->assertCount(1, $result['submissions']);
        $this->assertEquals(1, $result['totalcount']);
        $this->assertEquals($submissionid, $result['submissions'][0]['id']);

        // Check with group restrictions.
        $this->setUser($this->anotherstudentg2);
        $result = mod_workshep_external::get_submissions($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submissions_returns(), $result);
        $this->assertCount(0, $result['submissions']);  // I can't see other users in separated groups.
        $this->assertEquals(0, $result['totalcount']);
    }

    /**
     * Test test_get_submissions_from_student_with_feedback_from_teacher.
     */
    public function test_get_submissions_from_student_with_feedback_from_teacher() {
        global $DB;

        // Create a couple of submissions with files.
        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $submissionid = $workshepgenerator->create_submission($this->workshep->id, $this->student->id);
        // Create teacher feedback for submission.
        $record = new stdclass();
        $record->id = $submissionid;
        $record->gradeover = 9;
        $record->gradeoverby = $this->teacher->id;
        $record->feedbackauthor = 'Hey';
        $record->feedbackauthorformat = FORMAT_MOODLE;
        $record->published = 1;
        $DB->update_record('workshep_submissions', $record);

        // Remove teacher caps.
        assign_capability('mod/workshep:viewallsubmissions', CAP_PROHIBIT, $this->teacher->id, $this->context->id);
        // Empty all the caches that may be affected  by this change.
        accesslib_clear_all_caches_for_unit_testing();
        course_modinfo::clear_instance_cache();

        $this->setUser($this->teacher);
        $result = mod_workshep_external::get_submissions($this->workshep->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submissions_returns(), $result);
        // We should get just our submission.
        $this->assertEquals(1, $result['totalcount']);
        $this->assertEquals($submissionid, $result['submissions'][0]['id']);
    }

    /**
     * Test test_get_submissions_from_students_as_teacher.
     */
    public function test_get_submissions_from_students_as_teacher() {

        // Create a couple of submissions with files.
        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $submissionid1 = $workshepgenerator->create_submission($this->workshep->id, $this->student->id);
        $submissionid2 = $workshepgenerator->create_submission($this->workshep->id, $this->anotherstudentg1->id);
        $submissionid3 = $workshepgenerator->create_submission($this->workshep->id, $this->anotherstudentg2->id);

        $this->setUser($this->teacher);
        $result = mod_workshep_external::get_submissions($this->workshep->id); // Get all.
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submissions_returns(), $result);
        $this->assertEquals(3, $result['totalcount']);
        $this->assertCount(3, $result['submissions']);

        $result = mod_workshep_external::get_submissions($this->workshep->id, 0, 0, 0, 2); // Check pagination.
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submissions_returns(), $result);
        $this->assertEquals(3, $result['totalcount']);
        $this->assertCount(2, $result['submissions']);

        $result = mod_workshep_external::get_submissions($this->workshep->id, 0, $this->group2->id); // Get group 2.
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submissions_returns(), $result);
        $this->assertEquals(1, $result['totalcount']);
        $this->assertCount(1, $result['submissions']);
        $this->assertEquals($submissionid3, $result['submissions'][0]['id']);

        $result = mod_workshep_external::get_submissions($this->workshep->id, $this->anotherstudentg1->id); // Get one.
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submissions_returns(), $result);
        $this->assertEquals(1, $result['totalcount']);
        $this->assertEquals($submissionid2, $result['submissions'][0]['id']);
    }

    /**
     * Test test_get_submission_student.
     */
    public function test_get_submission_student() {

        // Create a couple of submissions with files.
        $firstsubmissionid = $this->create_test_submission($this->student);  // Create submission with files.

        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_CLOSED);
        $this->setUser($this->student);
        $result = mod_workshep_external::get_submission($firstsubmissionid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submission_returns(), $result);
        $this->assertEquals($firstsubmissionid, $result['submission']['id']);
        $this->assertCount(1, $result['submission']['contentfiles']); // Check we retrieve submission text files.
        $this->assertCount(1, $result['submission']['attachmentfiles']); // Check we retrieve attachment files.
        $this->assertArrayHasKey('feedbackauthor', $result['submission']);
        $this->assertArrayNotHasKey('grade', $result['submission']);
        $this->assertArrayNotHasKey('gradeover', $result['submission']);
        $this->assertArrayHasKey('gradeoverby', $result['submission']);
        $this->assertArrayNotHasKey('timegraded', $result['submission']);

        // Switch to a different phase (where feedback won't be available).
        $workshep->switch_phase(workshep::PHASE_EVALUATION);
        $result = mod_workshep_external::get_submission($firstsubmissionid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submission_returns(), $result);
        $this->assertEquals($firstsubmissionid, $result['submission']['id']);
        $this->assertCount(1, $result['submission']['contentfiles']); // Check we retrieve submission text files.
        $this->assertCount(1, $result['submission']['attachmentfiles']); // Check we retrieve attachment files.
        $this->assertArrayNotHasKey('feedbackauthor', $result['submission']);
        $this->assertArrayNotHasKey('grade', $result['submission']);
        $this->assertArrayNotHasKey('gradeover', $result['submission']);
        $this->assertArrayNotHasKey('gradeoverby', $result['submission']);
        $this->assertArrayNotHasKey('timegraded', $result['submission']);
    }

    /**
     * Test test_get_submission_i_reviewed.
     */
    public function test_get_submission_i_reviewed() {

        // Create a couple of submissions with files.
        $firstsubmissionid = $this->create_test_submission($this->student);  // Create submission with files.
        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $workshepgenerator->create_assessment($firstsubmissionid, $this->anotherstudentg1->id, array(
            'weight' => 3,
            'grade' => 95,
        ));
        // Now try to get the submission I just reviewed.
        $this->setUser($this->anotherstudentg1);
        $result = mod_workshep_external::get_submission($firstsubmissionid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submission_returns(), $result);
        $this->assertEquals($firstsubmissionid, $result['submission']['id']);
        $this->assertCount(1, $result['submission']['contentfiles']); // Check we retrieve submission text files.
        $this->assertCount(1, $result['submission']['attachmentfiles']); // Check we retrieve attachment files.
        $this->assertArrayNotHasKey('feedbackauthor', $result['submission']);
        $this->assertArrayNotHasKey('grade', $result['submission']);
        $this->assertArrayNotHasKey('gradeover', $result['submission']);
        $this->assertArrayNotHasKey('gradeoverby', $result['submission']);
        $this->assertArrayNotHasKey('timegraded', $result['submission']);
    }

    /**
     * Test test_get_submission_other_student.
     */
    public function test_get_submission_other_student() {

        // Create a couple of submissions with files.
        $firstsubmissionid = $this->create_test_submission($this->student);  // Create submission with files.
        // Expect failure.
        $this->setUser($this->anotherstudentg1);
        $this->expectException('moodle_exception');
        $result = mod_workshep_external::get_submission($firstsubmissionid);
    }

    /**
     * Test test_get_submission_published_student.
     */
    public function test_get_submission_published_student() {

        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_CLOSED);
        // Create a couple of submissions with files.
        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $submission = array('published' => 1);
        $submissionid = $workshepgenerator->create_submission($this->workshep->id, $this->anotherstudentg1->id, $submission);

        $this->setUser($this->student);
        $result = mod_workshep_external::get_submission($submissionid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submission_returns(), $result);
        $this->assertEquals($submissionid, $result['submission']['id']);
        // Check that the student don't see the other student grade/feedback data even if is published.
        // We should not see the grade or feedback information.
        $properties = submission_exporter::properties_definition();
        $this->assertArrayNotHasKey('feedbackauthor', $result['submission']);
        $this->assertArrayNotHasKey('grade', $result['submission']);
        $this->assertArrayNotHasKey('gradeover', $result['submission']);
        $this->assertArrayNotHasKey('gradeoverby', $result['submission']);
        $this->assertArrayNotHasKey('timegraded', $result['submission']);

        // Check with group restrictions.
        $this->setUser($this->anotherstudentg2);
        $this->expectException('moodle_exception');
        mod_workshep_external::get_submission($submissionid);
    }

    /**
     * Test test_get_submission_from_student_with_feedback_from_teacher.
     */
    public function test_get_submission_from_student_with_feedback_from_teacher() {
        global $DB;

        // Create a couple of submissions with files.
        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $submissionid = $workshepgenerator->create_submission($this->workshep->id, $this->student->id);
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_CLOSED);
        // Create teacher feedback for submission.
        $record = new stdclass();
        $record->id = $submissionid;
        $record->gradeover = 9;
        $record->gradeoverby = $this->teacher->id;
        $record->feedbackauthor = 'Hey';
        $record->feedbackauthorformat = FORMAT_MOODLE;
        $record->published = 1;
        $record->timegraded = time();
        $DB->update_record('workshep_submissions', $record);

        $this->setUser($this->teacher);
        $result = mod_workshep_external::get_submission($submissionid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submission_returns(), $result);
        $this->assertEquals($submissionid, $result['submission']['id']);
        $this->assertEquals($record->feedbackauthor, $result['submission']['feedbackauthor']);
        $this->assertEquals($record->gradeover, $result['submission']['gradeover']);
        $this->assertEquals($record->gradeoverby, $result['submission']['gradeoverby']);
        $this->assertEquals($record->timegraded, $result['submission']['timegraded']);

        // Go to phase where feedback and grades are not yet available.
        $workshep->switch_phase(workshep::PHASE_SUBMISSION);
        $result = mod_workshep_external::get_submission($submissionid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submission_returns(), $result);
        $this->assertArrayNotHasKey('feedbackauthor', $result['submission']);
        $this->assertArrayNotHasKey('grade', $result['submission']);
        $this->assertArrayNotHasKey('gradeover', $result['submission']);
        $this->assertArrayNotHasKey('gradeoverby', $result['submission']);
        $this->assertArrayNotHasKey('timegraded', $result['submission']);

        // Remove teacher caps to view and go to valid phase.
        $workshep->switch_phase(workshep::PHASE_EVALUATION);
        unassign_capability('mod/workshep:viewallsubmissions', $this->teacherrole->id);
        // Empty all the caches that may be affected  by this change.
        accesslib_clear_all_caches_for_unit_testing();

        $this->expectException('moodle_exception');
        mod_workshep_external::get_submission($submissionid);
    }

    /**
     * Test test_get_submission_from_students_as_teacher.
     */
    public function test_get_submission_from_students_as_teacher() {
        // Create a couple of submissions with files.
        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $submissionid1 = $workshepgenerator->create_submission($this->workshep->id, $this->student->id);
        $submissionid2 = $workshepgenerator->create_submission($this->workshep->id, $this->anotherstudentg1->id);
        $submissionid3 = $workshepgenerator->create_submission($this->workshep->id, $this->anotherstudentg2->id);

        $this->setUser($this->teacher);
        $result = mod_workshep_external::get_submission($submissionid1); // Get all.
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submission_returns(), $result);
        $this->assertEquals($submissionid1, $result['submission']['id']);

        $result = mod_workshep_external::get_submission($submissionid3); // Get group 2.
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submission_returns(), $result);
        $this->assertEquals($submissionid3, $result['submission']['id']);
    }


    /**
     * Test get_submission_assessments_student.
     */
    public function test_get_submission_assessments_student() {

        // Create the submission that will be deleted.
        $submissionid = $this->create_test_submission($this->student);

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $workshepgenerator->create_assessment($submissionid, $this->anotherstudentg1->id, array(
            'weight' => 3,
            'grade' => 95,
        ));
        $workshepgenerator->create_assessment($submissionid, $this->student->id, array(
            'weight' => 2,
            'grade' => 90,
        ));

        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_CLOSED);
        $this->setUser($this->student);
        $result = mod_workshep_external::get_submission_assessments($submissionid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submission_assessments_returns(), $result);
        $this->assertCount(2, $result['assessments']);  // I received my two assessments.
        foreach ($result['assessments'] as $assessment) {
            if ($assessment['grade'] == 90) {
                // My own assessment, I can see me.
                $this->assertEquals($this->student->id, $assessment['reviewerid']);
            } else {
                // Student's can't see who did the review.
                $this->assertEquals(0, $assessment['reviewerid']);
            }
        }
    }

    /**
     * Test get_submission_assessments_invalid_phase.
     */
    public function test_get_submission_assessments_invalid_phase() {

        // Create the submission that will be deleted.
        $submissionid = $this->create_test_submission($this->student);

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $workshepgenerator->create_assessment($submissionid, $this->anotherstudentg1->id, array(
            'weight' => 3,
            'grade' => 95,
        ));

        $this->expectException('moodle_exception');
        mod_workshep_external::get_submission_assessments($submissionid);
    }

    /**
     * Test get_submission_assessments_teacher.
     */
    public function test_get_submission_assessments_teacher() {

        // Create the submission that will be deleted.
        $submissionid = $this->create_test_submission($this->student);

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $assessmentid = $workshepgenerator->create_assessment($submissionid, $this->anotherstudentg1->id, array(
            'weight' => 1,
            'grade' => 50,
        ));

        $this->setUser($this->teacher);
        $result = mod_workshep_external::get_submission_assessments($submissionid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_submission_assessments_returns(), $result);
        $this->assertCount(1, $result['assessments']);
        $this->assertEquals(50, $result['assessments'][0]['grade']);
        $this->assertEquals($assessmentid, $result['assessments'][0]['id']);
    }

    /**
     * Test get_assessment_author.
     */
    public function test_get_assessment_author() {

        // Create the submission.
        $submissionid = $this->create_test_submission($this->anotherstudentg1);

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $assessmentid = $workshepgenerator->create_assessment($submissionid, $this->student->id, array(
            'weight' => 2,
            'grade' => 90,
        ));

        // Switch to closed phase.
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_CLOSED);
        $this->setUser($this->anotherstudentg1);
        $result = mod_workshep_external::get_assessment($assessmentid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_assessment_returns(), $result);
        $this->assertEquals($assessmentid, $result['assessment']['id']);
        $this->assertEquals(90, $result['assessment']['grade']);
        // I can't see the reviewer review.
        $this->assertFalse(isset($result['assessment']['feedbackreviewer']));
    }

    /**
     * Test get_assessment_reviewer.
     */
    public function test_get_assessment_reviewer() {

        // Create the submission.
        $submissionid = $this->create_test_submission($this->anotherstudentg1);

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $assessmentid = $workshepgenerator->create_assessment($submissionid, $this->student->id, array(
            'weight' => 2,
            'grade' => 90,
        ));

        // Switch to closed phase.
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_CLOSED);
        $this->setUser($this->student);
        $result = mod_workshep_external::get_assessment($assessmentid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_assessment_returns(), $result);
        $this->assertEquals($assessmentid, $result['assessment']['id']);
        $this->assertEquals(90, $result['assessment']['grade']);
        // I can see the reviewer review.
        $this->assertTrue(isset($result['assessment']['feedbackreviewer']));
    }

    /**
     * Test get_assessment_teacher.
     */
    public function test_get_assessment_teacher() {

        // Create the submission.
        $submissionid = $this->create_test_submission($this->anotherstudentg1);

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $assessmentid = $workshepgenerator->create_assessment($submissionid, $this->student->id, array(
            'weight' => 2,
            'grade' => 90,
        ));

        // Switch to closed phase.
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_CLOSED);
        $this->setUser($this->teacher);
        $result = mod_workshep_external::get_assessment($assessmentid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_assessment_returns(), $result);
        $this->assertEquals($assessmentid, $result['assessment']['id']);
        $this->assertEquals(90, $result['assessment']['grade']);
    }

    /**
     * Test get_assessment_student_invalid_phase.
     */
    public function test_get_assessment_student_invalid_phase() {

        // Create the submission.
        $submissionid = $this->create_test_submission($this->anotherstudentg1);

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $assessmentid = $workshepgenerator->create_assessment($submissionid, $this->student->id, array(
            'weight' => 2,
            'grade' => 90,
        ));

        // Switch to closed phase.
        $this->setUser($this->anotherstudentg1);

        $this->expectException('moodle_exception');
        mod_workshep_external::get_assessment($assessmentid);
    }

    /**
     * Test get_assessment_student_invalid_user.
     */
    public function test_get_assessment_student_invalid_user() {

        // Create the submission.
        $submissionid = $this->create_test_submission($this->anotherstudentg1);

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $assessmentid = $workshepgenerator->create_assessment($submissionid, $this->student->id, array(
            'weight' => 2,
            'grade' => 90,
        ));

        // Switch to closed phase.
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_CLOSED);
        $this->setUser($this->anotherstudentg2);

        $this->expectException('moodle_exception');
        mod_workshep_external::get_assessment($assessmentid);
    }

    /**
     * Test get_assessment_form_definition_reviewer_new_assessment.
     */
    public function test_get_assessment_form_definition_reviewer_new_assessment() {

        // Create the submission.
        $submissionid = $this->create_test_submission($this->anotherstudentg1);

        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $submission = $workshep->get_submission_by_id($submissionid);
        $assessmentid = $workshep->add_allocation($submission, $this->student->id);

        // Switch to assessment phase.
        $workshep->switch_phase(workshep::PHASE_ASSESSMENT);
        $this->setUser($this->student);
        $result = mod_workshep_external::get_assessment_form_definition($assessmentid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_assessment_form_definition_returns(), $result);
        $this->assertEquals(4, $result['dimenssionscount']);    // We receive the expected 4 dimensions.
        $this->assertEmpty($result['current']); // Assessment not yet done.
        foreach ($result['fields'] as $field) {
            if (strpos($field['name'], 'grade__idx_') === 0) {
                $this->assertEquals(25, $field['value']); // Check one of the dimension fields attributes.
            }
        }
        // Check dimensions grading info.
        foreach ($result['dimensionsinfo'] as $dimension) {
            $this->assertEquals(0, $dimension['min']);
            $this->assertEquals(25, $dimension['max']);
            $this->assertEquals(25, $dimension['weight']);
            $this->assertFalse(isset($dimension['scale']));
        }
    }

    /**
     * Test get_assessment_form_definition_teacher_new_assessment.
     */
    public function test_get_assessment_form_definition_teacher_new_assessment() {

        // Create the submission.
        $submissionid = $this->create_test_submission($this->anotherstudentg1);

        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $submission = $workshep->get_submission_by_id($submissionid);
        $assessmentid = $workshep->add_allocation($submission, $this->student->id);

        // Switch to assessment phase.
        $workshep->switch_phase(workshep::PHASE_ASSESSMENT);
        // Teachers need to be able to view assessments.
        $this->setUser($this->teacher);
        $result = mod_workshep_external::get_assessment_form_definition($assessmentid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_assessment_form_definition_returns(), $result);
        $this->assertEquals(4, $result['dimenssionscount']);
    }

    /**
     * Test get_assessment_form_definition_invalid_phase.
     */
    public function test_get_assessment_form_definition_invalid_phase() {

        // Create the submission.
        $submissionid = $this->create_test_submission($this->anotherstudentg1);

        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $submission = $workshep->get_submission_by_id($submissionid);
        $assessmentid = $workshep->add_allocation($submission, $this->anotherstudentg1->id);

        $workshep->switch_phase(workshep::PHASE_EVALUATION);
        $this->setUser($this->student);
        // Since we are not reviewers we can't see the assessment until the workshep is closed.
        $this->expectException('moodle_exception');
        mod_workshep_external::get_assessment_form_definition($assessmentid);
    }

    /**
     * Test get_reviewer_assessments.
     */
    public function test_get_reviewer_assessments() {

        // Create the submission.
        $submissionid1 = $this->create_test_submission($this->student);
        $submissionid2 = $this->create_test_submission($this->anotherstudentg1);

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $assessmentid1 = $workshepgenerator->create_assessment($submissionid1, $this->student->id, array(
            'weight' => 2,
            'grade' => 90,
        ));
        $assessmentid2 = $workshepgenerator->create_assessment($submissionid2, $this->student->id, array(
            'weight' => 3,
            'grade' => 80,
        ));

        // Switch to assessment phase.
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_ASSESSMENT);
        $this->setUser($this->student);
        // Get my assessments.
        $result = mod_workshep_external::get_reviewer_assessments($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_reviewer_assessments_returns(), $result);
        $this->assertCount(2, $result['assessments']);
        foreach ($result['assessments'] as $assessment) {
            if ($assessment['id'] == $assessmentid1) {
                $this->assertEquals(90, $assessment['grade']);
            } else {
                $this->assertEquals($assessmentid2, $assessment['id']);
                $this->assertEquals(80, $assessment['grade']);
            }
        }

        // Now, as teacher try to get the same student assessments.
        $result = mod_workshep_external::get_reviewer_assessments($this->workshep->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_reviewer_assessments_returns(), $result);
        $this->assertCount(2, $result['assessments']);
    }

    /**
     * Test get_reviewer_assessments_other_student.
     */
    public function test_get_reviewer_assessments_other_student() {

        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_ASSESSMENT);
        // Try to get other user assessments.
        $this->setUser($this->student);
        $this->expectException('moodle_exception');
        mod_workshep_external::get_reviewer_assessments($this->workshep->id, $this->anotherstudentg1->id);
    }

    /**
     * Test get_reviewer_assessments_invalid_phase.
     */
    public function test_get_reviewer_assessments_invalid_phase() {

        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_SUBMISSION);
        // Try to get other user assessments.
        $this->setUser($this->student);
        $this->expectException('moodle_exception');
        mod_workshep_external::get_reviewer_assessments($this->workshep->id, $this->anotherstudentg1->id);
    }

    /**
     * Test update_assessment.
     */
    public function test_update_assessment() {

        // Create the submission.
        $submissionid = $this->create_test_submission($this->anotherstudentg1);

        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $submission = $workshep->get_submission_by_id($submissionid);
        $assessmentid = $workshep->add_allocation($submission, $this->student->id);

        // Switch to assessment phase.
        $workshep->switch_phase(workshep::PHASE_ASSESSMENT);
        $this->setUser($this->student);
        // Get the form definition.
        $result = mod_workshep_external::get_assessment_form_definition($assessmentid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_assessment_form_definition_returns(), $result);

        // Prepare the data to be sent.
        $data = $result['fields'];
        foreach ($data as $key => $param) {
            if (strpos($param['name'], 'peercomment__idx_') === 0) {
                $data[$key]['value'] = 'Some content';
            } else if (strpos($param['name'], 'grade__idx_') === 0) {
                $data[$key]['value'] = 25; // Set all to 25.
            }
        }

        // Required data.
        $data[] = array(
            'name' => 'nodims',
            'value' => $result['dimenssionscount'],
        );

        // General feedback.
        $data[] = array(
            'name' => 'feedbackauthor',
            'value' => 'Feedback for the author',
        );
        $data[] = array(
            'name' => 'feedbackauthorformat',
            'value' => FORMAT_MOODLE,
        );

        // Create a file in a draft area for inline attachments.
        $fs = get_file_storage();
        $draftidinlineattach = file_get_unused_draft_itemid();
        $usercontext = context_user::instance($this->student->id);
        $filenameimg = 'shouldbeanimage.txt';
        $filerecordinline = array(
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => $draftidinlineattach,
            'filepath'  => '/',
            'filename'  => $filenameimg,
        );
        $fs->create_file_from_string($filerecordinline, 'image contents (not really)');

        // Create a file in a draft area for regular attachments.
        $draftidattach = file_get_unused_draft_itemid();
        $filerecordattach = $filerecordinline;
        $attachfilename = 'attachment.txt';
        $filerecordattach['filename'] = $attachfilename;
        $filerecordattach['itemid'] = $draftidattach;
        $fs->create_file_from_string($filerecordattach, 'simple text attachment');

        $data[] = array(
            'name' => 'feedbackauthorinlineattachmentsid',
            'value' => $draftidinlineattach,
        );
        $data[] = array(
            'name' => 'feedbackauthorattachmentsid',
            'value' => $draftidattach,
        );

        // Update the assessment.
        $result = mod_workshep_external::update_assessment($assessmentid, $data);
        $result = external_api::clean_returnvalue(mod_workshep_external::update_assessment_returns(), $result);
        $this->assertEquals(100, $result['rawgrade']);
        $this->assertTrue($result['status']);

        // Get the assessment and check it was updated properly.
        $result = mod_workshep_external::get_assessment($assessmentid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_assessment_returns(), $result);
        $this->assertEquals(100, $result['assessment']['grade']);
        $this->assertEquals($this->student->id, $result['assessment']['reviewerid']);
        $this->assertEquals('Feedback for the author', $result['assessment']['feedbackauthor']);
        $this->assertCount(1, $result['assessment']['feedbackcontentfiles']);
        $this->assertCount(1, $result['assessment']['feedbackattachmentfiles']);

        // Now, get again the form and check we received the data we already sent.
        $result = mod_workshep_external::get_assessment_form_definition($assessmentid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_assessment_form_definition_returns(), $result);
        foreach ($result['current'] as $currentdata) {
            if (strpos($currentdata['name'], 'peercomment__idx_') === 0) {
                $this->assertEquals('Some content', $currentdata['value']);
            } else if (strpos($currentdata['name'], 'grade__idx_') === 0) {
                $this->assertEquals(25, (int) $currentdata['value']);
            }
        }
    }

    /**
     * Test get_grades.
     */
    public function test_get_grades() {

        $timenow = time();
        $submissiongrade = array(
            'userid' => $this->student->id,
            'rawgrade' => 40,
            'feedback' => '',
            'feedbackformat' => 1,
            'datesubmitted' => $timenow,
            'dategraded' => $timenow,
        );
        $assessmentgrade = array(
            'userid' => $this->student->id,
            'rawgrade' => 10,
            'feedback' => '',
            'feedbackformat' => 1,
            'datesubmitted' => $timenow,
            'dategraded' => $timenow,
        );

        workshep_grade_item_update($this->workshep, (object) $submissiongrade, (object) $assessmentgrade);

        // First retrieve my grades.
        $this->setUser($this->student);
        $result = mod_workshep_external::get_grades($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_grades_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertEquals($assessmentgrade['rawgrade'], $result['assessmentrawgrade']);
        $this->assertEquals($submissiongrade['rawgrade'], $result['submissionrawgrade']);
        $this->assertFalse($result['assessmentgradehidden']);
        $this->assertFalse($result['submissiongradehidden']);
        $this->assertEquals($assessmentgrade['rawgrade'] . ".00 / 20.00", $result['assessmentlongstrgrade']);
        $this->assertEquals($submissiongrade['rawgrade'] . ".00 / 80.00", $result['submissionlongstrgrade']);

        // Second, teacher retrieve user grades.
        $this->setUser($this->teacher);
        $result = mod_workshep_external::get_grades($this->workshep->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_grades_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertEquals($assessmentgrade['rawgrade'], $result['assessmentrawgrade']);
        $this->assertEquals($submissiongrade['rawgrade'], $result['submissionrawgrade']);
        $this->assertFalse($result['assessmentgradehidden']);
        $this->assertFalse($result['submissiongradehidden']);
        $this->assertEquals($assessmentgrade['rawgrade'] . ".00 / 20.00", $result['assessmentlongstrgrade']);
        $this->assertEquals($submissiongrade['rawgrade'] . ".00 / 80.00", $result['submissionlongstrgrade']);
    }

    /**
     * Test get_grades_other_student.
     */
    public function test_get_grades_other_student() {

        // Create the submission that will be deleted.
        $submissionid = $this->create_test_submission($this->student);

        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_CLOSED);
        $this->setUser($this->anotherstudentg1);
        $this->expectException('moodle_exception');
        mod_workshep_external::get_grades($this->workshep->id, $this->student->id);
    }

    /**
     * Test evaluate_assessment.
     */
    public function test_evaluate_assessment() {
        global $DB;

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $submissionid = $workshepgenerator->create_submission($this->workshep->id, $this->student->id);
        $assessmentid = $workshepgenerator->create_assessment($submissionid, $this->anotherstudentg1->id, array(
            'weight' => 3,
            'grade' => 20,
        ));

        $this->setUser($this->teacher);
        $feedbacktext = 'The feedback';
        $feedbackformat = FORMAT_MOODLE;
        $weight = 10;
        $gradinggradeover = 10;
        $result = mod_workshep_external::evaluate_assessment($assessmentid, $feedbacktext, $feedbackformat, $weight,
            $gradinggradeover);
        $result = external_api::clean_returnvalue(mod_workshep_external::evaluate_assessment_returns(), $result);
        $this->assertTrue($result['status']);

        $assessment = $DB->get_record('workshep_assessments', array('id' => $assessmentid));
        $this->assertEquals('The feedback', $assessment->feedbackreviewer);
        $this->assertEquals(10, $assessment->weight);

        // Now test passing incorrect weight and grade values.
        $weight = 17;
        $gradinggradeover = 100;
        $result = mod_workshep_external::evaluate_assessment($assessmentid, $feedbacktext, $feedbackformat, $weight,
            $gradinggradeover);
        $result = external_api::clean_returnvalue(mod_workshep_external::evaluate_assessment_returns(), $result);
        $this->assertFalse($result['status']);
        $this->assertCount(2, $result['warnings']);
        $found = 0;
        foreach ($result['warnings'] as $warning) {
            if ($warning['item'] == 'weight' || $warning['item'] == 'gradinggradeover') {
                $found++;
            }
        }
        $this->assertEquals(2, $found);
    }

    /**
     * Test evaluate_assessment_ignore_parameters.
     */
    public function test_evaluate_assessment_ignore_parameters() {
        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $submissionid = $workshepgenerator->create_submission($this->workshep->id, $this->student->id);
        $assessmentid = $workshepgenerator->create_assessment($submissionid, $this->anotherstudentg1->id, array(
            'weight' => 3,
            'grade' => 20,
        ));

        assign_capability('mod/workshep:allocate', CAP_PROHIBIT, $this->teacherrole->id, $this->context->id);
        // Empty all the caches that may be affected  by this change.
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($this->teacher);
        $feedbacktext = 'The feedback';
        $feedbackformat = FORMAT_MOODLE;
        $weight = 10;
        $gradinggradeover = 19;
        $result = mod_workshep_external::evaluate_assessment($assessmentid, $feedbacktext, $feedbackformat, $weight,
            $gradinggradeover);
        $result = external_api::clean_returnvalue(mod_workshep_external::evaluate_assessment_returns(), $result);
        $this->assertTrue($result['status']);

        $result = mod_workshep_external::get_assessment($assessmentid);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_assessment_returns(), $result);
        $this->assertNotEquals(10, $result['assessment']['weight']);
    }

    /**
     * Test evaluate_assessment_no_permissions.
     */
    public function test_evaluate_assessment_no_permissions() {
        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $submissionid = $workshepgenerator->create_submission($this->workshep->id, $this->student->id);
        $assessmentid = $workshepgenerator->create_assessment($submissionid, $this->anotherstudentg1->id, array(
            'weight' => 3,
            'grade' => 20,
        ));

        $this->setUser($this->student);
        $feedbacktext = 'The feedback';
        $feedbackformat = FORMAT_MOODLE;
        $weight = 10;
        $gradinggradeover = 50;
        $this->expectException('moodle_exception');
        mod_workshep_external::evaluate_assessment($assessmentid, $feedbacktext, $feedbackformat, $weight, $gradinggradeover);
    }

    /**
     * Test get_grades_report.
     */
    public function test_get_grades_report() {

        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $submissionid1 = $workshepgenerator->create_submission($this->workshep->id, $this->student->id);
        $submissionid2 = $workshepgenerator->create_submission($this->workshep->id, $this->anotherstudentg1->id);

        $assessmentid1 = $workshepgenerator->create_assessment($submissionid2, $this->student->id, array(
            'weight' => 100,
            'grade' => 50,
        ));
        $assessmentid2 = $workshepgenerator->create_assessment($submissionid1, $this->anotherstudentg1->id, array(
            'weight' => 100,
            'grade' => 55,
        ));

        $workshep->switch_phase(workshep::PHASE_CLOSED);
        $this->setUser($this->teacher);
        $result = mod_workshep_external::get_grades_report($this->workshep->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_grades_report_returns(), $result);
        $this->assertEquals(3, $result['report']['totalcount']); // Expect 3 potential submissions.

        foreach ($result['report']['grades'] as $grade) {
            if ($grade['userid'] == $this->student->id) {
                $this->assertEquals($this->anotherstudentg1->id, $grade['reviewedby'][0]['userid']); // Check reviewer.
                $this->assertEquals($this->anotherstudentg1->id, $grade['reviewerof'][0]['userid']); // Check reviewer.
                $this->assertEquals($workshep->real_grade(50), $grade['reviewerof'][0]['grade']); // Check grade (converted).
                $this->assertEquals($workshep->real_grade(55), $grade['reviewedby'][0]['grade']); // Check grade (converted).
            } else if ($grade['userid'] == $this->anotherstudentg1->id) {
                $this->assertEquals($this->student->id, $grade['reviewedby'][0]['userid']); // Check reviewer.
                $this->assertEquals($this->student->id, $grade['reviewerof'][0]['userid']); // Check reviewer.
                $this->assertEquals($workshep->real_grade(55), $grade['reviewerof'][0]['grade']); // Check grade (converted).
                $this->assertEquals($workshep->real_grade(50), $grade['reviewedby'][0]['grade']); // Check grade (converted).
            }
        }
        // Now check pagination.
        $result = mod_workshep_external::get_grades_report($this->workshep->id, 0, 'lastname', 'ASC', 0, 1);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_grades_report_returns(), $result);
        $this->assertEquals(3, $result['report']['totalcount']); // Expect the total count.
        $this->assertCount(1, $result['report']['grades']);

        // Groups filtering.
        $result = mod_workshep_external::get_grades_report($this->workshep->id, $this->group1->id);
        $result = external_api::clean_returnvalue(mod_workshep_external::get_grades_report_returns(), $result);
        $this->assertEquals(2, $result['report']['totalcount']); // Expect the group count.
    }

    /**
     * Test get_grades_report_invalid_phase.
     */
    public function test_get_grades_report_invalid_phase() {
        $this->setUser($this->teacher);
        $this->expectException('moodle_exception');
        $this->expectExceptionMessage(get_string('nothingfound', 'workshep'));
        mod_workshep_external::get_grades_report($this->workshep->id);
    }

    /**
     * Test get_grades_report_missing_permissions.
     */
    public function test_get_grades_report_missing_permissions() {
        $this->setUser($this->student);
        $this->expectException('required_capability_exception');
        mod_workshep_external::get_grades_report($this->workshep->id);
    }

    /**
     * Test test_view_submission.
     */
    public function test_view_submission() {

        // Create a couple of submissions with files.
        $firstsubmissionid = $this->create_test_submission($this->student);  // Create submission with files.

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $this->setUser($this->student);
        $result = mod_workshep_external::view_submission($firstsubmissionid);
        $result = external_api::clean_returnvalue(mod_workshep_external::view_submission_returns(), $result);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_workshep\event\submission_viewed', $event);
        $this->assertEquals($this->context, $event->get_context());
        $moodleworkshep = new \moodle_url('/mod/workshep/submission.php', array('id' => $firstsubmissionid,
            'cmid' => $this->cm->id));
        $this->assertEquals($moodleworkshep, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

    }

    /**
     * Test evaluate_submission.
     */
    public function test_evaluate_submission() {
        global $DB;

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $submissionid = $workshepgenerator->create_submission($this->workshep->id, $this->student->id);

        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_EVALUATION);

        $this->setUser($this->teacher);
        $feedbacktext = 'The feedback';
        $feedbackformat = FORMAT_MOODLE;
        $published = 1;
        $gradeover = 10;
        $result = mod_workshep_external::evaluate_submission($submissionid, $feedbacktext, $feedbackformat, $published,
            $gradeover);
        $result = external_api::clean_returnvalue(mod_workshep_external::evaluate_submission_returns(), $result);
        $this->assertTrue($result['status']);

        $submission = $DB->get_record('workshep_submissions', array('id' => $submissionid));
        $this->assertEquals($feedbacktext, $submission->feedbackauthor);
        $this->assertEquals($workshep->raw_grade_value($gradeover, $workshep->grade), $submission->gradeover);  // Expected grade.
        $this->assertEquals(1, $submission->published); // Submission published.
    }

    /**
     * Test evaluate_submission_invalid_phase_for_override.
     */
    public function test_evaluate_submission_invalid_phase_for_override() {
        global $DB;

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $submissionid = $workshepgenerator->create_submission($this->workshep->id, $this->student->id);

        $this->setUser($this->teacher);
        $feedbacktext = 'The feedback';
        $feedbackformat = FORMAT_MOODLE;
        $published = 1;
        $gradeover = 10;
        $result = mod_workshep_external::evaluate_submission($submissionid, $feedbacktext, $feedbackformat, $published,
            $gradeover);
        $result = external_api::clean_returnvalue(mod_workshep_external::evaluate_submission_returns(), $result);
        $this->assertTrue($result['status']);

        $submission = $DB->get_record('workshep_submissions', array('id' => $submissionid));
        $this->assertEquals('', $submission->feedbackauthor);   // Feedback and grade not updated.
        $this->assertEquals(0, $submission->gradeover);
        $this->assertEquals(1, $submission->published); // Publishing status correctly updated.
    }

    /**
     * Test evaluate_submission_no_permissions.
     */
    public function test_evaluate_submission_no_permissions() {

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $submissionid = $workshepgenerator->create_submission($this->workshep->id, $this->student->id);
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_EVALUATION);

        $this->setUser($this->student);
        $feedbacktext = 'The feedback';
        $feedbackformat = FORMAT_MOODLE;
        $published = 1;
        $gradeover = 50;
        $this->expectException('moodle_exception');
        mod_workshep_external::evaluate_submission($submissionid, $feedbacktext, $feedbackformat, $published, $gradeover);
    }

    /**
     * Test evaluate_submission_invalid_grade.
     */
    public function test_evaluate_submission_invalid_grade() {

        $workshepgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshep');
        $submissionid = $workshepgenerator->create_submission($this->workshep->id, $this->student->id);
        $workshep = new workshep($this->workshep, $this->cm, $this->course);
        $workshep->switch_phase(workshep::PHASE_EVALUATION);

        $this->setUser($this->teacher);
        $feedbacktext = 'The feedback';
        $feedbackformat = FORMAT_MOODLE;
        $published = 1;
        $gradeover = 150;
        $result = mod_workshep_external::evaluate_submission($submissionid, $feedbacktext, $feedbackformat, $published, $gradeover);
        $result = external_api::clean_returnvalue(mod_workshep_external::evaluate_submission_returns(), $result);
        $this->assertCount(1, $result['warnings']);
        $this->assertFalse($result['status']);
        $this->assertEquals('gradeover', $result['warnings'][0]['item']);
    }
}
