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
 * Allows user to view a static list of allocations
 *
 * @package    workshepallocation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(__FILE__)) . '/lib.php');                  // Interface definition.
require_once(dirname(dirname(dirname(__FILE__))) . '/locallib.php');    // Workshep internal API.

/**
 * Allows users to view a static list of allocations.
 */
class workshep_static_allocator implements workshep_allocator {

    /** @var workshep instance */
    protected $workshep;

    /**
     * @param workshep $workshep Workshop API object
     */
    public function __construct(workshep $workshep) {
        $this->workshep = $workshep;
    }

    /**
     * Allocate submissions as requested by user
     *
     * @return workshep_allocation_result
     */
    public function init() {

        $result = new workshep_allocation_result($this);
        $result->set_status(workshep_allocation_result::STATUS_VOID);
        return $result;
    }

    /**
     * Prints user interface - current allocation and a form to edit it
     */
    public function ui() {
        global $PAGE, $DB;

        $groupid    = groups_get_activity_group($this->workshep->cm, true);
        $output     = $PAGE->get_renderer('workshepallocation_static');

        // Fetch the list of ids of all workshep participants.
        $numofparticipants = $this->workshep->count_participants(false, $groupid);
        $participants = $this->workshep->get_participants(false, $groupid);

        // This will hold the information needed to display user names and pictures.
        $userinfo = $participants;

        // Load the participants' submissions.
        $submissions = $this->workshep->get_submissions(array_keys($participants));
        $allnames = get_all_user_name_fields();
        foreach ($submissions as $submission) {
            if (!isset($userinfo[$submission->authorid])) {
                $userinfo[$submission->authorid]            = new stdclass();
                $userinfo[$submission->authorid]->id        = $submission->authorid;
                $userinfo[$submission->authorid]->picture   = $submission->authorpicture;
                $userinfo[$submission->authorid]->imagealt  = $submission->authorimagealt;
                $userinfo[$submission->authorid]->email     = $submission->authoremail;
                foreach ($allnames as $addname) {
                    $temp = 'author' . $addname;
                    $userinfo[$submission->authorid]->$addname = $submission->$temp;
                }
            }
        }

        // Get the current reviewers.
        $reviewers = array();
        if ($submissions) {
            list($submissionids, $params) = $DB->get_in_or_equal(array_keys($submissions), SQL_PARAMS_NAMED);
            $picturefields = user_picture::fields('r', array(), 'reviewerid');
            $sql = "SELECT a.id AS assessmentid, a.submissionid, $picturefields,
                           s.id AS submissionid, s.authorid
                      FROM {workshep_assessments} a
                      JOIN {user} r ON (a.reviewerid = r.id)
                      JOIN {workshep_submissions} s ON (a.submissionid = s.id)
                     WHERE a.submissionid $submissionids";
            $reviewers = $DB->get_records_sql($sql, $params);
            foreach ($reviewers as $reviewer) {
                if (!isset($userinfo[$reviewer->reviewerid])) {
                    $userinfo[$reviewer->reviewerid]            = new stdclass();
                    $userinfo[$reviewer->reviewerid]->id        = $reviewer->reviewerid;
                    $userinfo[$reviewer->reviewerid]->picture   = $reviewer->picture;
                    $userinfo[$reviewer->reviewerid]->imagealt  = $reviewer->imagealt;
                    $userinfo[$reviewer->reviewerid]->email     = $reviewer->email;
                    foreach ($allnames as $addname) {
                        $userinfo[$reviewer->reviewerid]->$addname = $reviewer->$addname;
                    }
                }
            }
        }

        // Get the current reviewees.
        $reviewees = array();
        if ($participants) {
            list($participantids, $params) = $DB->get_in_or_equal(array_keys($participants), SQL_PARAMS_NAMED);
            $namefields = get_all_user_name_fields(true, 'e');
            $params['workshepid'] = $this->workshep->id;
            $sql = "SELECT a.id AS assessmentid, a.submissionid,
                           u.id AS reviewerid,
                           s.id AS submissionid,
                           e.id AS revieweeid, e.lastname, e.firstname, $namefields, e.picture, e.imagealt, e.email
                      FROM {user} u
                      JOIN {workshep_assessments} a ON (a.reviewerid = u.id)
                      JOIN {workshep_submissions} s ON (a.submissionid = s.id)
                      JOIN {user} e ON (s.authorid = e.id)
                     WHERE u.id $participantids AND s.workshepid = :workshepid AND s.example = 0";
            $reviewees = $DB->get_records_sql($sql, $params);
            foreach ($reviewees as $reviewee) {
                if (!isset($userinfo[$reviewee->revieweeid])) {
                    $userinfo[$reviewee->revieweeid]            = new stdclass();
                    $userinfo[$reviewee->revieweeid]->id        = $reviewee->revieweeid;
                    $userinfo[$reviewee->revieweeid]->firstname = $reviewee->firstname;
                    $userinfo[$reviewee->revieweeid]->lastname  = $reviewee->lastname;
                    $userinfo[$reviewee->revieweeid]->picture   = $reviewee->picture;
                    $userinfo[$reviewee->revieweeid]->imagealt  = $reviewee->imagealt;
                    $userinfo[$reviewee->revieweeid]->email     = $reviewee->email;
                    foreach ($allnames as $addname) {
                        $userinfo[$reviewee->revieweeid]->$addname = $reviewee->$addname;
                    }
                }
            }
        }

        // The information about the allocations.
        $allocations = array();

        foreach ($participants as $participant) {
            $allocations[$participant->id] = new stdClass();
            $allocations[$participant->id]->userid = $participant->id;
            $allocations[$participant->id]->submissionid = null;
            $allocations[$participant->id]->reviewedby = array();
            $allocations[$participant->id]->reviewerof = array();
        }
        unset($participants);

        foreach ($submissions as $submission) {
            $allocations[$submission->authorid]->submissionid = $submission->id;
            $allocations[$submission->authorid]->submissiontitle = $submission->title;
            $allocations[$submission->authorid]->submissiongrade = $submission->grade;
        }
        unset($submissions);

        foreach ($reviewers as $reviewer) {
            $allocations[$reviewer->authorid]->reviewedby[$reviewer->reviewerid] = $reviewer->assessmentid;
        }
        unset($reviewers);

        foreach ($reviewees as $reviewee) {
            $allocations[$reviewee->reviewerid]->reviewerof[$reviewee->revieweeid] = $reviewee->assessmentid;
        }
        unset($reviewees);

        // Get the calibration scores.
        $calibration = $this->workshep->calibration_instance();
        $scores = $calibration->get_calibration_scores();

        if (!is_array($scores)) {
            $scores = array();
        }

        // Prepare data to be rendered.
        $data                   = new workshepallocation_static_allocations();
        $data->workshep         = $this->workshep;
        $data->allocations      = $allocations;
        $data->userinfo         = $userinfo;
        $data->authors          = $this->workshep->get_potential_authors();
        $data->reviewers        = $this->workshep->get_potential_reviewers();
        $data->selfassessment   = $this->workshep->useselfassessment;
        $data->scores           = $scores;

        return $output->render($data);
    }

    /**
     * Delete all data related to a given workshep module instance
     *
     * This plugin does not store any data.
     *
     * @see workshep_delete_instance()
     * @param int $workshepid id of the workshep module instance being deleted
     * @return void
     */
    public static function delete_instance($workshepid) {
        return;
    }

    public static function teammode_class() {
        return;
    }
}

/**
 * Contains all information needed to render current allocations and the allocator UI
 *
 * @see workshep_manual_allocator::ui()
 */
class workshepallocation_static_allocations implements renderable {

    /** @var workshep module instance */
    public $workshep;

    /** @var array of stdClass, indexed by userid, properties userid, submissionid, (array)reviewedby, (array)reviewerof */
    public $allocations;

    /** @var array of stdClass contains the data needed to display the user name and picture */
    public $userinfo;

    /* var array of stdClass potential authors */
    public $authors;

    /* var array of stdClass potential reviewers */
    public $reviewers;

    /* var int the id of the user to highlight as the author */
    public $hlauthorid;

    /* var int the id of the user to highlight as the reviewer */
    public $hlreviewerid;

    /* var bool should the selfassessment be allowed */
    public $selfassessment;
}