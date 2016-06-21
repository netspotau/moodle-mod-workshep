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
 * Renderer class for the static allocation UI is defined here
 *
 * @package    workshepallocation
 * @subpackage static
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Manual allocation renderer class
 */
class workshepallocation_static_renderer extends mod_workshep_renderer  {

    /** @var workshep module instance */
    protected $workshep;

    /** @var nosubmissionfound string cache */
    private $nosubmissionfound;

    /** @var nogradeyet string cache */
    private $nogradeyet;

    /** @var alreadygraded string cache */
    private $alreadygraded;

    /** @var nothingtoreview string cache */
    private $nothingtoreview;

    /** @var withoutsubmission string cache */
    private $withoutsubmission;

    /** @var reviewernumber string cache */
    private $reviewernumber;

    /** @var revieweenumber string cache */
    private $revieweenumber;

    /** @var calibrarionscore string cache */
    private $calibrationscore;

    /** @var calibrationscorehelp string cache */
    private $calibrationscorehelp;

    /** @var scores list of calibration scores */
    private $scores;

    /**
     * Display the table of all current allocations and widgets to modify them
     *
     * @param workshepallocation_static_allocations $data to be displayed
     * @return string html code
     */
    protected function render_workshepallocation_static_allocations(workshepallocation_static_allocations $data) {
        global $PAGE, $CFG;

        $this->workshep     = $data->workshep;

        $allocations        = $data->allocations;       // Prepared array of all allocations data.
        $userinfo           = $data->userinfo;          // Names and pictures of all required users.
        $authors            = $data->authors;           // Potential reviewees.
        $reviewers          = $data->reviewers;         // Potential submission reviewers.
        $selfassessment     = $data->selfassessment;    // Is the self-assessment allowed in this workshep?
        $this->scores       = $data->scores;            // Available callibration scores.

        if (empty($allocations)) {
            return '';
        }

        // String caches.
        $this->nosubmissionfound = $this->output->container(get_string('nosubmissionfound', 'workshep'), 'info');
        $this->nogradeyet = $this->output->container(get_string('nogradeyet', 'workshep'), array('grade', 'missing'));
        $this->alreadygraded = $this->output->container(get_string('alreadygraded', 'workshep'), array('grade', 'missing'));
        $this->nothingtoreview = $this->output->container(get_string('nothingtoreview', 'workshep'), 'info');
        $this->withoutsubmission = $this->output->container(get_string('withoutsubmission', 'workshep'), 'info');
        $this->reviewernumber = get_string('reviewernumber', 'workshepallocation_static');
        $this->revieweenumber = get_string('revieweenumber', 'workshepallocation_static');
        $this->calibrationscore = get_string('calibrationscore', 'workshepallocation_static');
        $this->calibrationscorehelp = get_string('calibrationscore_help', 'workshepallocation_static');

        $table              = new html_table();
        $table->attributes['class'] = 'allocations';
        $table->head        = array(get_string('participantreviewedby', 'workshep'),
                                    get_string('participant', 'workshep'),
                                    get_string('participantrevierof', 'workshep'));
        $table->rowclasses  = array();
        $table->colclasses  = array('reviewedby', 'peer', 'reviewerof');
        $table->data        = array();
        foreach ($allocations as $allocation) {
            $row = array();
            $row[] = $this->helper_reviewers_of_participant($allocation, $userinfo, $reviewers, $selfassessment);
            $row[] = $this->helper_participant($allocation, $userinfo);
            $row[] = $this->helper_reviewees_of_participant($allocation, $userinfo, $authors, $selfassessment);
            $table->data[] = $row;
        }

        $url = new moodle_url("allocation/download.php", array("id" => $this->workshep->cm->id));
        $btn = new single_button($url, get_string('downloadallocations', 'workshep'), 'get');

        $o = html_writer::table($table);
        $o .= $this->output->render($btn);

        return $this->output->container($o, 'static-allocator');
    }

    /**
     * static full name cache
     */
    private function fullname_cache($userinfo, $userid) {
        static $cache = array();

        if (isset($cache[$userid])) {
            return $cache[$userid];
        } else {
            $fullname = fullname($userinfo[$userid]);
            $cache[$userid] = $fullname;
            return $fullname;
        }
    }

    /**
     * static user picture cache
     */
    private function user_picture_cache($userinfo, $userid, $smallicon = false) {
        static $largecache = array();
        static $smallcache = array();

        if (!$smallicon) {
            if (isset($largecache[$userid])) {
                return $largecache[$userid];
            } else {
                $userpicture = $this->output->user_picture(
                    $userinfo[$userid],
                    array('courseid' => $this->page->course->id)
                );
                $largecache[$userid] = $userpicture;
                return $userpicture;
            }
        } else {
            if (isset($smallcache[$userid])) {
                return $smallcache[$userid];
            } else {
                $userpicture = $this->output->user_picture(
                    $userinfo[$userid],
                    array('courseid' => $this->page->course->id, 'size' => 16)
                );
                $smallcache[$userid] = $userpicture;
                return $userpicture;
            }
        }
    }

    /**
     * Static calibration score cache
     */
    private function calibrationscore_cache($userid) {
        static $cache = array();
        static $calibration = null;

        if ($calibration == null) {
            $calibration = $this->workshep->calibration_instance();
        }

        if (isset($cache[$userid])) {
            return $cache[$userid];
        } else {
            if (isset($this->scores[$userid])) {
                $link = html_writer::link($calibration->user_calibration_url($userid),
                    sprintf($this->calibrationscore, $this->scores[$userid]),
                    array(
                        'class' => 'score',
                        'title' => $this->calibrationscorehelp
                    )
                );
                $cache[$userid] = $link;
                return $link;
            } else {
                $cache[$userid] = '';
                return '';
            }
        }
    }

    /**
     * Returns information about the workshep participant
     *
     * @return string HTML code
     */
    protected function helper_participant(stdclass $allocation, array $userinfo) {
        $o  = $this->user_picture_cache($userinfo, $allocation->userid);
        $o .= $this->fullname_cache($userinfo, $allocation->userid);
        $o .= $this->calibrationscore_cache($allocation->userid);
        $o .= $this->output->container_start(array('submission'));
        if (is_null($allocation->submissionid)) {
            $o .= $this->nosubmissionfound;
        } else {
            $link = $this->workshep->submission_url($allocation->submissionid);
            $o .= $this->output->container(html_writer::link($link, format_string($allocation->submissiontitle)), 'title');
            if (is_null($allocation->submissiongrade)) {
                $o .= $this->nogradeyet;
            } else {
                $o .= $this->alreadygraded;
            }
        }
        $o .= $this->output->container_end();
        return $o;
    }

    /**
     * Returns information about the current reviewers of the given participant and a selector do add new one
     *
     * @return string html code
     */
    protected function helper_reviewers_of_participant(stdclass $allocation, array $userinfo, array $reviewers, $selfassessment) {
        $o = '';
        if (is_null($allocation->submissionid)) {
            $o .= $this->nothingtoreview;
        }
        $o .= html_writer::start_tag('ul', array());
        foreach ($allocation->reviewedby as $reviewerid => $assessmentid) {
            $o .= html_writer::start_tag('li', array());
            $o .= $this->user_picture_cache($userinfo, $reviewerid, true);
            $o .= $this->fullname_cache($userinfo, $reviewerid);
            $o .= $this->calibrationscore_cache($reviewerid);
            $o .= html_writer::end_tag('li');
        }
        $o .= html_writer::end_tag('ul');
        $o .= html_writer::tag('p',
            sprintf($this->reviewernumber, count($allocation->reviewedby)),
            array('class' => 'reviewernumber')
        );
        return $o;
    }

    /**
     * Returns information about the current reviewees of the given participant and a selector do add new one
     *
     * @return string html code
     */
    protected function helper_reviewees_of_participant(stdclass $allocation, array $userinfo, array $authors, $selfassessment) {
        $o = '';
        if (is_null($allocation->submissionid)) {
            $o .= $this->withoutsubmission;
        }
        $o .= html_writer::start_tag('ul', array());
        foreach ($allocation->reviewerof as $authorid => $assessmentid) {
            $o .= html_writer::start_tag('li', array());
            $o .= $this->user_picture_cache($userinfo, $authorid, true);
            $o .= $this->fullname_cache($userinfo, $authorid);
            $o .= $this->calibrationscore_cache($authorid);
            $o .= html_writer::end_tag('li');
        }
        $o .= html_writer::end_tag('ul');
        $o .= html_writer::tag('p',
            sprintf($this->revieweenumber, count($allocation->reviewerof)),
            array('class' => 'revieweenumber')
        );
        return $o;
    }
}
