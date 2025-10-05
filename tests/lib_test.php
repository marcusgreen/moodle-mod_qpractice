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
 * Unit tests for (some of) mod/qpractice/locallib.php.
 *
 * @package    mod_qpractice
 * @category   test
 * @copyright  2019 Marcus Green
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * */
namespace mod_qpractice;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/qpractice/lib.php');


/**
 * PHPunit tests of the qpractice (question practice) moodle activity
 *
 * @package     mod_qpractice
 * @copyright   2023 Marcus Green
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *  */
final class lib_test extends \advanced_testcase {
    /**
     * instance of question practice for use
     * in other methods
     *
     * @var stdClass
     */
    public $qp;

    /**
     * add an instance and check the id that is returned
     *
     * @return void
     */
    public function test_qpractice_add_instance(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $_POST['categories'] = [];
        $id = qpractice_add_instance($this->qp);
        $this->assertIsInt($id);
    }

    /**
     * Create a session and check that an int is returned for the sessionid
     *
     * @return void
     */
    public function test_qpractice_session_create(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $context = \context_module::instance($this->qp->coursemodule);
        $this->qp->instanceid = qpractice_add_instance($this->qp);
        $sessionid = qpractice_session_create($this->qp, $context);
        $this->assertIsInt($sessionid);
    }
    /**
     * Create an instance on a course then delete it.
     * Check that the instance really was deleted.
     */
    public function test_qpractice_delete_instance(): void {
        global $SITE, $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        // Setup a qpractice instance.
        $qpracticegenerator = $this->getDataGenerator()->get_plugin_generator('mod_qpractice');
        $qpractice = $qpracticegenerator->create_instance(['course' => $SITE->id]);
        qpractice_delete_instance($qpractice->id);

        // Check that the qpractice was removed.
        $count = $DB->count_records('qpractice', ['id' => $qpractice->id]);
        $this->assertEquals(0, $count);
    }
    /**
     * initialise globally available object for user
     * in other methods.
     *
     * @return void
     */
    public function setup(): void {
        global $SITE;
        $qpracticegenerator = $this->getDataGenerator()->get_plugin_generator('mod_qpractice');
        $qpractice = $qpracticegenerator->create_instance(['course' => $SITE->id]);
        $this->qp = new \stdClass();
        $this->qp->name = 'QP1';
        $this->qp->topcategory = 62;
        $this->qp->visible = 1;
        $this->qp->visibleoncoursepage = 1;
        $this->qp->cmidnumber = "";
        $this->qp->availabilityconditionsjson = "";
        $this->qp->behaviour = ['interactive'];
        $this->qp->course = 2;
        $this->qp->categories = [];

        $this->qp->coursemodule = $qpractice->cmid;
    }
}
