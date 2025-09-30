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
 * The renderer for qpractice module.
 *
 * @package    mod_qpractice
 * @copyright  2013 Jayesh Anandani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
/**
 * Mainly things about reporting
 *
 * @package    mod_qpractice
 * @copyright  2019 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_qpractice_renderer extends plugin_renderer_base {
    /**
     * shown at the end of a session
     *
     * @param int $sessionid
     * @return void
     */
    public function summary_table(int $sessionid) {
        global $DB;

        $session = $DB->get_record('qpractice_session', ['id' => $sessionid]);
        $table = new html_table();
        $table->attributes['class'] = 'generaltable qpracticesummaryofattempt boxaligncenter';
        $table->caption = get_string('pastsessions', 'qpractice');
        $table->head = [get_string('totalquestions', 'qpractice'), get_string('totalmarks', 'qpractice')];
        $table->align = ['left', 'left'];
        $table->size = ['', ''];
        $table->data = [];
        $table->data[] = [$session->totalnoofquestions, $session->marksobtained . '/' . $session->totalmarks];
        echo html_writer::table($table);
    }

    /**
     * Show buttons after summary table for resume practice or
     * submit and finish
     *
     * @param int $sessionid
     * @return void
     */
    public function summary_form(int $sessionid) {
        $actionurl = new moodle_url('/mod/qpractice/summary.php', ['id' => $sessionid]);
        $output = '';
        $output .= html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $actionurl,
            'enctype' => 'multipart/form-data',
            'id' => 'responseform',
            'class' => 'qpractice']);
        $output .= html_writer::start_tag('div', ['align' => 'center']);
        $output .= html_writer::empty_tag(
            'input',
            [
                    'type' => 'submit',
                    'class' => 'qpbtn submit',
                    'name' => 'back',
                    'value' => get_string('resumepractice', 'qpractice'),
                    ]
        );
        $output .= html_writer::empty_tag('br');
        $output .= html_writer::empty_tag('br');
        $output .= html_writer::empty_tag('input', [
                    'type' => 'submit',
                    'name' => 'finish',
                    'class' => 'qpbtn submit',
                    'value' => get_string('submitandfinish', 'qpractice')]);
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');

        echo $output;
    }


    /**
     * Used for 'show past sessions'
     *
     * @param stdClass $cm
     * @param \context $context
     * @return void
     */
    public function report_table(stdClass $cm, \context $context) {
        global $DB, $USER;

        $canviewallreports = has_capability('mod/qpractice:viewallreports', $context);
        $canviewmyreports = has_capability('mod/qpractice:viewmyreport', $context);

        if ($canviewmyreports) {
            $session = $DB->get_records('qpractice_session', ['qpracticeid' => $cm->instance, 'userid' => $USER->id]);
        } if ($canviewallreports) {
            $session = $DB->get_records('qpractice_session', ['qpracticeid' => $cm->instance]);
        }

        if ($session != null) {
            $table = new html_table();
            $table->attributes['class'] = 'generaltable qpracticesummaryofpractices boxaligncenter';
            $table->caption = get_string('pastsessions', 'qpractice');
            $table->head = [get_string('practicedate', 'qpractice'), get_string('category', 'qpractice'),
                get_string('score', 'qpractice'),
                get_string('noofquestionsviewed', 'qpractice'),
                get_string('noofquestionsright', 'qpractice')];
            $table->align = ['left', 'left', 'left', 'left', 'left', 'left', 'left'];
            $table->size = ['', '', '', '', '', '', '', ''];
            $table->data = [];
            foreach ($session as $qpractice) {
                $date = $qpractice->practicedate;
                $categoryid = $qpractice->categoryid;

                $category = $DB->get_records_menu('question_categories', ['id' => $categoryid], 'name');
                /* If the category has been deleted, jump to the next session */
                if (empty($category)) {
                    continue;
                }
                $table->data[] = [userdate($date), $category[$categoryid],
                    $qpractice->marksobtained . '/' . $qpractice->totalmarks,
                    $qpractice->totalnoofquestions, $qpractice->totalnoofquestionsright];
            }
            echo html_writer::table($table);
        } else {
            $viewurl = new moodle_url('/mod/qpractice/view.php', ['id' => $cm->id]);
            $viewtext = get_string('viewurl', 'qpractice');
            redirect($viewurl, $viewtext);
        }
    }
}
