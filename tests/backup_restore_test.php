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
 * Backup and restore tests for mod_qpractice.
 *
 * @package    mod_qpractice
 * @category   test
 * @copyright  2026
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_qpractice;

use backup;
use backup_controller;
use restore_controller;
use restore_dbops;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/mod/qpractice/locallib.php');

/**
 * Tests that settings, path stages and student data survive backup and restore.
 *
 * @package     mod_qpractice
 * @copyright   2026
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \backup_qpractice_activity_structure_step
 * @covers      \restore_qpractice_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Create a course with a question bank holding two categories, a path-mode qpractice
     * using them, and a student with a session and path progress.
     *
     * @return array [course, qpractice, student, category A, category B]
     */
    private function create_course_with_path(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');

        $qbank = $generator->create_module('qbank', ['course' => $course->id]);
        $bankcontext = \context_module::instance($qbank->cmid);
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $categorya = $questiongenerator->create_question_category(['contextid' => $bankcontext->id, 'name' => 'Stage A']);
        $categoryb = $questiongenerator->create_question_category(['contextid' => $bankcontext->id, 'name' => 'Stage B']);
        $questiongenerator->create_question('truefalse', null, ['category' => $categorya->id]);
        $questiongenerator->create_question('truefalse', null, ['category' => $categoryb->id]);

        $qpractice = $generator->create_module('qpractice', ['course' => $course->id]);
        $DB->set_field('qpractice', 'pathmode', 1, ['id' => $qpractice->id]);
        $DB->set_field('qpractice', 'allowwrongonly', 1, ['id' => $qpractice->id]);
        $DB->insert_record('qpractice_categories', ['qpracticeid' => $qpractice->id, 'categoryid' => $categorya->id]);
        $DB->insert_record('qpractice_category_path', [
            'qpracticeid' => $qpractice->id,
            'categoryid' => $categorya->id,
            'sortorder' => 0,
            'targetpercent' => 60,
            'minquestions' => 4,
            'onachieve' => 'nextstage',
        ]);
        $DB->insert_record('qpractice_category_path', [
            'qpracticeid' => $qpractice->id,
            'categoryid' => $categoryb->id,
            'sortorder' => 1,
            'targetpercent' => null,
            'minquestions' => 0,
            'onachieve' => 'nextstage',
        ]);

        $this->setUser($student);
        qpractice_record_path_answer($qpractice->id, $student->id, 1, 1);
        qpractice_session_create((object) [
            'behaviour' => 'interactive',
            'instanceid' => $qpractice->id,
            'categories' => [$categorya->id => 1],
        ], \context_module::instance($qpractice->cmid));
        $this->setAdminUser();

        return [$course, $qpractice, $student, $categorya, $categoryb];
    }

    /**
     * Back up a whole course with user data and restore it as a new course.
     *
     * @param \stdClass $course
     * @return int New course id.
     */
    private function backup_and_restore_course(\stdClass $course): int {
        global $USER;

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();

        // A general backup produces an .mbz; unpack it where the restore expects it.
        $backupid = 'qpractice_' . random_string(10);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), make_backup_temp_directory($backupid));

        $newcourseid = restore_dbops::create_new_course(
            $course->fullname . ' copy',
            $course->shortname . '_copy',
            $course->category
        );
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }

    /**
     * A course restore brings the path, its settings and student data across, pointing at
     * the restored copies of the question categories.
     */
    public function test_course_restore_keeps_path_and_user_data(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $qpractice, $student, $categorya, $categoryb] = $this->create_course_with_path();
        $newcourseid = $this->backup_and_restore_course($course);

        $newqpractice = $DB->get_record('qpractice', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertEquals(1, $newqpractice->pathmode);
        $this->assertEquals(1, $newqpractice->allowwrongonly);

        // Stages point at the restored categories, not the originals.
        $stages = array_values($DB->get_records(
            'qpractice_category_path',
            ['qpracticeid' => $newqpractice->id],
            'sortorder ASC'
        ));
        $this->assertCount(2, $stages);
        $this->assertNotEquals($categorya->id, $stages[0]->categoryid);
        $this->assertEquals('Stage A', $DB->get_field('question_categories', 'name', ['id' => $stages[0]->categoryid]));
        $this->assertEquals('Stage B', $DB->get_field('question_categories', 'name', ['id' => $stages[1]->categoryid]));
        $this->assertEquals(60, $stages[0]->targetpercent);
        $this->assertEquals(4, $stages[0]->minquestions);
        $this->assertNull($stages[1]->targetpercent);

        $freechoice = $DB->get_fieldset_select('qpractice_categories', 'categoryid', 'qpracticeid = ?', [$newqpractice->id]);
        $this->assertEquals([$stages[0]->categoryid], array_map('intval', $freechoice));

        // Student progress through the path.
        $progress = $DB->get_record(
            'qpractice_user_path_progress',
            ['qpracticeid' => $newqpractice->id, 'userid' => $student->id],
            '*',
            MUST_EXIST
        );
        $this->assertEquals(0, $progress->currentsortorder);
        $this->assertEquals(1, $progress->stageanswered);
        $this->assertEquals(1, $progress->stagecorrect);

        // The session belongs to the new instance and has its own question usage.
        $oldsession = $DB->get_record('qpractice_session', ['qpracticeid' => $qpractice->id], '*', MUST_EXIST);
        $session = $DB->get_record('qpractice_session', ['qpracticeid' => $newqpractice->id], '*', MUST_EXIST);
        $this->assertEquals($student->id, $session->userid);
        $this->assertNotEquals($oldsession->questionusageid, $session->questionusageid);
        $this->assertTrue($DB->record_exists('question_usages', ['id' => $session->questionusageid]));
        $this->assertEquals(
            [$stages[0]->categoryid],
            array_map('intval', $DB->get_fieldset_select('qpractice_session_cats', 'category', 'session = ?', [$session->id]))
        );
    }

    /**
     * Duplicating the activity in the same course keeps using the same categories, as
     * the question bank they live in is not part of the activity backup.
     */
    public function test_duplicate_keeps_original_categories(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $qpractice, , $categorya, $categoryb] = $this->create_course_with_path();
        $newcm = (new \core_courseformat\local\cmactions($course))->duplicate($qpractice->cmid);

        $stages = $DB->get_fieldset_select(
            'qpractice_category_path',
            'categoryid',
            'qpracticeid = ? ORDER BY sortorder',
            [$newcm->instance]
        );
        $this->assertEquals([$categorya->id, $categoryb->id], array_map('intval', $stages));
        $this->assertFalse($DB->record_exists('qpractice_user_path_progress', ['qpracticeid' => $newcm->instance]));
    }
}
