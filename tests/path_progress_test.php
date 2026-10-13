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
 * Unit tests for the category path progression logic in mod/qpractice/locallib.php.
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

/**
 * Tests for stage advancement through a category path.
 *
 * @package     mod_qpractice
 * @copyright   2026
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class path_progress_test extends \advanced_testcase {
    /**
     * Set up a path-mode qpractice instance with two stages: stage 0 (category A,
     * target 50%) and stage 1 (category B, no target, final stage).
     *
     * @param int $minquestions Minimum questions answered on stage 0 before it can advance.
     * @return array [qpracticeid, categoryid A, categoryid B]
     */
    private function setup_two_stage_path(int $minquestions = 0): array {
        global $SITE, $DB;

        $qpracticegenerator = $this->getDataGenerator()->get_plugin_generator('mod_qpractice');
        $qpractice = $qpracticegenerator->create_instance(['course' => $SITE->id]);
        $DB->set_field('qpractice', 'pathmode', 1, ['id' => $qpractice->id]);

        $DB->insert_record('qpractice_category_path', (object) [
            'qpracticeid' => $qpractice->id,
            'categoryid' => 101,
            'sortorder' => 0,
            'targetpercent' => 50,
            'minquestions' => $minquestions,
            'onachieve' => 'nextstage',
        ]);
        $DB->insert_record('qpractice_category_path', (object) [
            'qpracticeid' => $qpractice->id,
            'categoryid' => 102,
            'sortorder' => 1,
            'targetpercent' => null,
            'onachieve' => 'nextstage',
        ]);

        return [$qpractice->id, 101, 102];
    }

    /**
     * A new student starts on the first stage's category.
     *
     * @covers ::qpractice_current_path_category
     */
    public function test_student_starts_on_first_stage(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$qpracticeid, $categorya] = $this->setup_two_stage_path();

        $this->assertEquals($categorya, qpractice_current_path_category($qpracticeid, 2));
    }

    /**
     * Falling short of the target keeps the student on the same stage.
     *
     * @covers ::qpractice_record_path_answer
     */
    public function test_below_target_does_not_advance(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$qpracticeid, $categorya] = $this->setup_two_stage_path();

        // 0 out of 1: 0%, below the 50% target.
        $advanced = qpractice_record_path_answer($qpracticeid, 2, 0, 1);

        $this->assertFalse($advanced);
        $this->assertEquals($categorya, qpractice_current_path_category($qpracticeid, 2));
    }

    /**
     * Reaching the target percentage unlocks the next stage and resets the running
     * per-stage counters.
     *
     * @covers ::qpractice_record_path_answer
     */
    public function test_reaching_target_advances_and_resets_counters(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$qpracticeid, $categorya, $categoryb] = $this->setup_two_stage_path();

        // First answer wrong (0/1), second answer right (1/1): running 1/2 = 50%, meets target.
        qpractice_record_path_answer($qpracticeid, 2, 0, 1);
        $advanced = qpractice_record_path_answer($qpracticeid, 2, 1, 1);

        $this->assertTrue($advanced);
        $this->assertEquals($categoryb, qpractice_current_path_category($qpracticeid, 2));

        $progress = $DB->get_record('qpractice_user_path_progress', ['qpracticeid' => $qpracticeid, 'userid' => 2]);
        $this->assertEquals(0, $progress->stagecorrect);
        $this->assertEquals(0, $progress->stagetotal);
        $this->assertEquals(0, $progress->stageanswered);
    }

    /**
     * Meeting the target does not advance the student until they have answered the
     * stage's minimum number of questions.
     *
     * @covers ::qpractice_record_path_answer
     */
    public function test_minimum_questions_delays_advance(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$qpracticeid, $categorya, $categoryb] = $this->setup_two_stage_path(3);

        // Two correct answers: 100%, above the 50% target, but only 2 of the 3 required.
        $this->assertFalse(qpractice_record_path_answer($qpracticeid, 2, 1, 1));
        $this->assertFalse(qpractice_record_path_answer($qpracticeid, 2, 1, 1));
        $this->assertEquals($categorya, qpractice_current_path_category($qpracticeid, 2));

        // Third answer, wrong: 2/3 is still above target and the minimum is now met.
        $this->assertTrue(qpractice_record_path_answer($qpracticeid, 2, 0, 1));
        $this->assertEquals($categoryb, qpractice_current_path_category($qpracticeid, 2));
    }

    /**
     * The final stage has no target, so it never reports an advance.
     *
     * @covers ::qpractice_record_path_answer
     */
    public function test_final_stage_never_advances(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$qpracticeid, $categorya, $categoryb] = $this->setup_two_stage_path();

        qpractice_record_path_answer($qpracticeid, 2, 0, 1);
        qpractice_record_path_answer($qpracticeid, 2, 1, 1);
        // Now on the final stage (category B); more correct answers should not advance further.
        $advanced = qpractice_record_path_answer($qpracticeid, 2, 1, 1);

        $this->assertFalse($advanced);
        $this->assertEquals($categoryb, qpractice_current_path_category($qpracticeid, 2));
    }

    /**
     * A new student sees the first stage, its target and the answers they need, but no score yet.
     *
     * @covers ::qpractice_get_path_status
     */
    public function test_status_on_first_stage(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$qpracticeid, $categorya] = $this->setup_two_stage_path(3);

        $status = qpractice_get_path_status($qpracticeid, 2);

        $this->assertEquals(1, $status->stageno);
        $this->assertEquals(2, $status->totalstages);
        $this->assertEquals($categorya, $status->categoryid);
        $this->assertNull($status->stagepercent);
        $this->assertEquals(50, $status->targetpercent);
        $this->assertEquals(3, $status->questionsneeded);
    }

    /**
     * The status tracks the stage score and counts down the answers still needed.
     *
     * @covers ::qpractice_get_path_status
     */
    public function test_status_tracks_stage_score(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$qpracticeid] = $this->setup_two_stage_path(3);

        qpractice_record_path_answer($qpracticeid, 2, 0, 1);
        qpractice_record_path_answer($qpracticeid, 2, 1, 1);
        $status = qpractice_get_path_status($qpracticeid, 2);

        $this->assertEquals(1, $status->stageno);
        $this->assertEquals(50, $status->stagepercent);
        $this->assertEquals(1, $status->questionsneeded);
    }

    /**
     * On the final stage there is no target and nothing more needed to move on.
     *
     * @covers ::qpractice_get_path_status
     */
    public function test_status_on_final_stage(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$qpracticeid, $categorya, $categoryb] = $this->setup_two_stage_path();

        qpractice_record_path_answer($qpracticeid, 2, 1, 1);
        $status = qpractice_get_path_status($qpracticeid, 2);

        $this->assertEquals(2, $status->stageno);
        $this->assertEquals($categoryb, $status->categoryid);
        $this->assertNull($status->stagepercent);
        $this->assertNull($status->targetpercent);
        $this->assertEquals(0, $status->questionsneeded);
    }

    /**
     * A stage set to stay has no target to show.
     *
     * @covers ::qpractice_get_path_status
     */
    public function test_status_on_stay_stage(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$qpracticeid] = $this->setup_two_stage_path(2);
        $DB->set_field('qpractice_category_path', 'onachieve', 'stay', ['qpracticeid' => $qpracticeid, 'sortorder' => 0]);

        $status = qpractice_get_path_status($qpracticeid, 2);

        $this->assertEquals(1, $status->stageno);
        $this->assertNull($status->targetpercent);
        $this->assertEquals(0, $status->questionsneeded);
    }

    /**
     * An instance without a path has no status.
     *
     * @covers ::qpractice_get_path_status
     */
    public function test_status_without_path(): void {
        global $SITE;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $qpractice = $this->getDataGenerator()->get_plugin_generator('mod_qpractice')
            ->create_instance(['course' => $SITE->id]);

        $this->assertNull(qpractice_get_path_status($qpractice->id, 2));
    }

    /**
     * Set up a three-stage path (categories 101, 102, 103) where stage 0 goes to the
     * category of stage 2 on reaching its 50% target, skipping stage 1.
     *
     * @return int qpracticeid
     */
    private function setup_goto_path(): int {
        global $SITE, $DB;

        $qpractice = $this->getDataGenerator()->get_plugin_generator('mod_qpractice')
            ->create_instance(['course' => $SITE->id]);
        $DB->set_field('qpractice', 'pathmode', 1, ['id' => $qpractice->id]);

        $stages = [
            [101, 50, 'gotocategory', 103],
            [102, 50, 'nextstage', null],
            [103, null, 'nextstage', null],
        ];
        foreach ($stages as $sortorder => [$categoryid, $target, $onachieve, $goto]) {
            $DB->insert_record('qpractice_category_path', (object) [
                'qpracticeid' => $qpractice->id,
                'categoryid' => $categoryid,
                'sortorder' => $sortorder,
                'targetpercent' => $target,
                'onachieve' => $onachieve,
                'gotocategoryid' => $goto,
            ]);
        }
        return $qpractice->id;
    }

    /**
     * Reaching the target on a "go to category" stage moves the student to the stage
     * using that category, skipping the stages in between.
     *
     * @covers ::qpractice_record_path_answer
     */
    public function test_goto_category_skips_ahead(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $qpracticeid = $this->setup_goto_path();

        $this->assertTrue(qpractice_record_path_answer($qpracticeid, 2, 1, 1));

        $this->assertEquals(103, qpractice_current_path_category($qpracticeid, 2));
        $this->assertEquals(3, qpractice_get_path_status($qpracticeid, 2)->stageno);
    }

    /**
     * A "go to category" stage can send the student back to an earlier stage.
     *
     * @covers ::qpractice_record_path_answer
     */
    public function test_goto_category_goes_back(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $qpracticeid = $this->setup_goto_path();
        $DB->set_field('qpractice_category_path', 'onachieve', 'gotocategory', ['qpracticeid' => $qpracticeid, 'sortorder' => 1]);
        $DB->set_field('qpractice_category_path', 'gotocategoryid', 101, ['qpracticeid' => $qpracticeid, 'sortorder' => 1]);
        $DB->insert_record('qpractice_user_path_progress', (object) [
            'qpracticeid' => $qpracticeid,
            'userid' => 2,
            'currentsortorder' => 1,
        ]);

        $this->assertTrue(qpractice_record_path_answer($qpracticeid, 2, 1, 1));

        $this->assertEquals(101, qpractice_current_path_category($qpracticeid, 2));
    }

    /**
     * A "go to category" stage whose category is no longer in the path never advances,
     * and shows no target.
     *
     * @covers ::qpractice_record_path_answer
     * @covers ::qpractice_get_path_status
     */
    public function test_goto_missing_category_does_not_advance(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $qpracticeid = $this->setup_goto_path();
        $DB->set_field('qpractice_category_path', 'gotocategoryid', 999, ['qpracticeid' => $qpracticeid, 'sortorder' => 0]);

        $this->assertNull(qpractice_get_path_status($qpracticeid, 2)->targetpercent);
        $this->assertFalse(qpractice_record_path_answer($qpracticeid, 2, 1, 1));
        $this->assertEquals(101, qpractice_current_path_category($qpracticeid, 2));
    }

    /**
     * A "go to category" stage shows its target in the status.
     *
     * @covers ::qpractice_get_path_status
     */
    public function test_status_on_goto_stage(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $qpracticeid = $this->setup_goto_path();

        $this->assertEquals(50, qpractice_get_path_status($qpracticeid, 2)->targetpercent);
    }
}
