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
 * Keeps track of upgrades to the workshep module
 *
 * @package    mod_workshep
 * @category   upgrade
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Performs upgrade of the database structure and data
 *
 * Workshop supports upgrades from version 1.9.0 and higher only. During 1.9 > 2.0 upgrade,
 * there are significant database changes.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_workshep_upgrade($oldversion) {
    global $CFG, $DB;

    // Moodle v2.8.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v2.9.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v3.0.0 release upgrade line.
    // Put any upgrade step following this.

    $dbman = $DB->get_manager();

    if ($oldversion < 2016022200) {
        // Add field submissionfiletypes to the table workshep.
        $table = new xmldb_table('workshep');
        $field = new xmldb_field('submissionfiletypes', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'nattachments');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add field overallfeedbackfiletypes to the table workshep.
        $field = new xmldb_field('overallfeedbackfiletypes',
                XMLDB_TYPE_CHAR, '255', null, null, null, null, 'overallfeedbackfiles');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2016022200, 'workshep');
    }

    // Moodle v3.1.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2016120600) {
        // Add field nosubmissionrequired to the table workshep.
        $table = new xmldb_table('workshep');
        $field = new xmldb_field('nosubmissionrequired', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2016120600, 'workshep');
    }

    if ($oldversion < 2016120601) {
        // Add field autorecalculate to the table workshep.
        $table = new xmldb_table('workshep');
        $field = new xmldb_field('autorecalculate', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2016120601, 'workshep');
    }

    if ($oldversion < 2016120602) {
        // Add field calibration comparison to the table workshep.
        $table = new xmldb_table('workshep');
        $field = new xmldb_field('calibrationcomparison', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add field calibration comparison to the table workshep.
        $table = new xmldb_table('workshep');
        $field = new xmldb_field('calibrationconsistency', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2016120602, 'workshep');
    }

    return true;
}
