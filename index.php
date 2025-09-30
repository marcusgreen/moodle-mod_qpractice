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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

require_login();

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
