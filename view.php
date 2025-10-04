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
 * Views options for starting a new session or see past reports.
 *
 * @package    mod_qpractice
 * @copyright  2013 Jayesh Anandani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

global $CFG, $USER;
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once("$CFG->libdir/formslib.php");

$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$n = optional_param('n', 0, PARAM_INT);  // Qpractice instance ID - it should be named as the first character of the module.
if ($id) {
    if (!$cm = get_coursemodule_from_id('qpractice', $id)) {
        throw new moodle_exception('invalidcoursemoduleid', 'error', '', $id);
    }
    if (!$course = $DB->get_record('course', ['id' => $cm->course])) {
        throw new moodle_exception('coursemisconf', 'error', '', $cm->course);
    }
    $qpractice = $DB->get_record('qpractice', ['id' => $cm->instance]);
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/qpractice:view', $context);

$params = [
    'objectid' => $cm->id,
    'context' => $context,
];
$event = mod_qpractice\event\qpractice_viewed::create($params);
$event->trigger();

$PAGE->set_url('/mod/qpractice/view.php', ['id' => $cm->id, 'courseid' => $course->id]);
$PAGE->set_title(format_string($qpractice->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$canview = has_capability('mod/qpractice:view', $context);

$createurl = new moodle_url('/mod/qpractice/startattempt.php', ['id' => $cm->id]);
$createtext = get_string('createurl', 'qpractice');
$reporturl = new moodle_url('/mod/qpractice/report.php', ['id' => $cm->id]);
$reporttext = get_string('reporturl', 'qpractice');

echo $OUTPUT->header();

if ($canview) {
    echo html_writer::start_tag('div', ['id' => 'buttons', 'class' => 'row']);
    echo $OUTPUT->single_button($createurl, $createtext, 'get', ['class' => 'btn text-left col-sm-3 ']);

    if (
        $qpractice = $DB->get_records('qpractice_session', ['userid' => $USER->id,
        'qpracticeid' => $cm->instance], 'id desc', '*', '0', '1')
    ) {
        $qpractice = array_values($qpractice);

        echo $OUTPUT->single_button($reporturl, $reporttext, 'get', ['class' => 'btn  text-left col-sm-4']);

        if ($qpractice[0]->status == 'inprogress') {
            $continueurl = new moodle_url('/mod/qpractice/attempt.php', ['id' => $qpractice[0]->id]);
            $continuetext = get_string('continueurl', 'qpractice');
            echo html_writer::link($continueurl, $continuetext);
        }
    }
    echo html_writer::end_tag('div');
} else {
    throw new moodle_exception(get_string('nopermission', 'qpractice'));
}

// Finish the page.
echo $OUTPUT->footer();
