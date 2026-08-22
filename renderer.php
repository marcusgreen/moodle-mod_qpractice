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
     * Per-category score breakdown for a finished session.
     *
     * No per-category totals are stored on the session, so this is derived on
     * demand from the session's question usage: marks and correctness come from
     * the question engine (which handles partial credit and unanswered
     * questions), and each question is mapped to its category via the question
     * bank tables.
     *
     * @param int $sessionid The qpractice_session id.
     * @return string Rendered HTML table, or '' when there is nothing to show.
     */
    public function category_breakdown_table(int $sessionid): string {
        global $DB;

        $session = $DB->get_record('qpractice_session', ['id' => $sessionid], '*', MUST_EXIST);
        $quba = question_engine::load_questions_usage_by_activity($session->questionusageid);

        // Collect the question ids used in this session's usage.
        $qids = [];
        foreach ($quba->get_slots() as $slot) {
            $qids[$quba->get_question_attempt($slot)->get_question_id()] = true;
        }
        if (empty($qids)) {
            return '';
        }

        // Map each question id to its category (id + name).
        [$insql, $params] = $DB->get_in_or_equal(array_keys($qids), SQL_PARAMS_NAMED);
        $sql = "SELECT q.id AS questionid, qc.id AS categoryid, qc.name AS categoryname
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                 WHERE q.id $insql";
        $catmap = $DB->get_records_sql($sql, $params);

        // Aggregate marks and correctness per category.
        $rows = [];
        foreach ($quba->get_slots() as $slot) {
            $qid = $quba->get_question_attempt($slot)->get_question_id();
            if (!isset($catmap[$qid])) {
                // Question deleted or moved out of the bank since the attempt.
                continue;
            }
            $cat = $catmap[$qid];
            if (!isset($rows[$cat->categoryid])) {
                $rows[$cat->categoryid] = (object) [
                    'name' => $cat->categoryname,
                    'count' => 0,
                    'right' => 0,
                    'marks' => 0.0,
                    'maxmarks' => 0.0,
                ];
            }
            $row = $rows[$cat->categoryid];

            $mark = $quba->get_question_mark($slot); // Null when unanswered.
            $row->count++;
            $row->maxmarks += $quba->get_question_max_mark($slot);
            $row->marks += ($mark ?? 0);
            // Any credit (fraction > 0) counts as correct, matching how the
            // session running totals count a right answer. Partial credit counts.
            if ($quba->get_question_fraction($slot) > 0) {
                $row->right++;
            }
        }

        if (empty($rows)) {
            return '';
        }

        $table = new html_table();
        $table->attributes['class'] = 'generaltable qpracticecategorybreakdown boxaligncenter';
        $table->caption = get_string('categorybreakdown', 'qpractice');
        $table->head = [
            get_string('category', 'qpractice'),
            get_string('totalquestions', 'qpractice'),
            get_string('noofquestionsright', 'qpractice'),
            get_string('totalmarks', 'qpractice'),
            get_string('percentage', 'qpractice'),
        ];
        $table->data = [];
        foreach ($rows as $row) {
            $pct = $row->maxmarks > 0 ? round(100 * $row->marks / $row->maxmarks) : 0;
            $table->data[] = [
                format_string($row->name),
                $row->count,
                $row->right,
                format_float($row->marks, 2) . '/' . format_float($row->maxmarks, 2),
                $pct . '%',
            ];
        }
        return html_writer::table($table);
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
     * Live progress summary shown above the question during an attempt.
     *
     * Practice is open-ended (no fixed target), so this shows running totals:
     * questions answered so far, how many were correct, the percentage correct,
     * and marks obtained. The answered count is derived from the usage (finished
     * slots) since it is not stored on the session.
     *
     * @param int $sessionid The qpractice_session id.
     * @param question_usage_by_activity $quba The session's question usage.
     * @return string Rendered HTML, or '' before the first question is answered.
     */
    public function attempt_progress(int $sessionid, question_usage_by_activity $quba): string {
        global $DB;

        $session = $DB->get_record('qpractice_session', ['id' => $sessionid], '*', MUST_EXIST);

        // Count questions already finished (the current one is still in progress).
        $answered = 0;
        foreach ($quba->get_slots() as $slot) {
            if ($quba->get_question_state($slot)->is_finished()) {
                $answered++;
            }
        }
        if ($answered == 0) {
            // Nothing answered yet: no progress to report.
            return '';
        }

        $right = (int) $session->totalnoofquestionsright;
        $pct = (int) round(100 * $right / $answered);

        $stats = html_writer::tag('span', get_string('answered', 'qpractice') . ': ' . $answered)
            . html_writer::tag('span', get_string('correct', 'qpractice') . ': ' . $right . '/' . $answered
                . ' (' . $pct . '%)')
            . html_writer::tag('span', get_string('score', 'qpractice') . ': '
                . format_float($session->marksobtained, 2) . '/' . format_float($session->totalmarks, 2));

        $bar = html_writer::div('', 'progress-bar', [
            'style' => 'width: ' . $pct . '%;',
            'role' => 'progressbar',
            'aria-valuenow' => $pct,
            'aria-valuemin' => 0,
            'aria-valuemax' => 100,
        ]);

        return html_writer::div(
            html_writer::tag(
                'div',
                $stats,
                ['class' => 'qpractice-progress-stats d-flex flex-wrap justify-content-between gap-2 mb-1']
            )
            . html_writer::div($bar, 'progress', ['style' => 'height: .5rem;']),
            'qpractice-progress card card-body py-2 mb-3',
            ['aria-label' => get_string('attemptprogress', 'qpractice')]
        );
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
