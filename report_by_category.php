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
 * This script displays the categories covered in a qpractice session.
 *
 * @package    mod_qpractice
 * @copyright  2013 Jayesh Anandani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/renderer.php');
require_once("$CFG->libdir/formslib.php");

$sessionid = required_param('sessionid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);

if ($cmid) {
    if (!$cm = get_coursemodule_from_id('qpractice', $cmid)) {
        throw new moodle_exception('invalidcoursemoduleid', 'error', '', $cmid);
    }
    if (!$course = $DB->get_record('course', ['id' => $cm->course])) {
        throw new \moodle_exception('coursemisconf');
    }
    $qpractice = $DB->get_record('qpractice', ['id' => $cm->instance]);
}

require_login($course, true, $cm);

require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/locallib.php');

$context = context_module::instance($cm->id);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/qpractice/report_by_category.php', ['sessionid' => $sessionid, 'cmid' => $cmid]));
$params = [
    'objectid' => $cm->id,
    'context' => $context,
];
$event = \mod_qpractice\event\qpractice_report_viewed::create($params);
$event->trigger();

$backurl = new moodle_url('/mod/qpractice/view.php', ['id' => $cm->id]);
$backtext = get_string('backurl', 'qpractice');
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();

// Get categories and question counts for this session, worked out from the actual
// question each attempt used (not the session's original category selection) so that
// path-mode sessions, which move through several categories, are counted correctly.
$sql = "SELECT qc.id as categoryid, qc.name as categoryname, COUNT(qa.id) as questioncount
        FROM {qpractice_session} qs
        JOIN {question_usages} qu ON qs.questionusageid = qu.id
        JOIN {question_attempts} qa ON qu.id = qa.questionusageid
        JOIN {question_versions} qv ON qv.questionid = qa.questionid
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE qs.id = :sessionid
        GROUP BY qc.id, qc.name
        ORDER BY qc.name";

$categories = $DB->get_records_sql($sql, ['sessionid' => $sessionid]);

// In path mode, show which stage of the path each category belongs to.
$stagelabels = [];
if (!empty($qpractice->pathmode)) {
    $stages = qpractice_get_path_stages($qpractice->id);
    $totalstages = count($stages);
    foreach ($stages as $i => $stage) {
        $stagelabels[$stage->categoryid] = get_string('pathstageofstages', 'qpractice', (object) [
            'stage' => $i + 1,
            'total' => $totalstages,
        ]);
    }
}

// Display the results in a table.
$table = new html_table();
$table->head = [get_string('categoryname', 'qpractice'), get_string('questioncount', 'qpractice')];
if (!empty($qpractice->pathmode)) {
    $table->head[] = get_string('pathstagecolumn', 'qpractice');
}
$table->data = [];

foreach ($categories as $category) {
    $row = [$category->categoryname, $category->questioncount];
    if (!empty($qpractice->pathmode)) {
        $row[] = $stagelabels[$category->categoryid] ?? '-';
    }
    $table->data[] = $row;
}

echo html_writer::table($table);

echo html_writer::empty_tag('br');
echo html_writer::link($backurl, $backtext);

// Finish the page.
echo $OUTPUT->footer();
