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
 * Implementaton of the quizaccess_eproctoring plugin.
 *
 * @package   quizaccess_eproctoring
 * @copyright 2018 eProctoring.com - Edu Labs Moodle Partner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');


/**
 * A rule requiring the student to promise not to cheat.
 *
 * @copyright  2018 eProctoring.com - Edu Labs Moodle Partner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_eproctoring extends quiz_access_rule_base {


//////////
//////////
//////////
// Print validate identity form
///////////
//////////
///////// 

public function description() {
 
   //Starts validate var session
   $_SESSION['validated'] = 0;
  
  global $USER,$DB,$CFG,$COURSE,$USER;
  //Get validation vars
  $webcampicture = optional_param('webcampicture','',PARAM_TEXT);
  $id = optional_param('id','',PARAM_INT);
  $contextmodule = context_module::instance($id, MUST_EXIST);
  $contextquiz = $DB->get_record('course_modules',array('id'=>$contextmodule->instanceid));

  ////////////////////
  ///////////////////
  ///////////////////
  // Validate identity
  //////////////////
  /////////////////
  /////////////////
  
  if ($_SESSION['validated'] == 0 and $webcampicture != null)
  {
 
  
  //Get user profile
        
            $contd = $DB->get_record('files',array('id'=>$USER->picture));
            $cont = $DB->get_record('files',array('contextid'=>$contd->contextid,'component'=>'user','filearea'=>'icon','filename'=>'f3.png'));
            $fs = get_file_storage();
 
    // Prepare file record object
    $fileinfo = array('component' => 'user','filearea' => 'icon','itemid' => $cont->itemid,'contextid' => $cont->contextid,'filepath' => '/','filename' => $cont->filename); 

    // Get file
    $file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
                      $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);

    $image = imagecreatefromstring($file->get_content());
    
    // start buffering, otherwise we will get some trouble to create base64 image
    ob_start();
    imagepng($image);
    $imgcontents =  ob_get_contents();
    ob_end_clean();
    //Create base64 picture from profile
    $user_profile_picture = base64_encode($imgcontents);
    
    //Validate identity posting data to eProctoring API. It is mandatory to use urlencode, otherwise pictures will be truncated
                
                $curl_connection = curl_init('https://eproctoring.edu-labs.co/validate_identity.php');
                
                curl_setopt($curl_connection, CURLOPT_CONNECTTIMEOUT, 90);
                curl_setopt($curl_connection, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");

                curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl_connection, CURLOPT_HTTPHEADER, array('Expect:'));
                curl_setopt($curl_connection, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl_connection, CURLOPT_FOLLOWLOCATION, 0);
                $post_data['url'] = $CFG->wwwroot;
                $post_data['user_webcam_photo'] = urlencode($webcampicture);
                $post_data['user_profile_picture'] = urlencode($user_profile_picture);
                $post_data['userid'] = $USER->id;
                $post_data['courseid'] = $COURSE->id;
                $post_data['quizid'] = $contextquiz->instance;


                foreach ( $post_data as $key => $value) {$post_items[] = $key . '=' . $value;}
                $post_string = implode ('&', $post_items);
                curl_setopt($curl_connection, CURLOPT_POSTFIELDS, $post_string);
                $validating = curl_exec($curl_connection);
                curl_close($curl_connection);

                //Save both pictures in Moodledata for further reports (eProctoring doesn't store your data)
                
                if (!file_exists($CFG->dataroot.'/eproctoring/')) {
                mkdir($CFG->dataroot.'/eproctoring/', 0777, true);
                }
                //Webcam picture
                $webcampicture = str_replace('data:image/png;base64,', '', $webcampicture);
                file_put_contents($CFG->dataroot.'/eproctoring/webcam-'.$USER->id.'-'.$COURSE->id.'-'.$contextquiz->instance.'-'.time().'.png', base64_decode($webcampicture));
                //Profile picture, already encoded
                file_put_contents($CFG->dataroot.'/eproctoring/profile-'.$USER->id.'-'.$COURSE->id.'-'.$contextquiz->instance.'-'.time().'.png', base64_decode($user_profile_picture));

                ////////
                //////// Return validation results
                ////////
                
                //This is what happens if validation is susccessful

                if (strpos($validating, 'True') !== false){ 
                    $_SESSION['validated'] = 1;
                    $success_validation = '<div class="box generalbox m-b-1 adminerror alert alert-success p-y-1">'.get_string('identityvalidated', 'quizaccess_eproctoring').'</div>';
                    //Stores record on database for reporting
                    $success = new stdClass();
                    $success->customerid = $checklicense->id;
                    $success->courseid = $COURSE->id;
                    $success->quizid = $contextquiz->instance;
                    $success->userid = $USER->id;
                    $success->profilepicture = 'profile-'.$USER->id.'-'.$COURSE->id.'-'.$contextquiz->instance.'-'.time().'.png';
                    $success->webcampicture = 'webcam-'.$USER->id.'-'.$COURSE->id.'-'.$contextquiz->instance.'-'.time().'.png';
                    $success->status = 1;
                    $success->timemodified = time();

                    $DB->insert_record('quizaccess_eproctoring_logs',$success);
                    
                    //Return validation message
                    return $success_validation;
                    
                }

                //This is what happens if validation is not susccessful                
                
                else{
                $_SESSION['validated'] = 0;
                //Stores record on database for reporting
                    $error = new stdClass();
                    $error->customerid = $checklicense->id;
                    $error->courseid = $COURSE->id;
                    $error->quizid = $contextquiz->instance;
                    $error->userid = $USER->id;
                    $error->profilepicture = 'profile-'.$USER->id.'-'.$COURSE->id.'-'.$contextquiz->instance.'-'.time().'.png';
                    $error->webcampicture = 'webcam-'.$USER->id.'-'.$COURSE->id.'-'.$contextquiz->instance.'-'.time().'.png';
                    $error->status = 0;
                    $error->timemodified = time();

                    $DB->insert_record('quizaccess_eproctoring_logs',$error);
                }
            
  }
  
  ////////////////////
  ///////////////////
  ///////////////////
  // Print validation form
  //////////////////
  /////////////////
  /////////////////
  
  if ($_SESSION['validated'] == 0)
  {
    global $CFG,$DB;
    //We need just a few data to register your Moodle site
    //Get site name
    $site = $DB->get_record('course',array('id'=>1));   
    $sitename = urlencode($site->fullname);
    
    $arraychecklicense=array("ssl"=>array("verify_peer"=>false,"verify_peer_name"=>false,));
    
    $checklicense = file_get_contents("https://eproctoring.edu-labs.co/checklicense.php?url=$CFG->wwwroot", false, stream_context_create($arraychecklicense));
    
        if (strpos($checklicense, 'True') == false) {
     
    $arrayregister=array("ssl"=>array("verify_peer"=>false,"verify_peer_name"=>false,));

    $register = file_get_contents("https://eproctoring.edu-labs.co/register.php?url=$CFG->wwwroot&moodleversion=$CFG->version&customer=$sitename", false, stream_context_create($arrayregister));
    
        }
    
    $validatebuttontext = get_string('step2validate','quizaccess_eproctoring');
    $photobuttontext = get_string('step1snap','quizaccess_eproctoring');

$scripting = file_get_contents('https://eproctoring.edu-labs.co/launcher.php?url='.$CFG->wwwroot.'&photobuttontext='.$photobuttontext.'&validatebuttontext='.$validatebuttontext.'');
$validation_content = '<div class="box generalbox m-b-1 adminerror alert alert-danger p-y-1">'.get_string('errorvalidation', 'quizaccess_eproctoring').'</div><div class="alert alert-info alert-block fade in " role="alert">'.get_string('eproctoringstatement', 'quizaccess_eproctoring').'</div><style>@import url("https://eproctoring.edu-labs.co/free-css.css");</style>'.$scripting.'';
        
        //Print validation reports link for admin and teacher
        $context = context_course::instance($COURSE->id, MUST_EXIST);

        if (has_capability('mod/quiz:grade', $context, $USER->id)){
        $reportlink = '<div id="eproctoring_reports" style="float:right;"><a href="accessrule/eproctoring/report.php?quizid='.$contextquiz->instance.'&courseid='.$COURSE->id.'" target="_blank">'.get_string('reportlink','quizaccess_eproctoring').'</a></div><br /><br />';    
        }
        else
        {
        $reportlink = ' ';    
        }
        return $reportlink.$validation_content;
        return get_string('eproctoringstatement', 'quizaccess_eproctoring');
    
  }   
}  


//////////
//////////
//////////
// Prevent access
///////////
//////////
/////////
    
    
/**
     * Checks if user is the same as the profile picture
     *
     * @return true if user's photo is the same as profile picture, else false. Must be different than zero
     */
        
        public function prevent_access() {
           
        
        if ($_SESSION['validated'] == 0) {
$error_validation = ' ';
            return $error_validation;
            
        } else
        {
            
            return false;
            
        }
    }    
    
    
//////////
//////////
//////////
//Other settings
///////////
//////////
/////////
        public function setup_attempt_page($page) {
        $page->set_title($this->quizobj->get_course()->shortname . ': ' . $page->title);
        $page->set_cacheable(false);
        $page->set_popup_notification_allowed(false); // Prevent message notifications.
        $page->set_heading($page->title);
        $page->set_pagelayout('secure');
    }

    public static function make(quiz $quizobj, $timenow, $canignoretimelimits) {

        if (empty($quizobj->get_quiz()->eproctoringrequired)) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    public static function add_settings_form_fields(
            mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $mform->addElement('select', 'eproctoringrequired',
                get_string('eproctoringrequired', 'quizaccess_eproctoring'),
                array(
                    0 => get_string('notrequired', 'quizaccess_eproctoring'),
                    1 => get_string('eproctoringwbcamrequired', 'quizaccess_eproctoring'),
                ));
        $mform->addHelpButton('eproctoringrequired',
                'eproctoringrequired', 'quizaccess_eproctoring');
    }

    public static function save_settings($quiz) {
        global $DB;
        if (empty($quiz->eproctoringrequired)) {
            $DB->delete_records('quizaccess_eproctoring', array('quizid' => $quiz->id));
        } else {
            if (!$DB->record_exists('quizaccess_eproctoring', array('quizid' => $quiz->id))) {
                $record = new stdClass();
                $record->quizid = $quiz->id;
                $record->eproctoringrequired = 1;
                $DB->insert_record('quizaccess_eproctoring', $record);
            }
        }
    }

    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_eproctoring', array('quizid' => $quiz->id));
    }
    
        public static function get_settings_sql($quizid) {
        return array(
            'eproctoringrequired',
            'LEFT JOIN {quizaccess_eproctoring} eproctoring ON eproctoring.quizid = quiz.id',
            array());
    }
}