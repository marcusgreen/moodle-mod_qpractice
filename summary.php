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
 * Views overall summary of your current attempt.
 *
 * @package    mod_qpractice
 * @copyright  2013 Jayesh Anandani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once(dirname(__FILE__) . '/renderer.php');
require_once("$CFG->libdir/formslib.php");

$sessionid = required_param('id', PARAM_INT); // Sessionid.
$session = $DB->get_record('qpractice_session', ['id' => $sessionid]);
$cm = get_coursemodule_from_instance('qpractice', $session->qpracticeid);
$course = $DB->get_record('course', ['id' => $cm->course]);
$qpractice = $DB->get_record('qpractice', ['id' => $cm->instance]);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

$params = [
    'objectid' => $cm->id,
    'context' => $context,
];

$event = \mod_qpractice\event\qpractice_summary_viewed::create($params);
$event->trigger();

$actionurl = new moodle_url('/mod/qpractice/attempt.php', ['id' => $sessionid]);
$stopurl = new moodle_url('/mod/qpractice/view.php', ['id' => $cm->id]);

if (data_submitted()) {
    if (optional_param('back', null, PARAM_BOOL)) {
        redirect($actionurl);
    } if (optional_param('finish', null, PARAM_BOOL)) {
        $quba = question_engine::load_questions_usage_by_activity($session->questionusageid);
        $DB->set_field('qpractice_session', 'status', 'finished', ['id' => $sessionid]);
        $slots = $quba->get_slots();
        $slot = end($slots);
        if (!$slot) {
            redirect($stopurl);
        } else {
            $fraction = $quba->get_question_fraction($slot);
            $maxmarks = $quba->get_question_max_mark($slot);
            $obtainedmarks = $fraction * $maxmarks;
            $updatesql = "UPDATE {qpractice_session}
                          SET marksobtained = marksobtained + ?, totalmarks = totalmarks + ?
                        WHERE id=?";
            $DB->execute($updatesql, [$obtainedmarks, $maxmarks, $sessionid]);
            if ($fraction > 0) {
                $updatesql1 = "UPDATE {qpractice_session}
                          SET totalnoofquestionsright = totalnoofquestionsright + '1'
                        WHERE id=?";
                $DB->execute($updatesql1, [$sessionid]);
            }
            $DB->set_field('qpractice_session', 'status', 'finished', ['id' => $sessionid]);
            redirect($stopurl);
        }
    }
}
$PAGE->set_title($qpractice->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);
$PAGE->set_url('/mod/qpractice/summary.php', ['id' => $sessionid]);
$output = $PAGE->get_renderer('mod_qpractice');

echo $OUTPUT->header();
echo $output->summary_table($sessionid);

// Add link to session categories report.
$reporturl = new moodle_url('/mod/qpractice/report_by_category.php', ['sessionid' => $sessionid, 'cmid' => $cm->id]);
echo html_writer::link($reporturl, get_string('viewpastsessions', 'qpractice'), ['class' => 'btn btn-primary mb-3']);

echo $output->summary_form($sessionid);

// Finish the page.
echo $OUTPUT->footer();
