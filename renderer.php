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
 * Workshop module renderering methods are defined here
 *
 * @package    mod_workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Workshop module renderer class
 *
 * @copyright 2009 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_workshep_renderer extends plugin_renderer_base {
    
    ////////////////////////////////////////////////////////////////////////////
    // External API - methods to render workshep renderable components
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Renders workshep message
     *
     * @param workshep_message $message to display
     * @return string html code
     */
    protected function render_workshep_message(workshep_message $message) {

        $text   = $message->get_message();
        $url    = $message->get_action_url();
        $label  = $message->get_action_label();

        if (empty($text) and empty($label)) {
            return '';
        }

        switch ($message->get_type()) {
        case workshep_message::TYPE_OK:
            $sty = 'ok';
            break;
        case workshep_message::TYPE_ERROR:
            $sty = 'error';
            break;
        default:
            $sty = 'info';
        }

        $o = html_writer::tag('span', $message->get_message());

        if (!is_null($url) and !is_null($label)) {
            $o .= $this->output->single_button($url, $label, 'get');
        }

        return $this->output->container($o, array('message', $sty));
    }


    /**
     * Renders full workshep submission
     *
     * @param workshep_submission $submission
     * @return string HTML
     */
    protected function render_workshep_submission(workshep_submission $submission) {
        global $CFG;

        $o  = '';    // output HTML code
        $anonymous = $submission->is_anonymous();
        $classes = 'submission-full';
        if ($anonymous || !empty($submission->group)) {
            $classes .= ' anonymous';
        }
        $o .= $this->output->container_start($classes);
        $o .= $this->output->container_start('header');

        $title = format_string($submission->title);

        if ($this->page->url != $submission->url) {
            $title = html_writer::link($submission->url, $title);
        }

        $o .= $this->output->heading($title, 3, 'title');

        if (!$anonymous) {
            if (!empty($submission->group)) {
                $byfullname = get_string('byfullname', 'workshep', array( "name" => $submission->group->name, "url" => ""));
                $oo = $this->output->container($byfullname, 'fullname');
            } else {
                $author = new stdClass();
                $additionalfields = explode(',', user_picture::fields());
                $author = username_load_fields_from_object($author, $submission, 'author', $additionalfields);

                $userpic            = $this->output->user_picture($author, array('courseid' => $this->page->course->id, 'size' => 64));
                $userurl            = new moodle_url('/user/view.php',
                                                array('id' => $author->id, 'course' => $this->page->course->id));
                $a                  = new stdclass();
                $a->name            = fullname($author);
                $a->url             = $userurl->out();
                $byfullname         = get_string('byfullname', 'workshep', $a);
                $oo  = $this->output->container($userpic, 'picture');
                $oo .= $this->output->container($byfullname, 'fullname');
            }

            $o .= $this->output->container($oo, 'author');
        }

        $created = get_string('userdatecreated', 'workshep', userdate($submission->timecreated));
        $o .= $this->output->container($created, 'userdate created');

        if ($submission->timemodified > $submission->timecreated) {
            $modified = get_string('userdatemodified', 'workshep', userdate($submission->timemodified));
            $o .= $this->output->container($modified, 'userdate modified');
        }

        $o .= $this->output->container_end(); // end of header

        $o .= $this->helper_submission_content($submission);

        $o .= $this->output->container_end(); // end of submission-full

        return $o;
    }
    
    protected function helper_submission_content($submission) {
        global $CFG;

        $o = '';
        
        $content = file_rewrite_pluginfile_urls($submission->content, 'pluginfile.php', $this->page->context->id,
                                                        'mod_workshep', 'submission_content', $submission->id);
        $content = format_text($content, $submission->contentformat, array('overflowdiv'=>true));
        if (!empty($content)) {
            if (!empty($CFG->enableplagiarism)) {
                require_once($CFG->libdir.'/plagiarismlib.php');
                $content .= plagiarism_get_links(array('userid' => $submission->authorid,
                    'content' => $submission->content,
                    'cmid' => $this->page->cm->id,
                    'course' => $this->page->course));
            }
        }
        $o .= $this->output->container($content, 'content');

        $o .= $this->output->container($this->helper_submission_wordcount($content), 'wordcount');

        $o .= $this->helper_submission_attachments($submission->id, 'html');
        
        return $o;
    }

    /**
     * Renders short summary of the submission
     *
     * @param workshep_submission_summary $summary
     * @return string text to be echo'ed
     */
    protected function render_workshep_submission_summary(workshep_submission_summary $summary) {

        $o  = '';    // output HTML code
        $anonymous = $summary->is_anonymous();
        $classes = 'submission-summary';

        if ($anonymous) {
            $classes .= ' anonymous';
        }

        $gradestatus = '';

        if ($summary->status == 'notgraded') {
            $classes    .= ' notgraded';
            $gradestatus = $this->output->container(get_string('nogradeyet', 'workshep'), 'grade-status');

        } else if ($summary->status == 'graded') {
            $classes    .= ' graded';
            $gradestatus = $this->output->container(get_string('alreadygraded', 'workshep'), 'grade-status');
        }

        $o .= $this->output->container_start($classes);  // main wrapper
        $o .= html_writer::link($summary->url, format_string($summary->title), array('class' => 'title'));

        if (!$anonymous) {
            $author             = new stdClass();
            $additionalfields = explode(',', user_picture::fields());
            $author = username_load_fields_from_object($author, $summary, 'author', $additionalfields);
            $userpic            = $this->output->user_picture($author, array('courseid' => $this->page->course->id, 'size' => 35));
            $userurl            = new moodle_url('/user/view.php',
                                            array('id' => $author->id, 'course' => $this->page->course->id));
            $a                  = new stdClass();
            $a->name            = fullname($author);
            $a->url             = $userurl->out();
            $byfullname         = get_string('byfullname', 'workshep', $a);

            $oo  = $this->output->container($userpic, 'picture');
            $oo .= $this->output->container($byfullname, 'fullname');
            $o  .= $this->output->container($oo, 'author');
        }

        $created = get_string('userdatecreated', 'workshep', userdate($summary->timecreated));
        $o .= $this->output->container($created, 'userdate created');

        if ($summary->timemodified > $summary->timecreated) {
            $modified = get_string('userdatemodified', 'workshep', userdate($summary->timemodified));
            $o .= $this->output->container($modified, 'userdate modified');
        }

        $o .= $gradestatus;
        $o .= $this->output->container_end(); // end of the main wrapper
        return $o;
    }

    protected function render_workshep_group_submission_summary(workshep_group_submission_summary $summary) {
        $o  = '';    // output HTML code
        $anonymous = $summary->is_anonymous();
        $classes = 'submission-summary group-submission-summary';

        if ($anonymous) {
            $classes .= ' anonymous';
        }

        $gradestatus = '';

        if ($summary->status == 'notgraded') {
            $classes    .= ' notgraded';
            $gradestatus = $this->output->container(get_string('nogradeyet', 'workshep'), 'grade-status');

        } else if ($summary->status == 'graded') {
            $classes    .= ' graded';
            $gradestatus = $this->output->container(get_string('alreadygraded', 'workshep'), 'grade-status');
        }

        $o .= $this->output->container_start($classes);  // main wrapper
        $o .= html_writer::link($summary->url, format_string($summary->title), array('class' => 'title'));

        if (!$anonymous) {
            $a                  = new stdClass();
            $url                = new moodle_url('/group/overview.php',
                                            array('id' => $this->page->course->id, 'group' => $summary->group->id));
            $a->name            = $summary->group->name;
            $a->url             = $url->out();

            $byfullname         = get_string('byfullname', 'workshep', $a);

            $oo = $this->output->container($byfullname, 'fullname');
            $o  .= $this->output->container($oo, 'author');
        }

        $created = get_string('userdatecreated', 'workshep', userdate($summary->timecreated));
        $o .= $this->output->container($created, 'userdate created');

        if ($summary->timemodified > $summary->timecreated) {
            $modified = get_string('userdatemodified', 'workshep', userdate($summary->timemodified));
            $o .= $this->output->container($modified, 'userdate modified');
        }

        $o .= $gradestatus;
        $o .= $this->output->container_end(); // end of the main wrapper
        return $o;

    }

    /**
     * Renders full workshep example submission
     *
     * @param workshep_example_submission $example
     * @return string HTML
     */
    protected function render_workshep_example_submission(workshep_example_submission $example) {

        $o  = '';    // output HTML code
        $classes = 'submission-full example';
        $o .= $this->output->container_start($classes);
        $o .= $this->output->container_start('header');
        $o .= $this->output->container(format_string($example->title), array('class' => 'title'));
        $o .= $this->output->container_end(); // end of header

        $content = file_rewrite_pluginfile_urls($example->content, 'pluginfile.php', $this->page->context->id,
                                                        'mod_workshep', 'submission_content', $example->id);
        $content = format_text($content, $example->contentformat, array('overflowdiv'=>true));
        $o .= $this->output->container($content, 'content');

        $o .= $this->helper_submission_attachments($example->id, 'html');

        $o .= $this->output->container_end(); // end of submission-full

        return $o;
    }

    /**
     * Renders short summary of the example submission
     *
     * @param workshep_example_submission_summary $summary
     * @return string text to be echo'ed
     */
    protected function render_workshep_example_submission_summary(workshep_example_submission_summary $summary) {

        $o  = '';    // output HTML code

        // wrapping box
        $o .= $this->output->box_start('generalbox example-summary ' . $summary->status);

        // title
        $o .= $this->output->container_start('example-title');
        $o .= html_writer::link($summary->url, format_string($summary->title), array('class' => 'title'));

        if ($summary->editable) {
            $o .= $this->output->action_icon($summary->editurl, new pix_icon('i/edit', get_string('edit')));
        }
        $o .= $this->output->container_end();

        // additional info
        if ($summary->status == 'notgraded') {
            $o .= $this->output->container(get_string('nogradeyet', 'workshep'), 'example-info nograde');
        } else {
            $o .= $this->output->container(get_string('gradeinfo', 'workshep' , $summary->gradeinfo), 'example-info grade');
        }

        // button to assess
        $button = new single_button($summary->assessurl, $summary->assesslabel, 'get');
        $o .= $this->output->container($this->output->render($button), 'example-actions');

        // end of wrapping box
        $o .= $this->output->box_end();

        return $o;
    }

    /**
     * Renders the user plannner tool
     *
     * @param workshep_user_plan $plan prepared for the user
     * @return string html code to be displayed
     */
    protected function render_workshep_user_plan(workshep_user_plan $plan) {        
        $o  = '';    // Output HTML code.
        $width = 100 / count($plan->phases);
        $numberofphases = count($plan->phases);
        $o .= html_writer::start_tag('div', array(
            'class' => 'userplan',
            'aria-labelledby' => 'mod_workshep-userplanheading',
            'aria-describedby' => 'mod_workshep-userplanaccessibilitytitle',
        ));
        $o .= html_writer::span(get_string('userplanaccessibilitytitle', 'workshep', $numberofphases),
            'accesshide', array('id' => 'mod_workshep-userplanaccessibilitytitle'));
        $o .= html_writer::link('#mod_workshep-userplancurrenttasks', get_string('userplanaccessibilityskip', 'workshep'),
            array('class' => 'accesshide'));
        foreach ($plan->phases as $phasecode => $phase) {
            $o .= html_writer::start_tag('dl', array('class' => 'phase', 'style' => "width: $width%;"));
            $actions = '';

            if ($phase->active) {
                // Mark the section as the current one.
                $icon = $this->output->pix_icon('i/marked', '', 'moodle', ['role' => 'presentation']);
                $actions .= get_string('userplancurrentphase', 'workshep').' '.$icon;

            } else {
                // Display a control widget to switch to the given phase or mark the phase as the current one.
                foreach ($phase->actions as $action) {
                    if ($action->type === 'switchphase') {
                        if ($phasecode == workshep::PHASE_ASSESSMENT && $plan->workshep->phase == workshep::PHASE_SUBMISSION
                                && $plan->workshep->phaseswitchassessment) {
                            $icon = new pix_icon('i/scheduled', get_string('switchphaseauto', 'mod_workshep'));
                        } else {
                            $icon = new pix_icon('i/marker', get_string('switchphase'.$phasecode, 'mod_workshep'));
                        }
                        $actions .= $this->output->action_icon($action->url, $icon, null, null, true);
                    }
                }
            }

            if (!empty($actions)) {
                $actions = $this->output->container($actions, 'actions');
            }
            $classes = 'phase' . $phasecode;
            if ($phase->active) {
                $title = html_writer::span($phase->title, 'phasetitle', ['id' => 'mod_workshep-userplancurrenttasks']);
                $classes .= ' active';
            } else {
                $title = html_writer::span($phase->title, 'phasetitle');
                $classes .= ' nonactive';
            }
            $o .= html_writer::start_tag('dt', array('class' => $classes));
            $o .= $this->output->container($title . $actions);
            $o .= html_writer::start_tag('dd', array('class' => $classes. ' phasetasks'));
            $o .= $this->helper_user_plan_tasks($phase->tasks);
            $o .= html_writer::end_tag('dd');
            $o .= html_writer::end_tag('dl');
        }
        $o .= html_writer::end_tag('div');
        return $o;
    }

    /**
     * Renders the result of the submissions allocation process
     *
     * @param workshep_allocation_result $result as returned by the allocator's init() method
     * @return string HTML to be echoed
     */
    protected function render_workshep_allocation_result(workshep_allocation_result $result) {
        global $CFG;

        $status = $result->get_status();

        if (is_null($status) or $status == workshep_allocation_result::STATUS_VOID) {
            debugging('Attempt to render workshep_allocation_result with empty status', DEBUG_DEVELOPER);
            return '';
        }

        switch ($status) {
        case workshep_allocation_result::STATUS_FAILED:
            if ($message = $result->get_message()) {
                $message = new workshep_message($message, workshep_message::TYPE_ERROR);
            } else {
                $message = new workshep_message(get_string('allocationerror', 'workshep'), workshep_message::TYPE_ERROR);
            }
            break;

        case workshep_allocation_result::STATUS_CONFIGURED:
            if ($message = $result->get_message()) {
                $message = new workshep_message($message, workshep_message::TYPE_INFO);
            } else {
                $message = new workshep_message(get_string('allocationconfigured', 'workshep'), workshep_message::TYPE_INFO);
            }
            break;

        case workshep_allocation_result::STATUS_EXECUTED:
            if ($message = $result->get_message()) {
                $message = new workshep_message($message, workshep_message::TYPE_OK);
            } else {
                $message = new workshep_message(get_string('allocationdone', 'workshep'), workshep_message::TYPE_OK);
            }
            break;

        default:
            throw new coding_exception('Unknown allocation result status', $status);
        }

        // start with the message
        $o = $this->render($message);

        // display the details about the process if available
        $logs = $result->get_logs();
        if (is_array($logs) and !empty($logs)) {
            $o .= html_writer::start_tag('ul', array('class' => 'allocation-init-results'));
            foreach ($logs as $log) {
                if ($log->type == 'debug' and !$CFG->debugdeveloper) {
                    // display allocation debugging messages for developers only
                    continue;
                }
                $class = $log->type;
                if ($log->indent) {
                    $class .= ' indent';
                }
                $o .= html_writer::tag('li', $log->message, array('class' => $class)).PHP_EOL;
            }
            $o .= html_writer::end_tag('ul');
        }

        return $o;
    }

    /**
     * Renders the workshep grading report
     *
     * @param workshep_grading_report $gradingreport
     * @return string html code
     */
    protected function render_workshep_grading_report(workshep_grading_report $gradingreport) {

        $data       = $gradingreport->get_data();
        $options    = $gradingreport->get_options();
        $grades     = $data->grades;
        $userinfo   = $data->userinfo;

        if (empty($grades)) {
            return '';
        }

        $table = new html_table();
        $table->attributes['class'] = 'grading-report';

        $sortbyfirstname = $this->helper_sortable_heading(get_string('firstname'), 'firstname', $options->sortby, $options->sorthow);
        $sortbylastname = $this->helper_sortable_heading(get_string('lastname'), 'lastname', $options->sortby, $options->sorthow);
        if (self::fullname_format() == 'lf') {
            $sortbyname = $sortbylastname . ' / ' . $sortbyfirstname;
        } else {
            $sortbyname = $sortbyfirstname . ' / ' . $sortbylastname;
        }

        $sortbysubmisstiontitle = $this->helper_sortable_heading(get_string('submission', 'workshep'), 'submissiontitle',
                $options->sortby, $options->sorthow);
        $sortbysubmisstionlastmodified = $this->helper_sortable_heading(get_string('submissionlastmodified', 'workshep'),
                'submissionmodified', $options->sortby, $options->sorthow);
        $sortbysubmisstion = $sortbysubmisstiontitle . ' / ' . $sortbysubmisstionlastmodified;

        $table->head = array();
        $table->head[] = $sortbyname;
        $table->head[] = $sortbysubmisstion;

        // If we are in submission phase ignore the following headers (columns).
        if (!empty($options->workshepphase) && $options->workshepphase != workshep::PHASE_SUBMISSION) {
            $table->head[] = $this->helper_sortable_heading(get_string('receivedgrades', 'workshep'));
            if ($options->showsubmissiongrade) {
                $table->head[] = $this->helper_sortable_heading(get_string('submissiongradeof', 'workshep', $data->maxgrade),
                        'submissiongrade', $options->sortby, $options->sorthow);
            }
            $table->head[] = $this->helper_sortable_heading(get_string('givengrades', 'workshep'));
            if ($options->showgradinggrade) {
                $table->head[] = $this->helper_sortable_heading(get_string('gradinggradeof', 'workshep', $data->maxgradinggrade),
                        'gradinggrade', $options->sortby, $options->sorthow);
            }
        }
        $table->rowclasses  = array();
        $table->colclasses  = array();
        $table->data        = array();

        foreach ($grades as $participant) {
            $numofreceived  = count($participant->reviewedby);
            $numofgiven     = count($participant->reviewerof);
            $published      = $participant->submissionpublished;

            // compute the number of <tr> table rows needed to display this participant
            if ($numofreceived > 0 and $numofgiven > 0) {
                $numoftrs       = workshep::lcm($numofreceived, $numofgiven);
                $spanreceived   = $numoftrs / $numofreceived;
                $spangiven      = $numoftrs / $numofgiven;
            } elseif ($numofreceived == 0 and $numofgiven > 0) {
                $numoftrs       = $numofgiven;
                $spanreceived   = $numoftrs;
                $spangiven      = $numoftrs / $numofgiven;
            } elseif ($numofreceived > 0 and $numofgiven == 0) {
                $numoftrs       = $numofreceived;
                $spanreceived   = $numoftrs / $numofreceived;
                $spangiven      = $numoftrs;
            } else {
                $numoftrs       = 1;
                $spanreceived   = 1;
                $spangiven      = 1;
            }

            for ($tr = 0; $tr < $numoftrs; $tr++) {
                $row = new html_table_row();
                if ($published) {
                    $row->attributes['class'] = 'published';
                }
                // column #1 - participant - spans over all rows
                if ($tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_participant($participant, $userinfo);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'participant';
                    $row->cells[] = $cell;
                }
                // column #2 - submission - spans over all rows
                if ($tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_submission($participant);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'submission';
                    $row->cells[] = $cell;
                }

                // If we are in submission phase ignore the following columns.
                if (!empty($options->workshepphase) && $options->workshepphase == workshep::PHASE_SUBMISSION) {
                    $table->data[] = $row;
                    continue;
                }

                // column #3 - received grades
                if ($tr % $spanreceived == 0) {
                    $idx = intval($tr / $spanreceived);
                    $assessment = self::array_nth($participant->reviewedby, $idx);
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_assessment($assessment, $options->showreviewernames, $userinfo,
                            get_string('gradereceivedfrom', 'workshep'));
                    $cell->rowspan = $spanreceived;
                    $cell->attributes['class'] = 'receivedgrade';
                    if (is_null($assessment) or is_null($assessment->grade)) {
                        $cell->attributes['class'] .= ' null';
                    } else {
                        $cell->attributes['class'] .= ' notnull';
                        if ($assessment->flagged and !empty($options->showdiscrepancy)) {
                            $cell->attributes['class'] .= ' flagged';
                            $cell->attributes['title'] = "This grade is more than 2 standard deviations from the median. Consider reviewing this assessment.";
                        }
                        if ($assessment->submitterflagged == 1) {
                            $cell->attributes['class'] .= ' flagged submitter';
                            $cell->attributes['title'] = "This assessment has been flagged by its submitter as unfair. Please review this assessment."; //consider concatenating with previous.
                        }
                    }
                    $row->cells[] = $cell;
                }
                // column #4 - total grade for submission
                if ($options->showsubmissiongrade and $tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_grade($participant->submissiongrade, $participant->submissiongradeover);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'submissiongrade';
                    $row->cells[] = $cell;
                }
                // column #5 - given grades
                if ($tr % $spangiven == 0) {
                    $idx = intval($tr / $spangiven);
                    $assessment = self::array_nth($participant->reviewerof, $idx);
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_assessment($assessment, $options->showauthornames, $userinfo,
                            get_string('gradegivento', 'workshep'));
                    $cell->rowspan = $spangiven;
                    $cell->attributes['class'] = 'givengrade';
                    if (is_null($assessment) or is_null($assessment->grade)) {
                        $cell->attributes['class'] .= ' null';
                    } else {
                        $cell->attributes['class'] .= ' notnull';
                    }
                    
                    $row->cells[] = $cell;
                }
                // column #6 - total grade for assessment
                if ($options->showgradinggrade and $tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_grade($participant->gradinggrade);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'gradinggrade';
                    $row->cells[] = $cell;
                }

                $table->data[] = $row;
            }
        }

        return html_writer::table($table);
    }


    /**
    * Renders the workshep grading report for visible groups mode
    *
    * @param workshep_grouped_grading_report $gradingreport
    * @return string HTML
    */

    protected function render_workshep_grouped_grading_report(workshep_grouped_grading_report $gradingreport) {
        //todo
        $data       = $gradingreport->get_data();
        $options    = $gradingreport->get_options();
        $grades     = $data->grades;
        $userinfo   = $data->userinfo;

        if (empty($grades)) {
            return '';
        }

        $table = new html_table();
        $table->attributes['class'] = 'grading-report grouped';

        $table->head = array();
        $table->head[] = $this->helper_sortable_heading(get_string('groupname','group'), 'name', $options->sortby, $options->sorthow);
        $table->head[] = $this->helper_sortable_heading(get_string('submission', 'workshep'), 'submissiontitle',
                $options->sortby, $options->sorthow);
        $table->head[] = $this->helper_sortable_heading(get_string('receivedgrades', 'workshep'));
        if ($options->showgradinggrade) {
            $table->head[] = $this->helper_sortable_heading(get_string('gradinggradeof', 'workshep', $data->maxgradinggrade),
                    'gradinggrade', $options->sortby, $options->sorthow);
        }
        if ($options->showsubmissiongrade) {
            $table->head[] = $this->helper_sortable_heading(get_string('submissiongradeof', 'workshep', $data->maxgrade),
                    'submissiongrade', $options->sortby, $options->sorthow);
        }

        $table->rowclasses  = array();
        $table->colclasses  = array();
        $table->data        = array();

        foreach ($grades as $participant) {
            $numofreceived  = count($participant->reviewedby);
            $published      = $participant->submissionpublished;

            // compute the number of <tr> table rows needed to display this participant
            if ($numofreceived > 0) {
                $numoftrs       = $numofreceived;
                $spanreceived   = $numoftrs / $numofreceived;
            } else {
                $numoftrs       = 1;
                $spanreceived   = 1;
            }

            for ($tr = 0; $tr < $numoftrs; $tr++) {
                $row = new html_table_row();
                if ($published) {
                    $row->attributes['class'] = 'published';
                }
                // column #1 - participant - spans over all rows
                if ($tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $participant->name;
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'participant';
                    $row->cells[] = $cell;
                }
                // column #2 - submission - spans over all rows
                if ($tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_submission($participant);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'submission';
                    $row->cells[] = $cell;
                }
                // column #3 - received grades
                if ($tr % $spanreceived == 0) {
                    $idx = intval($tr / $spanreceived);
                    $assessment = self::array_nth($participant->reviewedby, $idx);
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_assessment($assessment, $options->showreviewernames, $userinfo,
                            get_string('gradereceivedfrom', 'workshep'));
                    $cell->rowspan = $spanreceived;
                    $cell->attributes['class'] = 'receivedgrade';
                    if (is_null($assessment) or is_null($assessment->grade)) {
                        $cell->attributes['class'] .= ' null';
                    } else {
                        $cell->attributes['class'] .= ' notnull';
                        if ($assessment->flagged and !empty($options->showdiscrepancy)) {
                            $cell->attributes['class'] .= ' flagged';
                            $cell->attributes['title'] = "This grade is more than 2 standard deviations from the median. Consider reviewing this assessment.";
                        }
                        if ($assessment->submitterflagged == 1) {
                            $cell->attributes['class'] .= ' flagged submitter';
                            $cell->attributes['title'] = "This assessment has been flagged by its submitter as unfair. Please review this assessment."; //consider concatenating with previous.
                        }
                    }
                    $row->cells[] = $cell;
                }
                // column #6 - total grade for assessment for markers
                if ($options->showgradinggrade and $tr % $spanreceived == 0) {
                    $idx = intval($tr / $spanreceived);
                    $assessment = self::array_nth($participant->reviewedby, $idx);
                    $cell = new html_table_cell();

                    if($assessment) {
                        $gradinggrade = empty($userinfo[$assessment->userid]->gradinggrade) ? null : $userinfo[$assessment->userid]->gradinggrade;

                       $cell->text = $this->helper_grading_report_grade($gradinggrade);
                       $cell->rowspan = $spanreceived;
                       $cell->attributes['class'] = 'gradinggrade';
                    }
                    $row->cells[] = $cell;
                }
                // column #4 - total grade for submission
                if ($options->showsubmissiongrade and $tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_grade($participant->submissiongrade, $participant->submissiongradeover);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'submissiongrade';
                    $row->cells[] = $cell;
                }

                $table->data[] = $row;
            }
        }

        return html_writer::table($table);
    }


    /**
     * Renders the feedback for the author of the submission
     *
     * @param workshep_feedback_author $feedback
     * @return string HTML
     */
    protected function render_workshep_feedback_author(workshep_feedback_author $feedback) {
        return $this->helper_render_feedback($feedback);
    }

    /**
     * Renders the feedback for the reviewer of the submission
     *
     * @param workshep_feedback_reviewer $feedback
     * @return string HTML
     */
    protected function render_workshep_feedback_reviewer(workshep_feedback_reviewer $feedback) {
        return $this->helper_render_feedback($feedback);
    }

    /**
     * Helper method to rendering feedback
     *
     * @param workshep_feedback_author|workshep_feedback_reviewer $feedback
     * @return string HTML
     */
    private function helper_render_feedback($feedback) {

        $o  = '';    // output HTML code
        $o .= $this->output->container_start('feedback feedbackforauthor');
        $o .= $this->output->container_start('header');
        $o .= $this->output->heading(get_string('feedbackby', 'workshep', s(fullname($feedback->get_provider()))), 3, 'title');

        $userpic = $this->output->user_picture($feedback->get_provider(), array('courseid' => $this->page->course->id, 'size' => 32));
        $o .= $this->output->container($userpic, 'picture');
        $o .= $this->output->container_end(); // end of header

        $content = format_text($feedback->get_content(), $feedback->get_format(), array('overflowdiv' => true));
        $o .= $this->output->container($content, 'content');

        $o .= $this->output->container_end();

        return $o;
    }

    /**
     * Renders the full assessment
     *
     * @param workshep_assessment $assessment
     * @return string HTML
     */
    protected function render_workshep_assessment(workshep_assessment $assessment) {

        $o = ''; // output HTML code
        $anonymous = is_null($assessment->reviewer);
        $classes = 'assessment-full';
        if ($anonymous) {
            $classes .= ' anonymous';
        }

        $o .= $this->output->container_start($classes);
        $o .= $this->output->container_start('header');

        if (!empty($assessment->title)) {
            $title = s($assessment->title);
        } else {
            $title = get_string('assessment', 'workshep');
        }
        if (($assessment->url instanceof moodle_url) and ($this->page->url != $assessment->url)) {
            $o .= $this->output->container(html_writer::link($assessment->url, $title), 'title');
        } else {
            $o .= $this->output->container($title, 'title');
        }

        if (!$anonymous) {
            $reviewer   = $assessment->reviewer;
            $userpic    = $this->output->user_picture($reviewer, array('courseid' => $this->page->course->id, 'size' => 32));

            $userurl    = new moodle_url('/user/view.php',
                                       array('id' => $reviewer->id, 'course' => $this->page->course->id));
            $a          = new stdClass();
            $a->name    = fullname($reviewer);
            $a->url     = $userurl->out();
            $byfullname = get_string('assessmentby', 'workshep', $a);
            $oo         = $this->output->container($userpic, 'picture');
            $oo        .= $this->output->container($byfullname, 'fullname');

            $o .= $this->output->container($oo, 'reviewer');
        }

        if (is_null($assessment->realgrade)) {
            $o .= $this->output->container(
                get_string('notassessed', 'workshep'),
                'grade nograde'
            );
        } else {
            $a              = new stdClass();
            $a->max         = $assessment->maxgrade;
            $a->received    = $assessment->realgrade;
            $o .= $this->output->container(
                get_string('gradeinfo', 'workshep', $a),
                'grade'
            );

            if (!is_null($assessment->weight) and $assessment->weight != 1) {
                $o .= $this->output->container(
                    get_string('weightinfo', 'workshep', $assessment->weight),
                    'weight'
                );
            }
        }

        $created = get_string('userdatecreated', 'workshep', userdate($assessment->timecreated));
        $o .= $this->output->container($created, 'userdate created');

        if ($assessment->timemodified > $assessment->timecreated) {
            $modified = get_string('userdatemodified', 'workshep', userdate($assessment->timemodified));
            $o .= $this->output->container($modified, 'userdate modified');
        }

        $o .= $this->output->container_start('actions');
        foreach ($assessment->actions as $action) {
            $o .= $this->output->single_button($action->url, $action->label, $action->method);
        }
        $o .= $this->output->container_end(); // actions

        $o .= $this->output->container_end(); // header

        if ($assessment->submission) {
            
            $o .= $this->container($this->helper_submission_content($assessment->submission), 'submission-full');
        
        }

        if (!is_null($assessment->form)) {
            $o .= print_collapsible_region_start('assessment-form-wrapper', uniqid('workshep-assessment'),
                    get_string('assessmentform', 'workshep'), '', false, true);
            if (isset($assessment->reference_form)) {
                $o .= $this->output->container_start('center');

                $o .= $this->output->container_start('inline-block');
                $o .= $this->output->heading(get_string('assessmentreference','workshep'), 2, 'reference-assessment');
                $o .= $this->output->container(self::moodleform($assessment->reference_form));
                $o .= $this->output->container_end();

                $o .= $this->output->container_start('inline-block');
                $o .= $this->output->heading(get_string('assessmentbyfullname','workshep', fullname($assessment->reviewer)), 2, 'example-assessment');
                $o .= $this->output->container(self::moodleform($assessment->form));
                $o .= $this->output->container_end();

                $o .= $this->output->container_end();
            } else {
                $o .= $this->output->container(self::moodleform($assessment->form), 'assessment-form');
            }
            $o .= print_collapsible_region_end(true);

            if (!$assessment->form->is_editable()) {
                $o .= $this->overall_feedback($assessment);
            }
        }

        // Handle the flagged assessment resolution options
        
        // This is raw HTML because it's intended for use within a larger form
        // TODO: Localisation
        if ($assessment->resolution) {
            $o .= <<<HTML
            <div class="resolution">
    <input type="radio" name="assessment_{$assessment->id}" value="1">This assessment is fair</input><br/>
    <input type="radio" name="assessment_{$assessment->id}" value="0">This assessment is unfair and should be discounted</input>
</div>
HTML;
        }
        
        $o .= $this->output->container_end(); // main wrapper

        return $o;
    }

    /**
     * Renders the assessment of an example submission
     *
     * @param workshep_example_assessment $assessment
     * @return string HTML
     */
    protected function render_workshep_example_assessment(workshep_example_assessment $assessment) {
        return $this->render_workshep_assessment($assessment);
    }

    /**
     * Renders the reference assessment of an example submission
     *
     * @param workshep_example_reference_assessment $assessment
     * @return string HTML
     */
    protected function render_workshep_example_reference_assessment(workshep_example_reference_assessment $assessment) {
        return $this->render_workshep_assessment($assessment);
    }
    
    /// CALIBRATION REPORT
    
    protected function helper_calibration_report_reviewer(stdclass $reviewer) {
        $out  = $this->output->user_picture($reviewer, array('courseid' => $this->page->course->id, 'size' => 35));
        $out .= html_writer::tag('span', fullname($reviewer));

        return $out;
    }
    
    protected function render_workshep_calibration_report(workshep_calibration_report $report) {
        
        $options = $report->options;
        
        $table = new html_table();
        
        $sortbyfirstname = $this->helper_sortable_heading(get_string('firstname'), 'firstname', $options->sortby, $options->sorthow);
        $sortbylastname = $this->helper_sortable_heading(get_string('lastname'), 'lastname', $options->sortby, $options->sorthow);
        if (self::fullname_format() == 'lf') {
            $sortbyname = $sortbylastname . ' / ' . $sortbyfirstname;
        } else {
            $sortbyname = $sortbyfirstname . ' / ' . $sortbylastname;
        }

        $table->head = array();
        $table->head[] = $sortbyname;
        $table->head[] = $this->helper_sortable_heading(get_string('score', 'workshep'), 'score', $options->sortby, $options->sorthow);
        
        foreach($report->reviewers as $reviewer) {
            $row = new html_table_row();
            
            $reviewercell = new html_table_cell();
            $reviewercell->text = $this->helper_calibration_report_reviewer($reviewer, $report->reviewers);
            
            $row->cells[] = $reviewercell;
            
            $scorecell = new html_table_cell();
            $scorecell->text = isset($report->scores[$reviewer->id]) ? sprintf("%1.2f%%", $report->scores[$reviewer->id]) : get_string('nocalibrationscore', 'workshep');
            if (isset($report->scores[$reviewer->id]) && isset($reviewer->calibrationlink)) {
                $scorecell->text = html_writer::link($reviewer->calibrationlink, $scorecell->text);
            }
            
            $row->cells[] = $scorecell;
            
            $table->data[] = $row;
        }
        
        return html_writer::table($table);
        
    }

    /**
     * Renders the overall feedback for the author of the submission
     *
     * @param workshep_assessment $assessment
     * @return string HTML
     */
    protected function overall_feedback(workshep_assessment $assessment) {

        $o = '';

        if (($assessment instanceof workshep_example_assessment) && isset($assessment->reference_assessment)) {
            
            $yours = $this->inner_overall_feedback($assessment);
            $ref = $this->inner_overall_feedback($assessment->reference_assessment);
            
            if (( $yours === '' ) && ( $ref === '' )) {
                $o = '';
            } else {
            
                $o .= $this->output->container_start('center');

                $o .= $this->output->container_start('inline-block');
                $o .= $this->output->heading(get_string('assessmentreference','workshep'), 2, 'reference-assessment');
            
                $o .= $this->inner_overall_feedback($assessment->reference_assessment);
            
                $o .= $this->output->container_end();

                $o .= $this->output->container_start('inline-block');
                $o .= $this->output->heading(get_string('assessmentbyfullname','workshep', fullname($assessment->reviewer)), 2, 'example-assessment');
            
                $o .= $this->inner_overall_feedback($assessment);
            
                $o .= $this->output->container_end();
                $o .= $this->output->container_end();
            }
            
        } else {
            $o = $this->inner_overall_feedback($assessment);
        }
        
        if ($o === '') {
            return '';
        }

        $o = $this->output->box($o, 'overallfeedback');
        $o = print_collapsible_region($o, 'overall-feedback-wrapper', uniqid('workshep-overall-feedback'),
            get_string('overallfeedback', 'workshep'), '', false, true);

        return $o;
    }
    
    protected function inner_overall_feedback(workshep_assessment $assessment) {
        $content = $assessment->get_overall_feedback_content();

        if ($content === false) {
            return '';
        }

        $o = '';

        if (!is_null($content)) {
            $o .= $this->output->container($content, 'content');
        }

        $attachments = $assessment->get_overall_feedback_attachments();

        if (!empty($attachments)) {
            $o .= $this->output->container_start('attachments');
            $images = '';
            $files = '';
            foreach ($attachments as $attachment) {
                $icon = $this->output->pix_icon(file_file_icon($attachment), get_mimetype_description($attachment),
                    'moodle', array('class' => 'icon'));
                $link = html_writer::link($attachment->fileurl, $icon.' '.substr($attachment->filepath.$attachment->filename, 1));
                if (file_mimetype_in_typegroup($attachment->mimetype, 'web_image')) {
                    $preview = html_writer::empty_tag('img', array('src' => $attachment->previewurl, 'alt' => '', 'class' => 'preview'));
                    $preview = html_writer::tag('a', $preview, array('href' => $attachment->fileurl));
                    $images .= $this->output->container($preview);
                } else {
                    $files .= html_writer::tag('li', $link, array('class' => $attachment->mimetype));
                }
            }
            if ($images) {
                $images = $this->output->container($images, 'images');
            }

            if ($files) {
                $files = html_writer::tag('ul', $files, array('class' => 'files'));
            }

            $o .= $images.$files;
            $o .= $this->output->container_end();
        }

        if ($o === '') {
            return '';
        }
        
        return $o;
    }

    /**
     * Renders a perpage selector for workshep listings
     *
     * The scripts using this have to define the $PAGE->url prior to calling this
     * and deal with eventually submitted value themselves.
     *
     * @param int $current current value of the perpage parameter
     * @return string HTML
     */
    public function perpage_selector($current=10) {

        $options = array();
        foreach (array(10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 200, 300, 400, 500, 1000) as $option) {
            if ($option != $current) {
                $options[$option] = $option;
            }
        }
        $select = new single_select($this->page->url, 'perpage', $options, '', array('' => get_string('showingperpagechange', 'mod_workshep')));
        $select->label = get_string('showingperpage', 'mod_workshep', $current);
        $select->method = 'post';

        return $this->output->container($this->output->render($select), 'perpagewidget');
    }

    /**
     * Renders the user's final grades
     *
     * @param workshep_final_grades $grades with the info about grades in the gradebook
     * @return string HTML
     */
    protected function render_workshep_final_grades(workshep_final_grades $grades) {

        $out = html_writer::start_tag('div', array('class' => 'finalgrades'));

        if (!empty($grades->submissiongrade)) {
            $cssclass = 'grade submissiongrade';
            if ($grades->submissiongrade->hidden) {
                $cssclass .= ' hiddengrade';
            }
            $out .= html_writer::tag(
                'div',
                html_writer::tag('div', get_string('submissiongrade', 'mod_workshep'), array('class' => 'gradetype')) .
                html_writer::tag('div', $grades->submissiongrade->str_long_grade, array('class' => 'gradevalue')),
                array('class' => $cssclass)
            );
        }

        if (!empty($grades->assessmentgrade)) {
            $cssclass = 'grade assessmentgrade';
            if ($grades->assessmentgrade->hidden) {
                $cssclass .= ' hiddengrade';
            }
            $out .= html_writer::tag(
                'div',
                html_writer::tag('div', get_string('gradinggrade', 'mod_workshep'), array('class' => 'gradetype')) .
                html_writer::tag('div', $grades->assessmentgrade->str_long_grade, array('class' => 'gradevalue')),
                array('class' => $cssclass)
            );
        }

        $out .= html_writer::end_tag('div');

        return $out;
    }

    ////////////////////////////////////////////////////////////////////////////
    // Internal rendering helper methods
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Renders a list of files attached to the submission
     *
     * If format==html, then format a html string. If format==text, then format a text-only string.
     * Otherwise, returns html for non-images and html to display the image inline.
     *
     * @param int $submissionid submission identifier
     * @param string format the format of the returned string - html|text
     * @return string formatted text to be echoed
     */
    protected function helper_submission_attachments($submissionid, $format = 'html') {
        global $CFG;
        require_once($CFG->libdir.'/filelib.php');

        $fs     = get_file_storage();
        $ctx    = $this->page->context;
        $files  = $fs->get_area_files($ctx->id, 'mod_workshep', 'submission_attachment', $submissionid);

        $outputimgs     = '';   // images to be displayed inline
        $outputfiles    = '';   // list of attachment files

        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }

            $filepath   = $file->get_filepath();
            $filename   = $file->get_filename();
            $fileurl    = moodle_url::make_pluginfile_url($ctx->id, 'mod_workshep', 'submission_attachment',
                            $submissionid, $filepath, $filename, true);
            $embedurl   = moodle_url::make_pluginfile_url($ctx->id, 'mod_workshep', 'submission_attachment',
                            $submissionid, $filepath, $filename, false);
            $embedurl   = new moodle_url($embedurl, array('preview' => 'bigthumb'));
            $type       = $file->get_mimetype();
            $image      = $this->output->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon'));

            $linkhtml   = html_writer::link($fileurl, $image . substr($filepath, 1) . $filename);
            $linktxt    = "$filename [$fileurl]";

            if ($format == 'html') {
                if (file_mimetype_in_typegroup($type, 'web_image')) {
                    $preview     = html_writer::empty_tag('img', array('src' => $embedurl, 'alt' => '', 'class' => 'preview'));
                    $preview     = html_writer::tag('a', $preview, array('href' => $fileurl));
                    $outputimgs .= $this->output->container($preview);

                } else {
                    $outputfiles .= html_writer::tag('li', $linkhtml, array('class' => $type));
                }

            } else if ($format == 'text') {
                $outputfiles .= $linktxt . PHP_EOL;
            }

            if (!empty($CFG->enableplagiarism)) {
                require_once($CFG->libdir.'/plagiarismlib.php');
                $outputfiles .= plagiarism_get_links(array('userid' => $file->get_userid(),
                    'file' => $file,
                    'cmid' => $this->page->cm->id,
                    'course' => $this->page->course->id));
            }
        }

        if ($format == 'html') {
            if ($outputimgs) {
                $outputimgs = $this->output->container($outputimgs, 'images');
            }

            if ($outputfiles) {
                $outputfiles = html_writer::tag('ul', $outputfiles, array('class' => 'files'));
            }

            return $this->output->container($outputimgs . $outputfiles, 'attachments');

        } else {
            return $outputfiles;
        }
    }

    function helper_submission_wordcount($content) {
        $content = strip_tags($content);
        $content = html_entity_decode($content);
        $content = preg_replace('/\s\s+/',' ',$content);
        return get_string('wordcount','workshep',str_word_count($content));
    }

    /**
     * Renders the tasks for the single phase in the user plan
     *
     * @param stdClass $tasks
     * @return string html code
     */
    protected function helper_user_plan_tasks(array $tasks) {
        $out = '';
        foreach ($tasks as $taskcode => $task) {
            $classes = '';
            $accessibilitytext = '';
            $icon = null;
            if ($task->completed === true) {
                $classes .= ' completed';
                $accessibilitytext .= get_string('taskdone', 'workshep') . ' ';
            } else if ($task->completed === false) {
                $classes .= ' fail';
                $accessibilitytext .= get_string('taskfail', 'workshep') . ' ';
            } else if ($task->completed === 'info') {
                $classes .= ' info';
                $accessibilitytext .= get_string('taskinfo', 'workshep') . ' ';
            } else {
                $accessibilitytext .= get_string('tasktodo', 'workshep') . ' ';
            }
            if (is_null($task->link)) {
                $title = html_writer::tag('span', $accessibilitytext, array('class' => 'accesshide'));
                $title .= $task->title;
            } else {
                $title = html_writer::tag('span', $accessibilitytext, array('class' => 'accesshide'));
                $title .= html_writer::link($task->link, $task->title);
            }
            $title = $this->output->container($title, 'title');
            $details = $this->output->container($task->details, 'details');
            $out .= html_writer::tag('li', $title . $details, array('class' => $classes));
        }
        if ($out) {
            $out = html_writer::tag('ul', $out, array('class' => 'tasks'));
        }
        return $out;
    }

    /**
     * Renders a text with icons to sort by the given column
     *
     * This is intended for table headings.
     *
     * @param string $text    The heading text
     * @param string $sortid  The column id used for sorting
     * @param string $sortby  Currently sorted by (column id)
     * @param string $sorthow Currently sorted how (ASC|DESC)
     *
     * @return string
     */
    protected function helper_sortable_heading($text, $sortid=null, $sortby=null, $sorthow=null) {
        global $PAGE;

        $out = html_writer::tag('span', $text, array('class'=>'text'));

        if (!is_null($sortid)) {
            if ($sortby !== $sortid or $sorthow !== 'ASC') {
                $url = new moodle_url($PAGE->url);
                $url->params(array('sortby' => $sortid, 'sorthow' => 'ASC'));
                $out .= $this->output->action_icon($url, new pix_icon('t/sort_asc', get_string('sortasc', 'workshep')),
                    null, array('class' => 'iconsort sort asc'));
            }
            if ($sortby !== $sortid or $sorthow !== 'DESC') {
                $url = new moodle_url($PAGE->url);
                $url->params(array('sortby' => $sortid, 'sorthow' => 'DESC'));
                $out .= $this->output->action_icon($url, new pix_icon('t/sort_desc', get_string('sortdesc', 'workshep')),
                    null, array('class' => 'iconsort sort desc'));
            }
        }
        return $out;
}

    /**
     * @param stdClass $participant
     * @param array $userinfo
     * @return string
     */
    protected function helper_grading_report_participant(stdclass $participant, array $userinfo) {
        $userid = $participant->userid;
        $out  = $this->output->user_picture($userinfo[$userid], array('courseid' => $this->page->course->id, 'size' => 35));
        $out .= html_writer::tag('span', fullname($userinfo[$userid]));

        return $out;
    }

    /**
     * @param stdClass $participant
     * @return string
     */
    protected function helper_grading_report_submission(stdclass $participant) {
        global $CFG;

        if (is_null($participant->submissionid)) {
            $out = $this->output->container(get_string('nosubmissionfound', 'workshep'), 'info');
        } else {
            $url = new moodle_url('/mod/workshep/submission.php',
                                  array('cmid' => $this->page->context->instanceid, 'id' => $participant->submissionid));
            $out = html_writer::link($url, format_string($participant->submissiontitle), array('class'=>'title'));

            $lastmodified = get_string('userdatemodified', 'workshep', userdate($participant->submissionmodified));
            $out .= html_writer::tag('div', $lastmodified, array('class' => 'lastmodified'));
        }

        return $out;
    }

    /**
     * @todo Highlight the nulls
     * @param stdClass|null $assessment
     * @param bool $shownames
     * @param string $separator between the grade and the reviewer/author
     * @return string
     */
    protected function helper_grading_report_assessment($assessment, $shownames, array $userinfo, $separator, $suppressgradinggrade = false) {
        global $CFG;

        if (is_null($assessment)) {
            return get_string('nullgrade', 'workshep');
        }
        $a = new stdclass();
        $a->grade = is_null($assessment->grade) ? get_string('nullgrade', 'workshep') : $assessment->grade;
        $a->gradinggrade = is_null($assessment->gradinggrade) ? get_string('nullgrade', 'workshep') : $assessment->gradinggrade;
        $a->weight = $assessment->weight;
        // grrr the following logic should really be handled by a future language pack feature
        if (is_null($assessment->gradinggradeover)) {
            if ($suppressgradinggrade == true) {
                if ($a->weight == 1) {
                    $grade = get_string('formatpeergradenograding', 'workshep', $a);
                    $gradehelp = get_string('formatpeergradenogradinghovertext', 'workshep');
                } else {
                    $grade = get_string('formatpeergradeweightednograding', 'workshep', $a);
                    $gradehelp = get_string('formatpeergradeweightednogradinghovertext', 'workshep');
                }
            } else {
                if ($a->weight == 1) {
                    $grade = get_string('formatpeergrade', 'workshep', $a);
                    $gradehelp = get_string('formatpeergradehovertext', 'workshep');
                } else {
                    $grade = get_string('formatpeergradeweighted', 'workshep', $a);
                    $gradehelp = get_string('formatpeergradeweightedhovertext', 'workshep');
                }
            }
        } else {
            $a->gradinggradeover = $assessment->gradinggradeover;
            if ($a->weight == 1) {
                $grade = get_string('formatpeergradeover', 'workshep', $a);
                $gradehelp = get_string('formatpeergradeoverhovertext', 'workshep');
            } else {
                $grade = get_string('formatpeergradeoverweighted', 'workshep', $a);
                $gradehelp = get_string('formatpeergradeoverweightedhovertext', 'workshep');
            }
        }
        $url = new moodle_url('/mod/workshep/assessment.php',
                              array('asid' => $assessment->assessmentid));
        $grade = html_writer::link($url, $grade, array('class'=>'grade', 'title'=>$gradehelp));

        if ($shownames) {
            $userid = $assessment->userid;
            $name   = $this->output->user_picture($userinfo[$userid], array('courseid' => $this->page->course->id, 'size' => 16));
            $dimmed = !empty($userinfo[$userid]->suspended) ? ' dimmed_text' : '';
            $name  .= html_writer::tag('span', fullname($userinfo[$userid]), array('class' => 'fullname'.$dimmed));
            $name   = $separator . html_writer::tag('span', $name, array('class' => 'user'));
        } else {
            $name   = '';
        }

        return $this->output->container($grade . $name, 'assessmentdetails');
    }

    /**
     * Formats the aggreagated grades
     */
    protected function helper_grading_report_grade($grade, $over=null) {
        $a = new stdclass();
        $a->grade = is_null($grade) ? get_string('nullgrade', 'workshep') : $grade;
        if (is_null($over)) {
            $text = get_string('formataggregatedgrade', 'workshep', $a);
        } else {
            $a->over = is_null($over) ? get_string('nullgrade', 'workshep') : $over;
            $text = get_string('formataggregatedgradeover', 'workshep', $a);
        }
        return $text;
    }

    protected function render_workshep_random_examples_helper(workshep_random_examples_helper $helper) {
        $precision = ini_set('precision',4);
        $o = '';
        $infos = array();
        $problems = array();
        $titles = workshep_random_examples_helper::$descriptors[count($helper->slices)];

        $helptext = $this->output->heading_with_help("What does this mean?","randomexampleshelp",'workshep');

        foreach($helper->slices as $i => $s) {
            $o .= "<div class='slice' style='background-color: #$s->colour; width: $s->width; left: $s->min%'></div>";
            $o .= "<div class='mean' style='background-color: #$s->meancolour; left: $s->mean%'></div>";
            $count = count($s->submissions);
            $infos[] = "<div class='info'><h3 style='color:#$s->meancolour'>$titles[$i]</h3>Range $s->min% to $s->max% | Average $s->mean%</div>";
            if (isset($s->warnings))
                $problems = array_merge($problems, $s->warnings);
            foreach($s->submissions as $a) {
                $o .= "<div class='submission' style='background-color: #$s->subcolour; left:$a->grade%'></div>";
            }
        }
        $problemstr = "";
        if (count($problems)) {
            $problemstr = print_collapsible_region_start('random-examples-problems','random_examples_problems','Warnings','',true,true);
            $problemstr .= "<ul>";
            foreach($problems as $p)
                $problemstr .= "<li>$p</li>";
            $problemstr .= print_collapsible_region_end(true);
            $problemstr .= "</ul>";
        }
        ini_set('precision',$precision);
        return "$helptext<div class='random-examples-helper'>$o</div> <div class='random-examples-info'>" . implode($infos) . "</div>$problemstr";
    }


    ////////////////////////////////////////////////////////////////////////////
    // Static helpers
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Helper method dealing with the fact we can not just fetch the output of moodleforms
     *
     * @param moodleform $mform
     * @return string HTML
     */
    protected static function moodleform(moodleform $mform) {

        ob_start();
        $mform->display();
        $o = ob_get_contents();
        ob_end_clean();

        return $o;
    }

    /**
     * Helper function returning the n-th item of the array
     *
     * @param array $a
     * @param int   $n from 0 to m, where m is th number of items in the array
     * @return mixed the $n-th element of $a
     */
    protected static function array_nth(array $a, $n) {
        $keys = array_keys($a);
        if ($n < 0 or $n > count($keys) - 1) {
            return null;
        }
        $key = $keys[$n];
        return $a[$key];
    }

    /**
     * Tries to guess the fullname format set at the site
     *
     * @return string fl|lf
     */
    protected static function fullname_format() {
        $fake = new stdclass(); // fake user
        $fake->lastname = 'LLLL';
        $fake->firstname = 'FFFF';
        $fullname = get_string('fullnamedisplay', '', $fake);
        if (strpos($fullname, 'LLLL') < strpos($fullname, 'FFFF')) {
            return 'lf';
        } else {
            return 'fl';
        }
    }
}
