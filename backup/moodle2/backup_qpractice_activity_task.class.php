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
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/qpractice/backup/moodle2/backup_qpractice_stepslib.php');


/**
 * Backup code
 *
 * choice backup task that provides all the settings and steps to perform one
 * complete backup of the activity
 *
 * @package    mod_qpractice
 * @copyright  2019 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qpractice_activity_task extends backup_activity_task {
    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
    }

    /**
     * Defines backup steps to store the instance data and required questions
     */
    protected function define_my_steps() {

        // Generate the qpractice.xml file containing all the qpractice information
        // and annotating used questions.
        $this->add_step(new backup_qpractice_activity_structure_step('qpractice_structure', 'qpractice.xml'));

        // Process all the annotated questions to calculate the question
        // categories needing to be included in backup for this activity
        // plus the categories belonging to the activity context itself.
        $this->add_step(new backup_calculate_question_categories('activity_question_categories'));

        // Clean backup_temp_ids table from questions. We already
        // have used them to detect question_categories and aren't
        // needed anymore.
        $this->add_step(new backup_delete_temp_questions('clean_temp_questions'));
    }

    /**
     * Encodes URLs to the index.php and view.php scripts
     *
     * @param string $content some HTML text that eventually contains URLs to the activity instance scripts
     * @return string the content with the URLs encoded
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        // Link to the list of qpracticezes.
        $search = "/(" . $base . "\/mod\/qpractice\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@qpracticeINDEX*$2@$', $content);

        // Link to qpractice view by moduleid.
        $search = "/(" . $base . "\/mod\/qpractice\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@qpracticeVIEWBYID*$2@$', $content);

        // Link to qpractice view by qpracticeid.
        $search = "/(" . $base . "\/mod\/qpractice\/view.php\?q\=)([0-9]+)/";
        $content = preg_replace($search, '$@qpracticeVIEWBYQ*$2@$', $content);

        return $content;
    }
}
