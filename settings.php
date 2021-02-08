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
 * The workshep module configuration variables
 *
 * The values defined here are often used as defaults for all module instances.
 *
 * @package    mod_workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/workshep/locallib.php');

    $grades = workshep::available_maxgrades_list();

    $settings->add(new admin_setting_configselect('workshep/grade', get_string('submissiongrade', 'workshep'),
                        get_string('configgrade', 'workshep'), 80, $grades));

    $settings->add(new admin_setting_configselect('workshep/gradinggrade', get_string('gradinggrade', 'workshep'),
                        get_string('configgradinggrade', 'workshep'), 20, $grades));

    $options = array();
    for ($i = 5; $i >= 0; $i--) {
        $options[$i] = $i;
    }
    $settings->add(new admin_setting_configselect('workshep/gradedecimals', get_string('gradedecimals', 'workshep'),
                        get_string('configgradedecimals', 'workshep'), 0, $options));

    if (isset($CFG->maxbytes)) {
        $maxbytes = get_config('workshep', 'maxbytes');
        $options = get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes);
        $settings->add(new admin_setting_configselect('workshep/maxbytes', get_string('maxbytes', 'workshep'),
                            get_string('configmaxbytes', 'workshep'), 0, $options));
    }

    $settings->add(new admin_setting_configselect('workshep/strategy', get_string('strategy', 'workshep'),
                        get_string('configstrategy', 'workshep'), 'accumulative', workshep::available_strategies_list()));

    $options = workshep::available_example_modes_list();
    $settings->add(new admin_setting_configselect('workshep/examplesmode', get_string('examplesmode', 'workshep'),
                        get_string('configexamplesmode', 'workshep'), workshep::EXAMPLES_VOLUNTARY, $options));

    // include the settings of allocation subplugins
    $allocators = core_component::get_plugin_list('workshepallocation');
    foreach ($allocators as $allocator => $path) {
        if (file_exists($settingsfile = $path . '/settings.php')) {
            $settings->add(new admin_setting_heading('workshepallocationsetting'.$allocator,
                    get_string('allocation', 'workshep') . ' - ' . get_string('pluginname', 'workshepallocation_' . $allocator), ''));
            include($settingsfile);
        }
    }

    // include the settings of grading strategy subplugins
    $strategies = core_component::get_plugin_list('workshepform');
    foreach ($strategies as $strategy => $path) {
        if (file_exists($settingsfile = $path . '/settings.php')) {
            $settings->add(new admin_setting_heading('workshepformsetting'.$strategy,
                    get_string('strategy', 'workshep') . ' - ' . get_string('pluginname', 'workshepform_' . $strategy), ''));
            include($settingsfile);
        }
    }

    // include the settings of grading evaluation subplugins
    $evaluations = core_component::get_plugin_list('workshepeval');
    foreach ($evaluations as $evaluation => $path) {
        if (file_exists($settingsfile = $path . '/settings.php')) {
            $settings->add(new admin_setting_heading('workshepevalsetting'.$evaluation,
                    get_string('evaluation', 'workshep') . ' - ' . get_string('pluginname', 'workshepeval_' . $evaluation), ''));
            include($settingsfile);
        }
    }
    
    // include the settings of grading evaluation subplugins
    $calibrations = core_component::get_plugin_list('workshepcalibration');
    foreach ($calibrations as $calibration => $path) {
        if (file_exists($settingsfile = $path . '/settings.php')) {
            $settings->add(new admin_setting_heading('workshepcalibrationsetting'.$evaluation,
                    get_string('calibration', 'workshep') . ' - ' . get_string('pluginname', 'workshepcalibration_' . $calibration), ''));
            include($settingsfile);
        }
    }

}
