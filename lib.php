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
 * Library of interface functions and constants for module qpractice
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the qpractice specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_qpractice
 * @copyright  2013 Jayesh Anandani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Returns the information on whether the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function qpractice_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_USES_QUESTIONS:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the qpractice into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $qpractice An object from the form in mod_form.php
 * @param mod_qpractice_mod_form $mform
 * @return int The id of the newly inserted qpractice record
 */
function qpractice_add_instance(stdClass $qpractice, mod_qpractice_mod_form $mform = null) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/questionlib.php');

    $qpractice->timecreated = time();
    $behaviour = $qpractice->behaviour;
    $comma = implode(",", array_keys($behaviour));
    $qpractice->behaviour = $comma;

    $qpractice->id = $DB->insert_record('qpractice', $qpractice);

    qpractice_after_add_or_update($qpractice);

    return $qpractice->id;
}

/**
 * Updates an instance of the qpractice in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $qpractice An object from the form in mod_form.php
 * @param mod_qpractice_mod_form $mform
 * @return boolean Success/Fail
 */
function qpractice_update_instance(stdClass $qpractice, mod_qpractice_mod_form $mform = null) {
    global $DB;

    $qpractice->timemodified = time();
    $qpractice->id = $qpractice->instance;
    $behaviour = $qpractice->behaviour;
    $comma = implode(",", array_keys($behaviour));
    $qpractice->behaviour = $comma;

    return $DB->update_record('qpractice', $qpractice);
}

/**
 * Removes an instance of the qpractice from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function qpractice_delete_instance($id) {
    global $DB;

    if (!$qpractice = $DB->get_record('qpractice', array('id' => $id))) {
        return false;
    }

    $DB->delete_records('qpractice', array('id' => $qpractice->id));

    return true;
}

/**
 * This function is called at the end of qpractice_add_instance
 * to do the common processing.
 *
 * @param object $qpractice the qpractice object.
 */
function qpractice_after_add_or_update($qpractice) {
    global $DB;
    $cmid = $qpractice->coursemodule;

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field('course_modules', 'instance', $qpractice->id, array('id' => $cmid));
    $context = context_module::instance($cmid);
    $contexts = array($context);
    question_make_default_categories($contexts);
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param int $course
 * @param int $user
 * @param int $mod
 * @param int $qpractice
 * @return stdClass|null
 */
function qpractice_user_outline(int $course, int $user, int $mod, int $qpractice) {
    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $qpractice the module instance record
 * @return void, is supposed to echp directly
 */
function qpractice_user_complete($course, $user, $mod, $qpractice) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in qpractice activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 */
/**
 * Always returns false (?)
 *
 * @param ? $course - not an int; called by block_recent_activity with $PAGE->course (see pagelib.php)
 * @param bool $viewfullnames
 * @param int $timestart
 * @return void
 */
function qpractice_print_recent_activity($course, bool $viewfullnames, int $timestart) {
    return false;  // True if anything was printed, otherwise false.
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link qpractice_print_recent_mod_activity()}.
 *
 * @param array $activities sequentially indexed array of objects with the 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 * @return void adds items into $activities and increases $index
 */
function qpractice_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid = 0, $groupid = 0) {

}



/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 * */
function qpractice_cron() {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * @return array
 */
function qpractice_get_extra_capabilities() {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    $caps = question_get_all_capabilities();
    $caps[] = 'moodle/site:accessallgroups';
    return $caps;
}

/**
 * Creates or updates grade item for the give qpractice instance
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php
 *
 * @param stdClass $qpractice instance object with extra cmidnumber and modname property
 * @return void
 */
function qpractice_grade_item_update(stdClass $qpractice) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = array();
    $item['itemname'] = clean_param($qpractice->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax'] = $qpractice->grade;
    $item['grademin'] = 0;

    grade_update('mod/qpractice', $qpractice->course, 'mod', 'qpractice', $qpractice->id, 0, null, $item);
}

/**
 * Update qpractice grades in the gradebook
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php
 *
 * @param stdClass $qpractice instance object with extra cmidnumber and modname property
 * @param int $userid update grade of specific user only, 0 means all participants
 * @return void
 */
function qpractice_update_grades(stdClass $qpractice, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    $grades = array(); // Populate array of grade objects indexed by userid.

    grade_update('mod/qpractice', $qpractice->course, 'mod', 'qpractice', $qpractice->id, 0, $grades);
}

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function qpractice_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for qpractice file areas
 *
 * @package mod_qpractice
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function qpractice_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the qpractice file areas
 *
 * @package mod_qpractice
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the qpractice's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function qpractice_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options = array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    send_file_not_found();
}

/**
 * Deal with attached files (do we have any?)
 *
 * @param int $course
 * @param \context $context
 * @param int $component
 * @param int $filearea
 * @param int $qubaid
 * @param int $slot
 * @param array $args
 * @param [type] $forcedownload
 * @param array $options
 * @return void
 */
function qpractice_question_pluginfile(int $course, \context $context, int $component, int $filearea,
    int $qubaid, int $slot, array $args, $forcedownload, array $options = array()) {
    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/$component/$filearea/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Extends the global navigation tree by adding qpractice nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the qpractice module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function qpractice_extend_navigation(navigation_node $navref, stdClass $course, stdClass $module, cm_info $cm) {
}

/**
 * Extends the settings navigation with the qpractice settings
 *
 * This function is called when the context for the page is a qpractice module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $qpracticenode {@link navigation_node}
 */
function qpractice_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $qpracticenode = null) {
    global $PAGE, $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    question_extend_settings_navigation($qpracticenode, $PAGE->cm->context)->trim_if_empty();

}
