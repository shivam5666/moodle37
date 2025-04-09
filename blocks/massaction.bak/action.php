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
 * @package    block_massaction
 * @copyright  2011 University of Minnesota
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

require_login();

$instanceid         = required_param('instance_id', PARAM_INT);
$massactionrequest  = required_param('request', PARAM_TEXT);
$returnurl          = required_param('return_url', PARAM_TEXT);
$deletepreconfirm   = optional_param('del_preconfirm', 0, PARAM_BOOL);
$deleteconfirm      = optional_param('del_confirm', 0, PARAM_BOOL);

// Check capability.
$context = context_block::instance($instanceid);
$PAGE->set_context($context);
require_capability('block/massaction:use', $context);

// Parse the submitted data.
$data = json_decode($massactionrequest);

// Verify that the submitted module IDs do belong to the course.
if (!is_array($data->module_ids) || count($data->module_ids) == 0) {
    print_error('missingparam', 'block_massaction', 'Module ID');
}

$modulerecords = $DB->get_records_select('course_modules',
                                          'ID IN (' .
                                          implode(',', array_fill(0, count($data->module_ids), '?'))
                                          . ')', $data->module_ids);

$rebuildcourses = array();    // Keep track of courses to rebuild cache.

foreach ($data->module_ids as $modid) {
    if (!isset($modulerecords[$modid])) {
        print_error('invalidmoduleid', 'block_massaction', $modid);
    }

    $rebuildcourses[$modulerecords[$modid]->course] = true;
}

if (!isset($data->action)) {
    print_error('noaction', 'block_massaction');
}

// Dispatch the submitted action.
switch ($data->action) {
    case 'moveleft':
    case 'moveright':
        require_capability('moodle/course:manageactivities', $context);
        adjust_indentation($modulerecords, $data->action == 'moveleft' ? -1 : 1);
        break;

    case 'hide':
    case 'show':
        require_capability('moodle/course:activityvisibility', $context);
        set_visibility($modulerecords, $data->action == 'show');
        break;

    case 'delete':
        if ( !$deletepreconfirm ) {
            print_deletion_confirmation($modulerecords, 'preconfirm');
        } else {
            perform_deletion($modulerecords, $returnurl);
        }
        break;

    case 'moveto':
        if (!isset($data->moveto_target)) {
            print_error('missingparam', 'block_massaction', 'moveto_target');
        }
        perform_moveto($modulerecords, $data->moveto_target);
        break;

    case 'dupto':
        if (!isset($data->dupto_target)) {
            print_error('missingparam', 'block_massaction', 'dupto_target');
        }
        perform_dupto($modulerecords, $data->dupto_target);
        break;

    default:
        print_error('invalidaction', 'block_massaction', $data->action);
}

// Rebuild course cache.
foreach ($rebuildcourses as $courseid => $nada) {
    rebuild_course_cache($courseid);
}

/*
 * If we're doing anything other than attempting to delete activities, then redirecting here is
 * appropriate. This is because if we redirect before we rebuild the course cache, then some of
 * our actions (particularly indent/outdent) may not take effect or be reflected on the page.
 *
 * If we are trying to delete, then redirecting here is not appropriate because trying to do so
 * throws errors on the confirmation page. Instead, we need to redirect after we've actually
 * deleted the selected item(s).
 */
if ($data->action != 'delete') {
    redirect($returnurl);
}

/**
 * helper function to perform indentation/outdentation
 *
 * @param array $modules list of module records to modify
 * @param int $amount, 1 for indent, -1 for outdent
 *
 * @return void
 */
function adjust_indentation($modules, $amount) {
    global $DB;

    foreach ($modules as $cm) {
        $cm->indent += $amount;

        if ($cm->indent < 0) {
            $cm->indent = 0;
        }

        $DB->set_field('course_modules', 'indent', $cm->indent, array('id' => $cm->id));
    }
}

/**
 * helper function to set visibility
 *
 * @param array $modules list of module records to modify
 * @param bool $visible true to show, false to hide
 *
 * @return void
 */
function set_visibility($modules, $visible) {
    global $CFG;

    require_once($CFG->dirroot.'/course/lib.php');

    foreach ($modules as $cm) {
        set_coursemodule_visible($cm->id, $visible);
    }
}

/**
 * print out the list of course-modules to be deleted for confirmation
 *
 * @param array $modules
 * @param string $mode either 'preconfirm' or 'confirm'
 *
 * @return void
 */
function print_deletion_confirmation($modules, $mode = 'preconfirm') {
    global $DB, $PAGE, $OUTPUT, $CFG, $massactionrequest, $instanceid, $returnurl;

    $modulelist = array();

    foreach ($modules as $modulerecord) {
        if (!$cm = get_coursemodule_from_id('', $modulerecord->id, 0, true)) {
            print_error('invalidcoursemodule');
        }

        if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
            print_error('invalidcourseid');
        }

        $context     = context_course::instance($course->id);
        require_capability('moodle/course:manageactivities', $context);

        $fullmodulename = get_string('modulename', $cm->modname);

        $modulelist[] = array($fullmodulename, $cm->name);
    }

    $optionsyes = array('del_preconfirm'  => 1,
                         'instance_id'     => $instanceid,
                         'return_url'      => $returnurl,
                         'request'         => $massactionrequest);

    if ($mode == 'confirm') {
        $optionsyes['del_confirm'] = 1;
    }

    $optionsno  = array('id' => $cm->course);

    $strdeletecheck = get_string('deletecheck', 'block_massaction');

    require_login($course->id);

    $PAGE->requires->css('/blocks/massaction/styles.css');
    $PAGE->set_url(new moodle_url('/blocks/massaction/action.php'));
    $PAGE->set_title($strdeletecheck);
    $PAGE->set_heading($course->fullname);
    $PAGE->navbar->add($strdeletecheck);
    echo $OUTPUT->header();

    // Prep the content.
    if ($mode == 'preconfirm') {
        $content = get_string('deletecheckpreconfirm', 'block_massaction');
    }

    $content .= '<table id="block_massaction_module_list"><thead><th>Module name</th><th>Module type</th></thead><tbody>';
    foreach ($modulelist as $modulename) {
        $content .= "<tr><td>{$modulename[1]}</td><td>{$modulename[0]}</td></tr>";
    }
    $content .= '</tbody></table>';

    echo $OUTPUT->box_start('noticebox');
    $continuebutton = new single_button(new moodle_url("{$CFG->wwwroot}/blocks/massaction/action.php", $optionsyes),
                            get_string('delete'), 'post');
    $cancelbutton   = new single_button(new moodle_url("{$CFG->wwwroot}/course/view.php?id={$course->id}", $optionsno),
                            get_string('cancel'), 'get');
    echo $OUTPUT->confirm($content, $continuebutton, $cancelbutton);
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();

    return;
}

/**
 * perform the actual deletion of the selected course modules
 *
 * @param array $modules
 *
 * @return void
 */
function perform_deletion($modules, $returnurl) {
    global $CFG, $OUTPUT, $DB, $USER;

    require_once($CFG->dirroot.'/course/lib.php');

    foreach ($modules as $modulerecord) {
        if (!$cm = get_coursemodule_from_id('', $modulerecord->id, 0, true)) {
            print_error('invalidcoursemodule');
        }

        if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
            print_error('invalidcourseid');
        }

        $context     = context_course::instance($course->id);
        $modcontext  = context_module::instance($cm->id);
        require_capability('moodle/course:manageactivities', $context);

        $modlib = $CFG->dirroot.'/mod/'.$cm->modname.'/lib.php';

        if (file_exists($modlib)) {
            require_once($modlib);
        } else {
            print_error('modulemissingcode', '', '', $modlib);
        }

        if (function_exists('course_delete_module')) {
            // Available from Moodle 2.5.
            course_delete_module($cm->id);
        } else {
            // Pre Moodle 2.5.
            $deletefunction = $cm->modname."_delete_instance";

            if (!$deletefunction($cm->instance)) {
                echo $OUTPUT->notification("Could not delete the $cm->modname (instance)");
            }

            // Remove all module files in case modules forget to do that.
            $fs = get_file_storage();
            $fs->delete_area_files($modcontext->id);

            if (!delete_course_module($cm->id)) {
                echo $OUTPUT->notification("Could not delete the $cm->modname (coursemodule)");
            }

            if (!delete_mod_from_section($cm->id, $cm->section)) {
                echo $OUTPUT->notification("Could not delete the $cm->modname from that section");
            }

            // Trigger a mod_deleted event with information about this module.
            $eventdata = new stdClass();
            $eventdata->modulename = $cm->modname;
            $eventdata->cmid       = $cm->id;
            $eventdata->courseid   = $course->id;
            $eventdata->userid     = $USER->id;
            events_trigger('mod_deleted', $eventdata);

            add_to_log($course->id, 'course', "delete mod",
                       "view.php?id=$cm->course",
                       "$cm->modname $cm->instance", $cm->id);
        }
    }

    redirect($returnurl);
}

/**
 * perform the actual relocation of the selected course modules
 *
 * @param array $modules
 * @param int $target ID of the section to move to
 *
 * @return void
 */
function perform_moveto($modules, $target) {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/course/lib.php');

    foreach ($modules as $modulerecord) {
        if (!$cm = get_coursemodule_from_id('', $modulerecord->id, 0, true)) {
            print_error('invalidcoursemodule');
        }

        // Verify target section.
        if (!$section = $DB->get_record('course_sections', array('course' => $cm->course, 'section' => $target))) {
            print_error('sectionnotexist', 'block_massaction');
        }

        $context = context_course::instance($section->course);
        require_capability('moodle/course:manageactivities', $context);

        moveto_module($modulerecord, $section);
    }
}

/**
 * Perform the duplication of the selected course modules.
 *
 * @param array $modules Array of module record objects
 * @param int $target ID of the section to duplicate to
 *
 * @return void
 */
function perform_dupto($modules, $target) {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/course/lib.php');
    $newcmids = array();

    foreach ($modules as $modulerecord) {
        // Check for all possible failure conditions before doing actual work.
        if (!$cm = get_coursemodule_from_id('', $modulerecord->id, 0, true)) {
            print_error('invalidcoursemodule');
        }

        if (!$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST)) {
            print_error('invalidcourse');
        }

        // Verify target section here even though perform_moveto will do this again because we do not want to
        // create the new modules unless we can also move them.
        if (!$section = $DB->get_record('course_sections', array('course' => $cm->course, 'section' => $target))) {
            print_error('sectionnotexist', 'block_massaction');
        }

        $coursecontext = context_course::instance($course->id);
        require_capability('moodle/course:manageactivities', $coursecontext);
        require_capability('moodle/backup:backuptargetimport', $coursecontext);
        require_capability('moodle/restore:restoretargetimport', $coursecontext);

        if (!course_allowed_module($course, $cm->modname)) {
            throw new moodle_exception('No permission to create that activity');
        }

        // No failures and we possess the required capabilities. Duplicate the module.
        $sr = optional_param('sr', null, PARAM_INT);
        $newcm = mod_duplicate_activity($course, $cm, $sr);

        $newcmids[] = $newcm->cmid;
    }

    $modulerecords = $DB->get_records_select('course_modules', 'ID IN ('.
            implode(',', array_fill(0, count($newcmids), '?')).')', $newcmids);
    perform_moveto($modulerecords, $target);
}
