<?php

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__)))))."/config.php");
require_once("upload_form.php");
require_once("../../locallib.php");

$cm  = required_param('cm', PARAM_INT);
$cm = get_coursemodule_from_id('workshep',$cm);
require_login($cm->course);
$context = $PAGE->context;
require_capability('mod/workshep:allocate', $context);

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$workshep = $DB->get_record('workshep', array('id' => $cm->instance), '*', MUST_EXIST);
$workshep = new workshep($workshep, $cm, $course);

$form = $workshep->teammode ? new workshep_allocation_teammode_manual_upload_form() : new workshep_allocation_manual_upload_form();

if($form->exportValue('clear'))
{

    $vals = $DB->get_fieldset_select('workshep_submissions','id','workshepid = ?', array($workshep->id));
    list($p, $q) = $DB->get_in_or_equal($vals);
    $select = "submissionid $p AND grade is NULL";
    $DB->delete_records_select('workshep_assessments',$select,$q);

} else {

    $content = $form->get_file_content('file');
    $content = preg_replace('!\r\n?!', "\n", $content);
    $csv = array_map('str_getcsv',explode("\n",$content));

    $usernames = array();
    foreach($csv as $a) {
        $usernames = array_merge($usernames,$a);
    }

    $users = $DB->get_records_list('user','username',$usernames,'','username,id,firstname,lastname');

    $failures = array(); // username => reason
    
    foreach($csv as $a) {
        if(!empty($a)) {
            $reviewee = trim($a[0]);
            $reviewers = array_slice($a,1);
            
            if (empty($reviewee)) continue;
            if (empty($reviewers)) continue;
            
            if (empty($users[$reviewee])) {
                
                $failures[$reviewee] = "error::No user for username $reviewee";
                continue;
            }
            
            $submission = $workshep->get_submission_by_author($users[$reviewee]->id);
            
            if ($submission === false) {
                $failures[$reviewee] = "error::No submission for {$users[$reviewee]->firstname} {$users[$reviewee]->lastname} ($reviewee)";
                continue;
            }
            
            foreach($reviewers as $i) {
                if (empty($i)) continue;
                if (empty($users[$i])) {
                    $failures[$i] = "error::No user for username $i";
                } else if (!$workshep->useselfassessment && $reviewee == $i) {
                    $failures[$i] = "info::Self-assessment is disabled for this workshop. {$users[$reviewee]->firstname} {$users[$reviewee]->lastname} ($i) was not allocated to assess their own submission.";
                } else {
                    $res = $workshep->add_allocation($submission, $users[$i]->id);
                }
            }
        }
    }

    $SESSION->workshep_upload_messages = $failures;
}

$url = new moodle_url('/mod/workshep/allocation.php', array('cmid' => $cm->id, 'method' => 'manual'));
redirect($url);