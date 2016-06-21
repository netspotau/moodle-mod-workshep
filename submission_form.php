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
 * Submit an assignment or edit the already submitted work
 *
 * @package    mod_workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class workshep_submission_form extends moodleform {

    function definition() {
        $mform = $this->_form;

        $current        = $this->_customdata['current'];
        $workshep       = $this->_customdata['workshep'];
        $contentopts    = $this->_customdata['contentopts'];
        $attachmentopts = $this->_customdata['attachmentopts'];

        $mform->addElement('header', 'general', get_string('submission', 'workshep'));

        $mform->addElement('text', 'title', get_string('submissiontitle', 'workshep'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('editor', 'content_editor', get_string('submissioncontent', 'workshep'), null, $contentopts);
        $mform->setType('content', PARAM_RAW);

        if ($workshep->nattachments > 0) {
            $mform->addElement('static', 'filemanagerinfo', get_string('nattachments', 'workshep'), $workshep->nattachments);
            $mform->addElement('filemanager', 'attachment_filemanager', get_string('submissionattachment', 'workshep'),
                                null, $attachmentopts);
        }

        $mform->addElement('hidden', 'id', $current->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'cmid', $workshep->cm->id);
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'edit', 1);
        $mform->setType('edit', PARAM_INT);

        $mform->addElement('hidden', 'example', 0);
        $mform->setType('example', PARAM_INT);

        $this->add_action_buttons();

        $this->set_data($current);
    }

    function validation($data, $files) {
        global $CFG, $USER, $DB;

        $errors = parent::validation($data, $files);

        if (empty($data['id']) and empty($data['example'])) {
            // make sure there is no submission saved meanwhile from another browser window
            $sql = "SELECT COUNT(s.id)
                      FROM {workshep_submissions} s
                      JOIN {workshep} w ON (s.workshepid = w.id)
                      JOIN {course_modules} cm ON (w.id = cm.instance)
                      JOIN {modules} m ON (m.name = 'workshep' AND m.id = cm.module)
                     WHERE cm.id = ? AND s.authorid = ? AND s.example = 0";

            if ($DB->count_records_sql($sql, array($data['cmid'], $USER->id))) {
                $errors['title'] = get_string('err_multiplesubmissions', 'mod_workshep');
            }
        }

        return $errors;
    }
}
