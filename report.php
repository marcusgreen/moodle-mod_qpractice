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
 * This script controls the display of the qpractice reports.
 *
 * @package    mod_qpractice
 * @copyright  2013 Jayesh Anandani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/renderer.php');
require_once("$CFG->libdir/formslib.php");

$id = required_param('id', PARAM_INT); // Course-Module id.

if ($id) {
    if (!$cm = get_coursemodule_from_id('qpractice', $id)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }
    $qpractice = $DB->get_record('qpractice', array('id' => $cm->instance));
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$courseid = required_param('id', PARAM_INT);   // Course.

$context = context_system::instance();
$PAGE->set_url(new moodle_url('/mod/qpractice/report.php'));
$params = [
    'objectid' => $cm->id,
    'context' => $context
];
$event = \mod_qpractice\event\qpractice_report_viewed::create($params);
$event->trigger();

$backurl = new moodle_url('/mod/qpractice/view.php', array('id' => $cm->id));
$backtext = get_string('backurl', 'qpractice');
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();

$report = \core_reportbuilder\system_report_factory::create(
    \mod_qpractice\reportbuilder\local\systemreports\qpractice_sessions_report::class,
    $context
);

echo $report->output();

echo html_writer::empty_tag('br');
echo html_writer::link($backurl, $backtext);

// Finish the page.
echo $OUTPUT->footer();
