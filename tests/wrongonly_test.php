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
 * Unit tests for the "practice only previously incorrect questions" logic in
 * mod/qpractice/locallib.php.
 *
 * @package    mod_qpractice
 * @category   test
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_qpractice;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/qpractice/locallib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

/**
 * Tests for qpractice_get_incorrect_questionids() and its effect on question selection.
 *
 * @package     mod_qpractice
 * @copyright   2026
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wrongonly_test extends \advanced_testcase {
    /**
     * Create a true/false question and answer it, recording the answer against a new
     * qpractice session for the given user.
     *
     * @param int $qpracticeid
     * @param int $userid
     * @param bool $answercorrectly
     * @return int the question id
     */
    private function create_answered_session(int $qpracticeid, int $userid, bool $answercorrectly): int {
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $questiondata = $questiongenerator->create_question('truefalse', null, ['category' => $category->id]);
        $question = \question_bank::load_question($questiondata->id);

        $quba = \question_engine::make_questions_usage_by_activity('mod_qpractice', \context_system::instance());
        $quba->set_preferred_behaviour('deferredfeedback');
        $slot = $quba->add_question($question);
        $quba->start_question($slot);
        $quba->process_action($slot, ['answer' => $answercorrectly ? 1 : 0]);
        $quba->finish_question($slot);
        \question_engine::save_questions_usage_by_activity($quba);

        global $DB;
        $DB->insert_record('qpractice_session', (object) [
            'qpracticeid' => $qpracticeid,
            'questionusageid' => $quba->get_id(),
            'userid' => $userid,
            'typeofpractice' => '1',
            'practicedate' => time(),
        ]);

        return $questiondata->id;
    }

    /**
     * A question answered wrong is included in the incorrect pool.
     *
     * @covers ::qpractice_get_incorrect_questionids
     */
    public function test_wrong_answer_is_included(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $questionid = $this->create_answered_session(1, 2, false);

        $this->assertEquals([$questionid], qpractice_get_incorrect_questionids(1, 2));
    }

    /**
     * A question answered right is not included in the incorrect pool.
     *
     * @covers ::qpractice_get_incorrect_questionids
     */
    public function test_right_answer_is_excluded(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->create_answered_session(1, 2, true);

        $this->assertEquals([], qpractice_get_incorrect_questionids(1, 2));
    }

    /**
     * If the same question is answered wrong then later right, in different sessions,
     * only the most recent (correct) result counts, so it drops out of the pool.
     *
     * @covers ::qpractice_get_incorrect_questionids
     */
    public function test_latest_attempt_wins(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $questiondata = $questiongenerator->create_question('truefalse', null, ['category' => $category->id]);

        global $DB;
        foreach ([false, true] as $correct) {
            $question = \question_bank::load_question($questiondata->id);
            $quba = \question_engine::make_questions_usage_by_activity('mod_qpractice', \context_system::instance());
            $quba->set_preferred_behaviour('deferredfeedback');
            $slot = $quba->add_question($question);
            $quba->start_question($slot);
            $quba->process_action($slot, ['answer' => $correct ? 1 : 0]);
            $quba->finish_question($slot);
            \question_engine::save_questions_usage_by_activity($quba);

            $DB->insert_record('qpractice_session', (object) [
                'qpracticeid' => 1,
                'questionusageid' => $quba->get_id(),
                'userid' => 2,
                'typeofpractice' => '1',
                'practicedate' => time(),
            ]);
        }

        $this->assertEquals([], qpractice_get_incorrect_questionids(1, 2));
    }

    /**
     * The incorrect pool is scoped to the given qpractice instance; another instance's
     * sessions for the same user do not contribute.
     *
     * @covers ::qpractice_get_incorrect_questionids
     */
    public function test_scoped_to_instance(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->create_answered_session(1, 2, false);

        $this->assertEquals([], qpractice_get_incorrect_questionids(999, 2));
    }
}
