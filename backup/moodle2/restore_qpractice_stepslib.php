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
 * Restore code
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_qpractice
 * @copyright  2013 Jayesh Anandani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one qpractice activity
 *
 * @package    mod_qpractice
 * @copyright  2019 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qpractice_activity_structure_step extends restore_questions_activity_structure_step {
    /**
     * @var stdClass|null The session being restored, held until its question usage has
     * been restored and the new usage id is known.
     */
    protected $currentsession = null;

    /**
     * Convert xml structure to structure for database
     *
     * @return void
     */
    protected function define_structure() {

        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $qpractice = new restore_path_element('qpractice', '/activity/qpractice');
        $paths[] = $qpractice;
        $paths[] = new restore_path_element('qpractice_category', '/activity/qpractice/categories/category');
        $paths[] = new restore_path_element('qpractice_pathstage', '/activity/qpractice/pathstages/pathstage');

        if ($userinfo) {
            $session = new restore_path_element('qpractice_session', '/activity/qpractice/sessions/session');
            $paths[] = $session;
            $this->add_question_usages($session, $paths);
            $paths[] = new restore_path_element(
                'qpractice_sessioncat',
                '/activity/qpractice/sessions/session/sessioncats/sessioncat'
            );
            $paths[] = new restore_path_element('qpractice_pathprogress', '/activity/qpractice/pathprogresses/pathprogress');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Work out which question category a backed-up category id should point at.
     *
     * Follows core's handling of random question categories: use the copy made by this
     * restore if there is one; otherwise, on the same site, keep pointing at the original
     * category if it still exists.
     *
     * @param int $oldcategoryid Category id recorded in the backup.
     * @return int|null New category id, or null if there is no usable category.
     */
    protected function map_question_category(int $oldcategoryid): ?int {
        global $DB;

        if ($newcategoryid = $this->get_mappingid('question_category', $oldcategoryid)) {
            return (int) $newcategoryid;
        }
        if ($this->get_task()->is_samesite() && $DB->record_exists('question_categories', ['id' => $oldcategoryid])) {
            return $oldcategoryid;
        }
        return null;
    }

    /**
     * Where the work happens
     *
     * @param array $data
     * @return void
     */
    protected function process_qpractice(array $data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        if (!empty($data->topcategory)) {
            $data->topcategory = $this->map_question_category((int) $data->topcategory);
        }

        // Insert the qpractice record.
        $newitemid = $DB->insert_record('qpractice', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore a category offered in free-choice mode.
     *
     * @param array $data
     * @return void
     */
    protected function process_qpractice_category(array $data) {
        global $DB;
        $data = (object)$data;

        $categoryid = $this->map_question_category((int) $data->categoryid);
        if ($categoryid === null) {
            return;
        }
        $DB->insert_record('qpractice_categories', (object) [
            'qpracticeid' => $this->get_new_parentid('qpractice'),
            'categoryid' => $categoryid,
        ]);
    }

    /**
     * Restore a path stage. A stage whose category can't be found is left out; students
     * on it fall back to the first stage.
     *
     * @param array $data
     * @return void
     */
    protected function process_qpractice_pathstage(array $data) {
        global $DB;
        $data = (object)$data;

        $categoryid = $this->map_question_category((int) $data->categoryid);
        if ($categoryid === null) {
            return;
        }
        $data->qpracticeid = $this->get_new_parentid('qpractice');
        $data->categoryid = $categoryid;
        $DB->insert_record('qpractice_category_path', $data);
    }

    /**
     * Deal with student sessions. The record is inserted in inform_new_usage_id(), once
     * its question usage has been restored.
     *
     * @param array $data
     * @return void
     */
    protected function process_qpractice_session(array $data) {
        $data = (object)$data;
        $data->qpracticeid = $this->get_new_parentid('qpractice');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->practicedate = $this->apply_date_offset($data->practicedate);

        $this->currentsession = $data;
    }

    /**
     * Save the session being restored now its question usage has a new id.
     *
     * @param int $newusageid
     * @return void
     */
    protected function inform_new_usage_id($newusageid) {
        global $DB;

        $data = $this->currentsession;
        if ($data === null) {
            return;
        }
        $this->currentsession = null;

        $oldid = $data->id;
        $data->questionusageid = $newusageid;
        $newitemid = $DB->insert_record('qpractice_session', $data);
        $this->set_mapping('qpractice_session', $oldid, $newitemid);
    }

    /**
     * Restore a category chosen for a session.
     *
     * @param array $data
     * @return void
     */
    protected function process_qpractice_sessioncat(array $data) {
        global $DB;
        $data = (object)$data;

        $sessionid = $this->get_mappingid('qpractice_session', $this->get_old_parentid('qpractice_session'));
        $categoryid = $this->map_question_category((int) $data->category);
        if (!$sessionid || $categoryid === null) {
            return;
        }
        $DB->insert_record('qpractice_session_cats', (object) [
            'session' => $sessionid,
            'category' => $categoryid,
        ]);
    }

    /**
     * Restore a student's position in the path.
     *
     * @param array $data
     * @return void
     */
    protected function process_qpractice_pathprogress(array $data) {
        global $DB;
        $data = (object)$data;

        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            return;
        }
        $data->qpracticeid = $this->get_new_parentid('qpractice');
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $DB->insert_record('qpractice_user_path_progress', $data);
    }

    /**
     * Deal with files (check this works)
     *
     * @return void
     */
    protected function after_execute() {
        parent::after_execute();
        // Add qpractice related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_qpractice', 'intro', null);
    }
}
