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
 * Best evaluation settings form
 *
 * @package    workshepeval
 * @subpackage best
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class workshep_best_evaluation_settings_form extends moodleform {

    function definition() {
        $mform = $this->_form;

        $plugindefaults = get_config('workshepeval_best');
        $current        = $this->_customdata['current'];
        $workshep       = $this->_customdata['workshep'];

        $mform->addElement('header', 'general', get_string('settings', 'workshepeval_best'));

        $options = $workshep->available_evaluation_methods_list();

        //TODO: this is a weird way of doing this. maybe we should AJAX in the forms when switching methods?
        $label = get_string('evaluationmethod', 'workshep');
        $mform->addElement('select', 'methodname', $label, $options);
        $mform->addHelpButton('methodname', 'evaluationmethod', 'workshep');

        $options = array();
        for ($i = 9; $i >= 1; $i = $i-2) {
            $options[$i] = get_string('comparisonlevel' . $i, 'workshepeval_best');
        }
        $label = get_string('comparison', 'workshepeval_best');
        $mform->addElement('select', 'comparison', $label, $options);
        $mform->addHelpButton('comparison', 'comparison', 'workshepeval_best');
        $mform->setDefault('comparison', $plugindefaults->bcomparison);

        $mform->addElement('submit', 'submit', get_string('aggregategrades', 'workshep'));

        $this->set_data($current);
    }
}
