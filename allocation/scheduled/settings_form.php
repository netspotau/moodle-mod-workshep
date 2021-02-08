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
 * Scheduled allocator's settings
 *
 * @package     workshepallocation_scheduled
 * @subpackage  mod_workshep
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');
require_once(dirname(dirname(__FILE__)) . '/random/settings_form.php'); // parent form

/**
 * Allocator settings form
 *
 * This is used by {@see workshep_scheduled_allocator::ui()} to set up allocation parameters.
 */
class workshep_scheduled_allocator_form extends workshep_random_allocator_form {

    /**
     * Definition of the setting form elements
     */
    public function definition() {
        global $OUTPUT;

        $mform = $this->_form;
        $workshep = $this->_customdata['workshep'];
        $current = $this->_customdata['current'];

        if (!empty($workshep->submissionend)) {
            $strtimeexpected = workshep::timestamp_formats($workshep->submissionend);
        }

        if (!empty($current->timeallocated)) {
            $strtimeexecuted = workshep::timestamp_formats($current->timeallocated);
        }

        $mform->addElement('header', 'scheduledallocationsettings', get_string('scheduledallocationsettings', 'workshepallocation_scheduled'));
        $mform->addHelpButton('scheduledallocationsettings', 'scheduledallocationsettings', 'workshepallocation_scheduled');

        $mform->addElement('checkbox', 'enablescheduled', get_string('enablescheduled', 'workshepallocation_scheduled'), get_string('enablescheduledinfo', 'workshepallocation_scheduled'), 1);

        $mform->addElement('header', 'scheduledallocationinfo', get_string('currentstatus', 'workshepallocation_scheduled'));

        if ($current === false) {
            $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'workshepallocation_scheduled'),
                get_string('resultdisabled', 'workshepallocation_scheduled').' '. $OUTPUT->pix_icon('i/invalid', ''));

        } else {
            if (!empty($current->timeallocated)) {
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'workshepallocation_scheduled'),
                    get_string('currentstatusexecution1', 'workshepallocation_scheduled', $strtimeexecuted).' '.
                    $OUTPUT->pix_icon('i/valid', ''));

                if ($current->resultstatus == workshep_allocation_result::STATUS_EXECUTED) {
                    $strstatus = get_string('resultexecuted', 'workshepallocation_scheduled').' '.
                        $OUTPUT->pix_icon('i/valid', '');
                } else if ($current->resultstatus == workshep_allocation_result::STATUS_FAILED) {
                    $strstatus = get_string('resultfailed', 'workshepallocation_scheduled').' '.
                        $OUTPUT->pix_icon('i/valid', '');
                } else {
                    $strstatus = get_string('resultvoid', 'workshepallocation_scheduled').' '.
                        $OUTPUT->pix_icon('i/valid', '');
                }

                if (!empty($current->resultmessage)) {
                    $strstatus .= html_writer::empty_tag('br').$current->resultmessage; // yes, this is ugly. better solution suggestions are welcome.
                }
                $mform->addElement('static', 'inforesult', get_string('currentstatusresult', 'workshepallocation_scheduled'), $strstatus);

                if ($current->timeallocated < $workshep->submissionend) {
                    $mform->addElement('static', 'infoexpected', get_string('currentstatusnext', 'workshepallocation_scheduled'),
                        get_string('currentstatusexecution2', 'workshepallocation_scheduled', $strtimeexpected).' '.
                            $OUTPUT->pix_icon('i/caution', ''));
                    $mform->addHelpButton('infoexpected', 'currentstatusnext', 'workshepallocation_scheduled');
                } else {
                    $mform->addElement('checkbox', 'reenablescheduled', get_string('currentstatusreset', 'workshepallocation_scheduled'),
                       get_string('currentstatusresetinfo', 'workshepallocation_scheduled'));
                    $mform->addHelpButton('reenablescheduled', 'currentstatusreset', 'workshepallocation_scheduled');
                }

            } else if (empty($current->enabled)) {
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'workshepallocation_scheduled'),
                    get_string('resultdisabled', 'workshepallocation_scheduled').' '.
                    $OUTPUT->pix_icon('i/invalid', ''));

            } else if ($workshep->phase != workshep::PHASE_SUBMISSION) {
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'workshepallocation_scheduled'),
                    get_string('resultfailed', 'workshepallocation_scheduled').' '.
                    $OUTPUT->pix_icon('i/invalid', '') .
                    html_writer::empty_tag('br').
                    get_string('resultfailedphase', 'workshepallocation_scheduled'));

            } else if (empty($workshep->submissionend)) {
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'workshepallocation_scheduled'),
                    get_string('resultfailed', 'workshepallocation_scheduled').' '.
                    $OUTPUT->pix_icon('i/invalid', '') .
                    html_writer::empty_tag('br').
                    get_string('resultfaileddeadline', 'workshepallocation_scheduled'));

            } else if ($workshep->submissionend < time()) {
                // next cron will execute it
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'workshepallocation_scheduled'),
                    get_string('currentstatusexecution4', 'workshepallocation_scheduled').' '.
                    $OUTPUT->pix_icon('i/caution', ''));

            } else {
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'workshepallocation_scheduled'),
                    get_string('currentstatusexecution3', 'workshepallocation_scheduled', $strtimeexpected).' '.
                    $OUTPUT->pix_icon('i/caution', ''));
            }
        }

        parent::definition();

        $mform->addHelpButton('randomallocationsettings', 'randomallocationsettings', 'workshepallocation_scheduled');
    }
}
