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
 * Provides support for the conversion of moodle1 backup to the moodle2 format
 *
 * @package    workshepform_numerrors
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/gradelib.php'); // grade_floatval() called here

/**
 * Conversion handler for the numerrors grading strategy data
 */
class moodle1_workshepform_numerrors_handler extends moodle1_workshepform_handler {

    /** @var array */
    protected $mappings = array();

    /** @var array */
    protected $dimensions = array();

    /**
     * New workshep instance is being processed
     */
    public function on_elements_start() {
        $this->mappings = array();
        $this->dimensions = array();
    }

    /**
     * Converts <ELEMENT> into <workshepform_numerrors_dimension> and stores it for later writing
     *
     * @param array $data legacy element data
     * @param array $raw raw element data
     *
     * @return array to be written to workshep.xml
     */
    public function process_legacy_element(array $data, array $raw) {

        $workshep = $this->parenthandler->get_current_workshep();

        $mapping = array();
        $mapping['id'] = $data['id'];
        $mapping['nonegative'] = $data['elementno'];
        if ($workshep['grade'] == 0 or $data['maxscore'] == 0) {
            $mapping['grade'] = 0;
        } else {
            $mapping['grade'] = grade_floatval($data['maxscore'] / $workshep['grade'] * 100);
        }
        $this->mappings[] = $mapping;

        $converted = null;

        if (trim($data['description']) and $data['description'] <> '@@ GRADE_MAPPING_ELEMENT @@') {
            // prepare a fake record and re-use the upgrade logic
            $fakerecord = (object)$data;
            $converted = (array)workshepform_numerrors_upgrade_element($fakerecord, 12345678);
            unset($converted['workshepid']);

            $converted['id'] = $data['id'];
            $this->dimensions[] = $converted;
        }

        return $converted;
    }

    /**
     * Writes gathered mappings and dimensions
     */
    public function on_elements_end() {

        foreach ($this->mappings as $mapping) {
            $this->write_xml('workshepform_numerrors_map', $mapping, array('/workshepform_numerrors_map/id'));
        }

        foreach ($this->dimensions as $dimension) {
            $this->write_xml('workshepform_numerrors_dimension', $dimension, array('/workshepform_numerrors_dimension/id'));
        }
    }
}

/**
 * Transforms a given record from workshep_elements_old into an object to be saved into workshepform_numerrors
 *
 * @param stdClass $old legacy record from workshep_elements_old
 * @param int $newworkshepid id of the new workshep instance that replaced the previous one
 * @return stdclass to be saved in workshepform_numerrors
 */
function workshepform_numerrors_upgrade_element(stdclass $old, $newworkshepid) {
    $new = new stdclass();
    $new->workshepid = $newworkshepid;
    $new->sort = $old->elementno;
    $new->description = $old->description;
    $new->descriptionformat = FORMAT_HTML;
    $new->grade0 = get_string('grade0default', 'workshepform_numerrors');
    $new->grade1 = get_string('grade1default', 'workshepform_numerrors');
    // calculate new weight of the element. Negative weights are not supported any more and
    // are replaced with weight = 0. Legacy workshep did not store the raw weight but the index
    // in the array of weights (see $WORKSHOP_EWEIGHTS in workshep 1.x)
    // workshep 2.0 uses integer weights only (0-16) so all previous weights are multiplied by 4.
    switch ($old->weight) {
        case 8: $new->weight = 1; break;
        case 9: $new->weight = 2; break;
        case 10: $new->weight = 3; break;
        case 11: $new->weight = 4; break;
        case 12: $new->weight = 6; break;
        case 13: $new->weight = 8; break;
        case 14: $new->weight = 16; break;
        default: $new->weight = 0;
    }
    return $new;
}
