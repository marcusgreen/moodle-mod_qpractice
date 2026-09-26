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
 * Backup code
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_qpractice
 * @copyright  2013 Jayesh Anandani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_qpractice_activity_task
 *
 * @package    mod_qpractice
 * @copyright  2019 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qpractice_activity_structure_step extends backup_questions_activity_structure_step {
    /**
     * Set the table structure up for converting to xml
     *
     * @return void  its not void, run it to find out real type
     */
    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $qpractice = new backup_nested_element('qpractice', ['id'], [
            'name', 'intro', 'introformat', 'topcategory', 'behaviour', 'pathmode',
            'allowwrongonly', 'timecreated', 'timemodified']);

        // Categories offered in free-choice mode.
        $categories = new backup_nested_element('categories');
        $category = new backup_nested_element('category', ['id'], ['categoryid']);

        // Ordered stages for path mode.
        $pathstages = new backup_nested_element('pathstages');
        $pathstage = new backup_nested_element('pathstage', ['id'], [
            'categoryid', 'sortorder', 'targetpercent', 'minquestions', 'onachieve']);

        $sessions = new backup_nested_element('sessions');

        $session = new backup_nested_element('session', ['id'], [
                'questionusageid', 'userid', 'typeofpractice', 'time', 'goalpercentage',
                'noofquestions', 'practicedate', 'status', 'totalnoofquestions',
                'totalnoofquestionsright', 'marksobtained', 'totalmarks', 'wrongonly']);

        $sessioncats = new backup_nested_element('sessioncats');
        $sessioncat = new backup_nested_element('sessioncat', ['id'], ['category']);

        // Each student's position in the path.
        $pathprogresses = new backup_nested_element('pathprogresses');
        $pathprogress = new backup_nested_element('pathprogress', ['id'], [
            'userid', 'currentsortorder', 'stagecorrect', 'stagetotal', 'stageanswered',
            'timemodified']);

        $this->add_question_usages($session, 'questionusageid');

        // Build the tree.
        $qpractice->add_child($categories);
        $categories->add_child($category);

        $qpractice->add_child($pathstages);
        $pathstages->add_child($pathstage);

        $qpractice->add_child($sessions);
        $sessions->add_child($session);
        $session->add_child($sessioncats);
        $sessioncats->add_child($sessioncat);

        $qpractice->add_child($pathprogresses);
        $pathprogresses->add_child($pathprogress);

        // Define sources.
        $qpractice->set_source_table('qpractice', ['id' => backup::VAR_ACTIVITYID]);
        $category->set_source_table('qpractice_categories', ['qpracticeid' => backup::VAR_PARENTID], 'id ASC');
        $pathstage->set_source_table('qpractice_category_path', ['qpracticeid' => backup::VAR_PARENTID], 'sortorder ASC');

        if ($userinfo) {
            $session->set_source_table('qpractice_session', ['qpracticeid' => backup::VAR_PARENTID], 'id ASC');
            $sessioncat->set_source_table('qpractice_session_cats', ['session' => backup::VAR_PARENTID], 'id ASC');
            $pathprogress->set_source_table('qpractice_user_path_progress', ['qpracticeid' => backup::VAR_PARENTID]);
        }

        // Define id annotations.
        $session->annotate_ids('user', 'userid');
        $pathprogress->annotate_ids('user', 'userid');

        // Define file annotations.
        $qpractice->annotate_files('mod_qpractice', 'intro', null); // This file area hasn't itemid.

        // Return the root element (qpractice), wrapped into standard activity structure.
        return $this->prepare_activity_structure($qpractice);
    }
}
