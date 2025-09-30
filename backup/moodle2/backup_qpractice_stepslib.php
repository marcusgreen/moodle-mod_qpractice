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
            'name', 'intro', 'introformat', 'topcategory', 'behaviour', 'timecreated',
            'timemodified']);

        $sessions = new backup_nested_element('sessions');

        $session = new backup_nested_element('session', ['id'], [
                'qpracticeid', 'questionusageid', 'userid', 'categoryid',
                'typeofpractice', 'time', 'goalpercentage', 'noofquestions',
                'practicedate', 'status', 'totalnoofquestions', 'totalnoofquestionsright',
                'marksobtained', 'totalmarks']);

        $this->add_question_usages($session, 'questionusageid');

        // Build the tree.

        $qpractice->add_child($sessions);
        $sessions->add_child($session);

         // Define sources.
        $qpractice->set_source_table('qpractice', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
               $session->set_source_table(
                   'qpractice_session',
                   ['qpracticeid' => backup::VAR_PARENTID]
               );
        }

        // Define id annotations.
        $session->annotate_ids('user', 'userid');
        $session->annotate_ids('question_categories', 'categoryid');

        // Define file annotations.
        $qpractice->annotate_files('mod_qpractice', 'intro', null); // This file area hasn't itemid.

        // Return the root element (qpractice), wrapped into standard activity structure.
        return $this->prepare_activity_structure($qpractice);
    }
}
