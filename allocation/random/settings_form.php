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
 * Random allocator settings form
 *
 * @package    workshepallocation
 * @subpackage random
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Allocator settings form
 *
 * This is used by {@see workshep_random_allocator::ui()} to set up allocation parameters.
 *
 * @copyright 2009 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workshep_random_allocator_form extends moodleform {

    /**
     * Definition of the setting form elements
     */
    public function definition() {
        $mform          = $this->_form;
        $workshep       = $this->_customdata['workshep'];
        $plugindefaults = get_config('workshepallocation_random');

        $mform->addElement('header', 'randomallocationsettings', get_string('allocationsettings', 'workshepallocation_random'));

        $gmode = groups_get_activity_groupmode($workshep->cm, $workshep->course);
        switch ($gmode) {
        case NOGROUPS:
            $grouplabel = get_string('groupsnone', 'group');
            break;
        case VISIBLEGROUPS:
            $grouplabel = get_string('groupsvisible', 'group');
            break;
        case SEPARATEGROUPS:
            $grouplabel = get_string('groupsseparate', 'group');
            break;
        }
        $mform->addElement('static', 'groupmode', get_string('groupmode', 'group'), $grouplabel);

        $options_numper = array(
            workshep_random_allocator_setting::NUMPER_SUBMISSION => get_string('numperauthor', 'workshepallocation_random'),
            workshep_random_allocator_setting::NUMPER_REVIEWER   => get_string('numperreviewer', 'workshepallocation_random')
        );
        $grpnumofreviews = array();
        $grpnumofreviews[] = $mform->createElement('select', 'numofreviews', '',
                workshep_random_allocator::available_numofreviews_list());
        $mform->setDefault('numofreviews', $plugindefaults->numofreviews);
        $grpnumofreviews[] = $mform->createElement('select', 'numper', '', $options_numper);
        $mform->setDefault('numper', workshep_random_allocator_setting::NUMPER_SUBMISSION);
        $mform->addGroup($grpnumofreviews, 'grpnumofreviews', get_string('numofreviews', 'workshepallocation_random'),
                array(' '), false);

        if (VISIBLEGROUPS == $gmode) {
            $mform->addElement('checkbox', 'excludesamegroup', get_string('excludesamegroup', 'workshepallocation_random'));
            $mform->setDefault('excludesamegroup', 0);
        } else {
            $mform->addElement('hidden', 'excludesamegroup', 0);
            $mform->setType('excludesamegroup', PARAM_BOOL);
        }

        $mform->addElement('checkbox', 'removecurrent', get_string('removecurrentallocations', 'workshepallocation_random'));
        $mform->setDefault('removecurrent', 0);

        $options = array(); $label = '';
        if ($workshep->examplesmode == workshep::EXAMPLES_BEFORE_ASSESSMENT) {
            $options['disabled'] = 'disabled';
            $label = ' <em>Workshop settings require submission before assessment</em>';
        }
        $mform->addElement('checkbox', 'assesswosubmission', get_string('assesswosubmission', 'workshepallocation_random'), $label, $options);
        $mform->setDefault('assesswosubmission', 0);

        if (empty($workshep->useselfassessment)) {
            $mform->addElement('static', 'addselfassessment', get_string('addselfassessment', 'workshepallocation_random'),
                                                                 get_string('selfassessmentdisabled', 'workshep'));
        } else {
            $mform->addElement('checkbox', 'addselfassessment', get_string('addselfassessment', 'workshepallocation_random'));
        }

        $this->add_action_buttons();
    }
}
