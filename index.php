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
 * This script lists all the instances of qpractice in a particular course.
 *
 *
 * @package    mod_qpractice
 * @copyright  2013 Jayesh Anandani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$courseid = required_param('id', PARAM_INT);   // Course.

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/qpractice/index.php'));

echo $OUTPUT->header();
echo $OUTPUT->heading('mod_qpractice');

$report = \core_reportbuilder\system_report_factory::create(
       \mod_qpractice\reportbuilder\local\systemreports\qpractice_sessions_report::class,
       context_course::instance($courseid)
);

echo $report->output();
echo $OUTPUT->footer();


// $course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

// require_course_login($course);

// add_to_log($course->id, 'qpractice', 'view all', 'index.php?id='.$course->id, '');

// $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);

// $PAGE->set_url('/mod/qpractice/index.php', array('id' => $id));
// $PAGE->set_title(format_string($course->fullname));
// $PAGE->set_heading(format_string($course->fullname));
// $PAGE->set_context($coursecontext);

// echo $OUTPUT->header();

// $table = new html_table();

// if (! $qpractices = get_all_instances_in_course('qpractice', $course)) {
//     notice(get_string('noqpractices', 'qpractice'), new moodle_url('/course/view.php', array('id' => $course->id)));
// }

// if ($course->format == 'weeks') {
//     $table->head  = array(get_string('week'), get_string('name'));
//     $table->align = array('center', 'left');
// } else if ($course->format == 'topics') {
//     $table->head  = array(get_string('topic'), get_string('name'));
//     $table->align = array('center', 'left', 'left', 'left');
// } else {
//     $table->head  = array(get_string('name'));
//     $table->align = array('left', 'left', 'left');
// }

// foreach ($qpractices as $qpractice) {
//     if (!$qpractice->visible) {
//         $link = html_writer::link(
//             new moodle_url('/mod/qpractice.php', array('id' => $qpractice->coursemodule)),
//             format_string($qpractice->name, true),
//             array('class' => 'dimmed'));
//     } else {
//         $link = html_writer::link(
//             new moodle_url('/mod/qpractice.php', array('id' => $qpractice->coursemodule)),
//             format_string($qpractice->name, true));
//     }

//     if ($course->format == 'weeks' or $course->format == 'topics') {
//         $table->data[] = array($qpractice->section, $link);
//     } else {
//         $table->data[] = array($link);
//     }
// }

// echo $OUTPUT->heading(get_string('modulenameplural', 'qpractice'), 2);
// echo html_writer::table($table);
// echo $OUTPUT->footer();
