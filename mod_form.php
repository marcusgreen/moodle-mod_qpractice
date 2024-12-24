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
        $mform->addElement('text', 'name', get_string('qpracticename', 'qpractice'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'qpracticename', 'qpractice');


        $this->standard_intro_elements();

        $mform->addElement('header', 'qpracticefieldset', get_string('categories', 'qpractice'));
        $mform->setExpanded('qpracticefieldset');

        if (!empty($this->current->preferredbehaviour)) {
            $currentbehaviour = $this->current->preferredbehaviour;
        } else {
            $currentbehaviour = '';
        }

        // $course = $this->get_course();
        $coursecontext = context_course::instance($COURSE->id);
        $questioncategories = new question_categories(
            $PAGE->url,
            [$coursecontext],
            10,
            2,

        );

        $this->add_categories($mform, reset($questioncategories->editlists)->items);

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

    public function add_categories($mform, $categories, $depth = 0) {
        foreach($categories as $c) {
            $name ='';
            for($i=0; $i < $depth; $i++) {
                $name .= '&nbsp;&nbsp;&nbsp;&nbsp;';
            }

            $name  .=  $c->name.' ('.$c->questioncount.')';
            $mform->addElement('advcheckbox', "category[$c->id]", null, $name, ['bidden' => true]);
            if($c->children) {
                $depth++;
                $this->add_categories($mform, $c->children, $depth);
                $depth--;
            } else{
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
    public function set_data($default_values) {
        global $DB;
        $mform = $this->_form;

        if (isset($default_values->topcategory)) {
          $this->_form->setDefault('selectcategories', '0');
        } else {
            $this->_form->setDefault('selectcategories', '1');
        }
        //$cats = $mform->getElement('categories');

        $categories = $DB->get_records('qpractice_categories', ['qpracticeid' => $default_values->id]);
        foreach ($categories as $c) {
            $parent = $DB->get_record('question_categories', ['id' => $c->categoryid]);
            $elid = 'id_categories_'.$c->categoryid.'_parent_'.$parent->parent;
            $elid = "category[$c->categoryid]";
            //$elid = "id_category_$c->categoryid";
            // $elid = 'id_category_17';
             //$elid = "category[$c->categoryid]";

            xdebug_break();
             $el = $mform->getElement($elid);
             $el->setChecked(true);

            //$this->_form->setDefault($el, 'checked');
        }
        parent::set_data($default_values);
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

        $categories = optional_param_array('categories', '', PARAM_INT );
        if (!$categories) {
            $errors['categories'] = 'No categories selected';
        }
        if (!isset($data['behaviour'])) {
            $errors['behaviour[adaptive]'] = get_string('selectonebehaviourerror', 'qpractice');
        }

        return $errors;
    }

}