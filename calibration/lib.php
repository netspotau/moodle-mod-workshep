<?php

/**
 * This file defines the interface of all calibration classes
 *
 * @package    mod
 * @subpackage workshep
 * @copyright  2014 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

interface workshep_calibration_method {

    public function __construct(workshep $workshep);

    public function calculate_calibration_scores(stdClass $settings);

    //You are strongly encouraged to maintain a (sensibly sized) in memory
    //cache of the calibration scores after a call to calculate_calibration_scores
    //and use that whenever possible to return values for these functions.
    public function get_calibration_scores();

    public function get_calibration_score_for_user($userid);

    // Returns a moodle_url object for a page containing relevant calibration
    // information for this user.
    public function user_calibration_url($userid);

    // These methods are optional - unfortunately PHP has no way of marking
    // optional methods in an interface. So, you can safely implement these methods
    // with just an empty pair of braces and return nothing.

    public function prepare_grade_breakdown($userid);

    public function get_settings_form(moodle_url $actionurl);

}
