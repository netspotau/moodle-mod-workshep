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
 * Event observers for workshepallocation_scheduled.
 *
 * @package workshepallocation_scheduled
 * @copyright 2013 Adrian Greeve <adrian@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace workshepallocation_scheduled;
defined('MOODLE_INTERNAL') || die();

/**
 * Class for workshepallocation_scheduled observers.
 *
 * @package workshepallocation_scheduled
 * @copyright 2013 Adrian Greeve <adrian@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Triggered when the '\mod_workshep\event\course_module_viewed' event is triggered.
     *
     * This does the same job as {@link workshepallocation_scheduled_cron()} but for the
     * single workshep. The idea is that we do not need to wait for cron to execute.
     * Displaying the workshep main view.php can trigger the scheduled allocation, too.
     *
     * @param \mod_workshep\event\course_module_viewed $event
     * @return bool
     */
    public static function workshep_viewed($event) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/workshep/locallib.php');

        $workshep = $event->get_record_snapshot('workshep', $event->objectid);
        $course   = $event->get_record_snapshot('course', $event->courseid);
        $cm       = $event->get_record_snapshot('course_modules', $event->contextinstanceid);

        $workshep = new \workshep($workshep, $cm, $course);
        $now = time();

        // Non-expensive check to see if the scheduled allocation can even happen.
        if ($workshep->phase == \workshep::PHASE_SUBMISSION and $workshep->submissionend > 0 and $workshep->submissionend < $now) {

            // Make sure the scheduled allocation has been configured for this workshep, that it has not
            // been executed yet and that the passed workshep record is still valid.
            $sql = "SELECT a.id
                      FROM {workshepallocation_scheduled} a
                      JOIN {workshep} w ON a.workshepid = w.id
                     WHERE w.id = :workshepid
                           AND a.enabled = 1
                           AND w.phase = :phase
                           AND w.submissionend > 0
                           AND w.submissionend < :now
                           AND (a.timeallocated IS NULL OR a.timeallocated < w.submissionend)";
            $params = array('workshepid' => $workshep->id, 'phase' => \workshep::PHASE_SUBMISSION, 'now' => $now);

            if ($DB->record_exists_sql($sql, $params)) {
                // Allocate submissions for assessments.
                $allocator = $workshep->allocator_instance('scheduled');
                $result = $allocator->execute();
                // Todo inform the teachers about the results.
            }
        }
        return true;
    }
}
