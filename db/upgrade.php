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
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    // Moodle v2.2.0 release upgrade line

    if ($oldversion < 2012033100) {
        // add the field 'phaseswitchassessment' to the 'workshep' table
        $table = new xmldb_table('workshep');
        $field = new xmldb_field('phaseswitchassessment', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'assessmentend');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2012033100, 'workshep');
    }

    /**
     * Remove all workshep calendar events
     */
    if ($oldversion < 2012041700) {
        require_once($CFG->dirroot . '/calendar/lib.php');
        $events = $DB->get_records('event', array('modulename' => 'workshep'));
        foreach ($events as $event) {
            $event = calendar_event::load($event);
            $event->delete();
        }
        upgrade_mod_savepoint(true, 2012041700, 'workshep');
    }

    /**
     * Recreate all workshep calendar events
     */
    if ($oldversion < 2012041701) {
        require_once(dirname(dirname(__FILE__)) . '/lib.php');

        $sql = "SELECT w.id, w.course, w.name, w.intro, w.introformat, w.submissionstart,
                       w.submissionend, w.assessmentstart, w.assessmentend,
                       cm.id AS cmid
                  FROM {workshep} w
                  JOIN {modules} m ON m.name = 'workshep'
                  JOIN {course_modules} cm ON (cm.module = m.id AND cm.course = w.course AND cm.instance = w.id)";

        $rs = $DB->get_recordset_sql($sql);

        foreach ($rs as $workshep) {
            $cmid = $workshep->cmid;
            unset($workshep->cmid);
            workshep_calendar_update($workshep, $cmid);
        }
        $rs->close();
        upgrade_mod_savepoint(true, 2012041701, 'workshep');
    }

    // Moodle v2.3.0 release upgrade line

    /**
     * Add new fields conclusion and conclusionformat
     */
    if ($oldversion < 2012102400) {
        $table = new xmldb_table('workshep');

        $field = new xmldb_field('conclusion', XMLDB_TYPE_TEXT, null, null, null, null, null, 'phaseswitchassessment');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('conclusionformat', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '1', 'conclusion');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2012102400, 'workshep');
    }


    // Moodle v2.4.0 release upgrade line
    // Put any upgrade step following this

    /**
     * Add overall feedback related fields into the workshep table.
     */
    if ($oldversion < 2013032500) {
        $table = new xmldb_table('workshep');

		if (! $dbman->field_exists('workshep','teammode')) {
			$field = new xmldb_field('teammode', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, false, '0');
			$dbman->add_field($table, $field);
		}

        $field = new xmldb_field('overallfeedbackmode', XMLDB_TYPE_INTEGER, '3', null, null, null, '1', 'conclusionformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('overallfeedbackfiles', XMLDB_TYPE_INTEGER, '3', null, null, null, '0', 'overallfeedbackmode');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('overallfeedbackmaxbytes', XMLDB_TYPE_INTEGER, '10', null, null, null, '100000', 'overallfeedbackfiles');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2013032500, 'workshep');
    }

    /**
     * Add feedbackauthorattachment field into the workshep_assessments table.
     */
    if ($oldversion < 2013032501) {

        $table = new xmldb_table('workshep_assessments');
        $field = new xmldb_field('feedbackauthorattachment', XMLDB_TYPE_INTEGER, '3', null, null, null, '0', 'feedbackauthorformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }


        if (!$dbman->field_exists('workshep', 'examplescompare')) {
            $field = new xmldb_field('examplescompare', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, false, '1');
            $dbman->add_field($table, $field);
        }

        if (!$dbman->field_exists('workshep', 'examplesreassess')) {
            $field = new xmldb_field('examplesreassess', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, false, '1');
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('numexamples', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, false, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table workshep_user_examples to be created
        $table = new xmldb_table('workshep_user_examples');

        // Adding fields to table workshep_user_examples
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('submissionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('workshepid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table workshep_user_examples
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for workshep_user_examples
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2013032501, 'workshep');

    }

    // Add unique index to assessments table to improve assesment allocation.
    if ($oldversion < 2014042900) {

        $index = new xmldb_index('submissionreviewer', XMLDB_INDEX_UNIQUE, array('submissionid', 'reviewerid'));
        $table = new xmldb_table('workshep_assessments');

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_mod_savepoint(true, 2014042900, 'workshep');
    }

    // Moodle v2.6.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v2.7.0 release upgrade line.
    // Put any upgrade step following this.

    
    if ($oldversion < 2014092600) {
        $table = new xmldb_table('workshep_assessments');
        $field = new xmldb_field('submitterflagged', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, false, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        upgrade_mod_savepoint(true, 2014092600, 'workshep');
    }
    
    if ($oldversion < 2014092601) {
        $table = new xmldb_table('workshep');
        
        $field = new xmldb_field('usecalibration', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, false, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('calibrationphase', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, false, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('calibrationmethod', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, false, 'examples');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $table = new xmldb_table('workshep_calibration');

        // Adding fields to table workshep_user_examples
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('workshepid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('score', XMLDB_TYPE_FLOAT, '10', null, XMLDB_NOTNULL);
        
        // Adding keys to table workshep_user_examples
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('wkshusrunq', XMLDB_KEY_UNIQUE, array('workshepid', 'userid'));

        // Conditionally launch create table for workshep_user_examples
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        
        upgrade_mod_savepoint(true, 2014092601, 'workshep');
    }
	
	if ($oldversion < 2014092602) {
		$table = new xmldb_table('workshep');
		
		$field = new xmldb_field('submitterflagging', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, false, '1');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
		
		upgrade_mod_savepoint(true, 2014092602, 'workshep');
	}


    return true;
}
