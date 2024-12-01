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

xdebug_break();

$sql = 'SELECT qc.id, qcats.name AS category_name, cs.marksobtained,cs.totalmarks
        FROM {qpractice_session} cs
        JOIN {qpractice_session_cats} qsc ON cs.id = qsc.session
        JOIN {qpractice_categories} qc ON qsc.category = qc.id
        JOIN {question_categories} qcats ON qc.categoryid = qcats.id
        WHERE cs.id = :sessionid';
$categories = $DB->get_records_sql($sql, ['sessionid' => $sessionid]);


$backurl = new moodle_url('/mod/qpractice/view.php', array('id' => $sessionid));
$backtext = get_string('backurl', 'qpractice');
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo '<table>';
foreach ($categories as $category) {
    echo '<tr><td>';
    echo $category->category_name;
    echo '</td><td>';
    echo $category->marksobtained;
    echo '</td><td>';
    echo $category->totalmarks;
    echo '</td>';
    echo '</tr>';
}
echo '</table>';
echo $OUTPUT->footer();
