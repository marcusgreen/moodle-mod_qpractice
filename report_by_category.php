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

$sessionid = required_param('sessionid', PARAM_INT); // Course-Module id.


// $sql = 'SELECT qc.id, qcats.name AS category_name, cs.marksobtained,cs.totalmarks
//         FROM {qpractice_session} cs
//         JOIN {qpractice_session_cats} qsc ON cs.id = qsc.session
//         JOIN {qpractice_categories} qc ON qsc.category = qc.id
//         JOIN {question_categories} qcats ON qc.categoryid = qcats.id
//         WHERE cs.id = :sessionid';

// select  qcats.id, qcats.name as categroy_name,session.marksobtained, session.totalmarks
// from mdl_qpractice qp join mdl_qpractice_session session on session.qpracticeid = qp.id
// join mdl_qpractice_session_cats sessioncats on session.id = sessioncats.session
// select  qcats.id, qcats.name as category_name, session.marksobtained, session.totalmarks
// from mdl_qpractice qp join mdl_qpractice_session session on session.qpracticeid = qp.id
// join mdl_qpractice_session_cats sessioncats on session.id = sessioncats.session
// join mdl_question_categories qcats on sessioncats.category = qcats.id where session.id = 1\G;
// join mdl_question_categories qcats on sessioncats.session = session.id where session.id = 1;

$sql = "SELECT qcats.id, qcats.name as category_name
        FROM {qpractice} qp
        JOIN {qpractice_session} session ON session.qpracticeid = qp.id
        JOIN {qpractice_session_cats} sessioncats ON session.id = sessioncats.session
        JOIN {question_categories} qcats ON sessioncats.category = qcats.id
        WHERE session.id = :sessionid";

$categories = $DB->get_records_sql($sql, ['sessionid' => $sessionid]);


$cmid = required_param('cmid', PARAM_INT); // Course-Module id.

if ($cmid) {
    if (!$cm = get_coursemodule_from_id('qpractice', $cmid)) {
        throw new moodle_exception('invalidcoursemoduleid', 'error', '', $cmid);

    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        throw new \moodle_exception('coursemisconf');
    }
    $qpractice = $DB->get_record('qpractice', array('id' => $cm->instance));
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);


$sql = "SELECT * FROM {question_usages} qu
        JOIN {question_attempts} qa  ON qa.questionusageid = qu.id
        JOIN {qpractice_session} session ON session.questionusageid = qu.id
        JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
        JOIN {question_versions} qver ON qver.questionid = qa.questionid
        WHERE qu.contextid = :contextid
        AND qas.fraction IS NOT NULL
        AND session.id = :sessionid";


$qusage = $DB->get_records_sql($sql, ['contextid' => $context->id, 'sessionid' => $sessionid]);

foreach($categories as $category) {
        $categorytotal = 0;
        foreach ($qusage as $q) {
            $sql = "SELECT qc.id as categoryid FROM {question_bank_entries} qbe
                    JOIN {question_versions} qver on qver.questionbankentryid = qbe.id
                    JOIN {question_categories} qc ON qbe.questioncategoryid = qc.id
                    AND qver.questionid = :questionid";

             $qcat = $DB->get_record_sql($sql, ['questionid' => $q->questionid]);
            if($qcat->categoryid  == $category->id) {
                $categorytotal += $q->fraction;
            }
        }
        $category->total = $categorytotal;
}


$report = \core_reportbuilder\system_report_factory::create(
    \mod_qpractice\reportbuilder\local\systemreports\qpractice_session_categories_report::class,
    $context
);

$backurl = new moodle_url('/mod/qpractice/view.php', array('id' => $sessionid));
$backtext = get_string('backurl', 'qpractice');
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
//echo $report->output();
$t = new html_table();
$t->head = array(get_string('category', 'qpractice'), get_string('marksobtained', 'qpractice'), get_string('totalmarks', 'qpractice'));
$t->data = $categories;
//echo html_writer::table($t);
$columns =[
    'category_name' => get_string('category', 'qpractice'),
    'marksobtained' => get_string('marksobtained', 'qpractice'),
    'categorytotal' => get_string('totalmarks', 'qpractice'),
];
$headers =[
    get_string('category', 'qpractice'),
    get_string('marksobtained', 'qpractice'),
    get_string('totalmarks', 'qpractice'),
];



$table = new flexible_table('questioncategories');


$table->define_headers($headers);
$table->define_columns($columns);
$table->column_style('restore', 'text-align', 'center');
$table->column_style('delete', 'text-align', 'center');
$table->define_baseurl($PAGE->url);
$table->set_attribute('id', 'questioncategoryable');
$table->setup();

foreach ($categories as $category) {

    $table->add_data((array) $category);
}

$table->finish_output();



// echo '<table>';
// foreach ($categories as $category) {
//     echo '<tr><td>';
//     echo $category->category_name;
//     echo '</td><td>';
//     echo $category->marksobtained;
//     echo '</td><td>';
//     echo $category->totalmarks;
//     echo '</td>';
//     echo '</tr>';
// }
// echo '</table>';
echo $OUTPUT->footer();
