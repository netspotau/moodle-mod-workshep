<?php

namespace mod_workshep;

// I'm not sure this is ever actually necessary. Pretty much anytime evaluation_context is called
// locallib has already been included... but if it fucks up, uncomment this line.
// require_once(dirname(dirname(__FILE__)) . '/locallib.php');

class evaluation_context extends \local_teameval\evaluation_context {

    protected $workshep;

    // Because workshep's constructor will only take a stdClass and not a cm_info,
    // we have to pass the proper cm_info as well.
    public function __construct($workshep, $cm) {
        $this->workshep = $workshep;
        parent::__construct($cm);
    }

    public function evaluation_permitted($userid = null) {
        if ($this->workshep->teammode) {

            if ($userid == null) {
                // as long as we're in team mode, you can use teameval
                return true;
            }

            $available = parent::evaluation_permitted($userid);

            if ($available) {
                return $this->workshep->phase >= \workshep::PHASE_EVALUATION;
            }

        }

        return false;
    }

    public function default_deadline() {
        $duedate = max($this->workshep->assessmentend, $this->workshep->submissionend);
        if ($duedate) {
            return $duedate + 604800;
        } else {
            return $this->cm->added + 604800;
        }
    }

    public function minimum_deadline() {
        return $this->workshep->assessmentend;
    }

    public function group_for_user($userid) {
        return $this->workshep->user_group($userid);
    }

    public function all_groups() {
        return groups_get_all_groups($this->cm->course, 0, $this->cm->groupingid);
    }

    public function marking_users() {
        $grouped = $this->workshep->get_potential_authors(false);
        $users = array_reduce($grouped, function($carry = [], $new) {
            return $carry + $new;
        }, []);
        return $users;
    }

    public function grade_for_group($groupid) {
        global $DB;
        $sql = <<<SQL
SELECT s.grade
    FROM {workshep_submissions} s
        LEFT JOIN {groups_members} m ON s.authorid = m.userid
    WHERE s.workshepid = :workshepid
        AND m.groupid = :groupid
    ORDER BY s.timemodified DESC
    LIMIT 1;
SQL;
        return $DB->get_field_sql($sql, ['workshepid' => $this->workshep->id, 'groupid' => $groupid]);
    }

    public function trigger_grade_update($users = null) {

        if (\workshep::PHASE_CLOSED == $this->workshep->phase) {
            // push the grades into the gradebook
            $workshep = new \stdClass();
            foreach ($this as $property => $value) {
                $workshep->{$property} = $value;
            }
            $workshep->cmidnumber = $this->cm->id;
            $workshep->modname    = 'workshep';
            workshep_update_grades($workshep);
        }

    }

    public function format_grade($grade) {
        $grade *= $this->workshep->grade / 100;
        return parent::format_grade($grade);
    }

}