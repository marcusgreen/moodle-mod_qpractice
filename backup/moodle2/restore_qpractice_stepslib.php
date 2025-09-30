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
     * Convert xml structure to structure for database
     *
     * @return void
     */
    protected function define_structure() {

        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $qpractice = new restore_path_element('qpractice', '/activity/qpractice');
        $paths[] = $qpractice;

        if ($userinfo) {
            $session = new restore_path_element('qpractice_session', '/activity/qpractice/sessions/session');
            $paths[] = $session;
            $this->add_question_usages($session, $paths);
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Where the work happens
     *
     * @param array $data
     * @return void
     */
    protected function process_qpractice(array $data) {
        global $CFG, $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the qpractice record.
        $newitemid = $DB->insert_record('qpractice', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }
    /**
     * Deal with student sessions
     *
     * @param array $data
     * @return void
     */
    protected function process_qpractice_session(array $data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->qpractice = $this->get_new_parentid('qpractice');

        $data->userid = $this->get_mappingid('user', $data->userid);

        $data->practicedate = $this->apply_date_offset($data->practicedate);

        $DB->insert_record('qpractice_session', $data);
    }

    /**
     * Abstract in parent so this is an empty implementation
     * @param int $newusageid
     * @return void
     */
    protected function inform_new_usage_id($newusageid) {
    }

    /**
     * Deal with files check this works
     * @todo check this works
     *
     * @return void
     */
    protected function after_execute() {
        parent::after_execute();
        // Add qpractice related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_qpractice', 'intro', null);
    }
}
