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
 * @since     Moodle 2.9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_qpractice\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Log viewings of qpractice
 *
 * @package    mod_qpractice
 * @copyright  2015 Marcus Green
 * @since     Moodle 2.9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qpractice_report_viewed extends \core\event\base {
    /**
     * initialize
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'qpractice';
    }
    /**
     * Clickable link for the log
     *
     * @return $moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/qpractice/report.php', ['id' => $this->objectid]);
    }


    /**
     * Text written to log
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id {$this->userid} viewed the report for qpractice id :  {$this->objectid}.";
    }
}
