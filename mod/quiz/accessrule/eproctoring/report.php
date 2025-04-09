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
 * Version information for the quizaccess_eproctoring plugin.
 *
 * @package   quizaccess_eproctoring
 * @copyright 2018 eProctoring.com - Edu Labs Moodle Partner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include '../../../../config.php';
require_once($CFG->dirroot.'/lib/tablelib.php');

require_login();

global $CFG,$DB,$USER;

//Get vars

$courseid = optional_param('courseid','',PARAM_INT); 
$quizid = optional_param('quizid','',PARAM_INT); 
$studentid = optional_param('studentid','',PARAM_INT); 
$reportid = optional_param('reportid','',PARAM_INT); 

$context = context_module::instance($courseid, MUST_EXIST);

$COURSE = $DB->get_record('course',array('id'=>$courseid));
$quiz = $DB->get_record('quiz',array('id'=>$quizid));

$url = new moodle_url('/mod/quiz/accessrule/eproctoring/report.php', array('courseid'=>$courseid,'userid'=>$userid,'quizid'=>$quizid));
$PAGE->set_url($url);
$PAGE->set_pagelayout('course');
$PAGE->set_title($COURSE->shortname .': '.get_string('pluginname', 'quizaccess_eproctoring'));
$PAGE->set_heading($COURSE->fullname.': '.get_string('pluginname', 'quizaccess_eproctoring'));
echo $OUTPUT->header();

echo '<div id="main">
<h2>'.get_string('eprotroringreports', 'quizaccess_eproctoring').''.$quiz->name.'</h2>
<div class="box generalbox m-b-1 adminerror alert alert-info p-y-1">'.get_string('eprotroringreportsdesc', 'quizaccess_eproctoring').'</div>
';

if (has_capability('mod/quiz:grade', $context, $USER->id) and $quizid != null and $courseid != null){


//Check if report if for some user

if ($studentid != null and $quizid != null and $courseid != null and $reportid != null)
{
    
    //Report for this user
    
    $sql = "SELECT e.id as reportid,e.userid as studentid,e.profilepicture as profilepicture,e.webcampicture as webcampicture,e.status as status,e.timemodified as timemodified,u.firstname as firstname,u.lastname as lastname,u.email as email from  {quizaccess_eproctoring_logs} e INNER JOIN {user} u WHERE u.id = e.userid AND e.courseid = '$courseid' AND e.quizid = '$quizid' AND u.id = '$studentid' and e.id = '$reportid'";

}

if ($studentid == null and $quizid != null and $courseid != null)
{
    //Report for all users
    
    $sql = "SELECT e.id as reportid,e.userid as studentid,e.profilepicture as profilepicture,e.webcampicture as webcampicture,e.status as status,e.timemodified as timemodified,u.firstname as firstname,u.lastname as lastname,u.email as email from  {quizaccess_eproctoring_logs} e INNER JOIN {user} u WHERE u.id = e.userid AND e.courseid = '$courseid' AND e.quizid = '$quizid'";

}

//Print report

$table = new flexible_table('eproctoring-report-'.$COURSE->id.'-'.$quizid);

    $table->course = $COURSE;

    $table->define_columns(array('fullname','email','status','dateverified','actions'));
    $table->define_headers(array (get_string('user'),get_string('email'),get_string('status','quizaccess_eproctoring'),get_string('dateverified','quizaccess_eproctoring'),get_string('actions','quizaccess_eproctoring')));
    $table->define_baseurl($url);

    $table->set_attribute('cellpadding','5');
    $table->set_attribute('class', 'generaltable generalbox reporttable');


    $table->setup();
    
    //Prepare data
    
    $sqlexecuted = $DB->get_recordset_sql($sql);
    
    $data = array();
    foreach($sqlexecuted as $info)
    {
    //Define status
    if ($info->status == 1){$validation_status = '<span style="color:blue;">'.get_string('statusyes','quizaccess_eproctoring').'</span>';}else{$validation_status = '<span style="color:red;">'.get_string('statusno','quizaccess_eproctoring').'</span>';}
    $data = array('<a href="'.$CFG->wwwroot.'/user/view.php?id='.$info->studentid.'&course='.$courseid.'" target="_blank">'.$info->firstname.' '.$info->lastname.'</a>',$info->email,$validation_status,date("Y/M/d H:m:s",$info->timemodified),'<a href="?courseid='.$courseid.'&quizid='.$quizid.'&studentid='.$info->studentid.'&reportid='.$info->reportid.'">'.get_string('picturesreport','quizaccess_eproctoring').'</a>');
    
    $table->add_data($data);    
    }
    


    //Print table
    
    
    $table->print_html();
    
    //Print image results
    
    if ($studentid != null and $quizid != null and $courseid != null and $reportid != null)
    {
        echo '<h3>'.get_string('picturesusedreport','quizaccess_eproctoring').'</h3>';

$tablepictures = new flexible_table('eproctoring-report-pictures'.$COURSE->id.'-'.$quizid);

    $tablepictures->course = $COURSE;

    $tablepictures->define_columns(array('profilepicture','webcamphoto'));
    $tablepictures->define_headers(array (get_string('profilepicture','quizaccess_eproctoring'),get_string('webcamphoto','quizaccess_eproctoring')));
    $tablepictures->define_baseurl($url);

    $tablepictures->set_attribute('cellpadding','5');
    $tablepictures->set_attribute('class', 'generaltable generalbox reporttable');


    $tablepictures->setup();
    
    //Prepare data
    
    $profilepicturebase64 = base64_encode(file_get_contents($CFG->dataroot.'/eproctoring/'.$info->profilepicture));
    $webcamphotobase64 = base64_encode(file_get_contents($CFG->dataroot.'/eproctoring/'.$info->webcampicture));
    
    $datapictures = array('<img src="data:image/png;base64, '.$profilepicturebase64.'" alt="'.get_string('profilepicture','quizaccess_eproctoring').' - '.$info->firstname.' '.$info->lastname.'" />','<img src="data:image/png;base64, '.$webcamphotobase64.'" alt="'.get_string('webcamphoto','quizaccess_eproctoring').' - '.$info->firstname.' '.$info->lastname.'" />');
    $tablepictures->add_data($datapictures); 
    $tablepictures->print_html();
    }

}
else{
    //User has not permissions to view this page
    echo '<div class="box generalbox m-b-1 adminerror alert alert-danger p-y-1">'.get_string('notpermissionreport', 'quizaccess_eproctoring').'</div>';
}
echo '</div>';
echo $OUTPUT->footer();