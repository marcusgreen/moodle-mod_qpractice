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
 * The qpractice_viewed event.
 *
 * @package    mod_qpractice
 * @copyright  2015 Marcus Green
 * @since      Moodle 2.9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_qpractice\event;
/**
 * The qpractice_viewed event.
 *
 * @package    mod_qpractice
 * @copyright  2019 Marcus Green
 * @since      Moodle 2.9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qpractice_summary_viewed extends \core\event\base {
    /**
     * initialization
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'qpractice';
    }

    /**
     * Clickable link in log taking you to the activity
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/qpractice/summary.php', ['id' => $this->objectid]);
    }

    /**
     * text description for the log
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id {$this->userid} viewed the summary for qpractice id :  {$this->objectid}.";
    }
}
