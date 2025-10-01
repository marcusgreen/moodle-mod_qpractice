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
 * Form for creating new instances and editing existing
 * @package    mod_qpractice
 * @copyright  2019 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->libdir . '/questionlib.php');
require_once(dirname(__FILE__) . '/locallib.php');
use qbank_managecategories\helper;

use qbank_managecategories\question_categories;
/**
 * The main qpractice configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_qpractice
 * @copyright  2013 Jayesh Anandani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_qpractice_mod_form extends moodleform_mod {
    /**
     * Create the interface elements
     *
     * @return void
     */
    public function definition() {
        global $PAGE, $CFG, $COURSE, $DB;
        $PAGE->requires->js_call_amd('mod_qpractice/qpractice', 'init');

        $mform = $this->_form;
        $updateid = optional_param('return', 0, PARAM_INT);

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('qpracticename', 'qpractice'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addHelpButton('name', 'qpracticename', 'qpractice');

        $this->standard_intro_elements();

        $mform->addElement('header', 'qpracticefieldset', get_string('categories', 'qpractice'));
        $mform->setExpanded('qpracticefieldset');

        if (!empty($this->current->preferredbehaviour)) {
            $currentbehaviour = $this->current->preferredbehaviour;
        } else {
            $currentbehaviour = '';
        }
        $questioncategories = $this->get_categories($COURSE->id);

        $this->add_categories($mform, $questioncategories);

        $mform->addElement('button', 'select_all_none', 'Select All/None');

        $mform->addElement('header', 'qpracticefieldset', get_string('behaviours', 'qpractice'));

        $behaviours = question_engine::get_behaviour_options($currentbehaviour);

        foreach ($behaviours as $key => $langstring) {
            $enabled = get_config('mod_qpractice', $key);
            if (!in_array('correctness', question_engine::get_behaviour_unused_display_options($key))) {
                $behaviour = 'behaviour[' . $key . ']';
                $mform->addElement('checkbox', $behaviour, null, $langstring);
                $mform->setDefault($behaviour, $enabled);
            }
        }
        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();
        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }

    /**
     * Return all question categories within the given course context.
     *
     * @param int $courseid The course id to fetch categories for.
     * @return array List of categories (stdClass) indexed by id.
     */
    public function get_categories(int $courseid): array {
        global $DB, $PAGE, $COURSE;

        $sql = "select * from {course_modules} where module = 16 and course = :courseid";
        $qbanks = $DB->get_records_sql($sql, ['courseid' => $courseid]);

        $contexts = [];
        foreach ($qbanks as $qbank) {
            $contexts[] = \context_module::instance($qbank->id);
        }

        $cats = new question_categories(
            $PAGE->url,
            $contexts,
            $COURSE->id,
            $COURSE->id
        );
        $categories = [];
        $editlist = $cats->editlists;
        foreach ($editlist as $list) {
            $categories = array_merge($categories, $list->items);
        }

        return $categories ?: [];
    }

    public function add_categories($mform, $categories, $depth = 0) {
        foreach ($categories as $c) {
            $name = '';
            for ($i = 0; $i < $depth; $i++) {
                $name .= '&nbsp;&nbsp;&nbsp;&nbsp;';
            }

            $name  .= $c->name . ' (' . $c->questioncount . ')';
            $mform->addElement('advcheckbox', "categories[$c->id]", null, $name, ['bidden' => true]);
            if (isset($c->children)) {
                $depth++;
                $this->add_categories($mform, $c->children, $depth);
                $depth--;
            } else {
                $depth = 0;
            }
        }
    }

    /**
     * Set the values of the behaviour checkboxes.
     * when editing an existing instance
     * @param array $toform
     * @return void
     */
    public function data_preprocessing(&$toform) {
        if (isset($toform['behaviour'])) {
            $reviewfields = [];
            $reviewfields = explode(',', $toform['behaviour']);
            $behaviours = question_engine::get_behaviour_options(null);
            foreach ($behaviours as $key => $langstring) {
                foreach ($reviewfields as $field => $used) {
                    if ($key == $used) {
                        $toform['behaviour[' . $key . ']'] = 1;
                        break;
                    } else {
                        $toform['behaviour[' . $key . ']'] = 0;
                    }
                }
            }
        }
    }
    /**
     * Load in existing data as form defaults.
     *
     * @param mixed $question object or array of default values
     */
    public function set_data($defaultvalues) {
        global $DB;
        $mform = $this->_form;
        if (isset($defaultvalues->topcategory)) {
            $this->_form->setDefault('selectcategories', '0');
        } else {
            $this->_form->setDefault('selectcategories', '1');
        }

        $categories = $DB->get_records('qpractice_categories', ['qpracticeid' => $defaultvalues->id]);
        foreach ($categories as $c) {
            $parent = $DB->get_record('question_categories', ['id' => $c->categoryid]);
            $elid = 'id_categories_' . $c->categoryid . '_parent_' . $parent->parent;
            $elid = "categories[$c->categoryid]";
            $elid = "id_category_$c->categoryid";
             $elid = "categories[$c->categoryid]";
             xdebug_break();

             $el = $mform->getElement($elid);
             $el->setChecked(true);

        }
        parent::set_data($defaultvalues);
    }


    /**
     * return errors if no behaviour was selected
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $hasvalues = array_filter($data, function ($data) {
            return $data != 0;
        });

        if (!$hasvalues) {
            $errors['categories'] = 'No categories selected';
        }
        if (!isset($data['behaviour'])) {
            $errors['behaviour[adaptive]'] = get_string('selectonebehaviourerror', 'qpractice');
        }

        return $errors;
    }
}
