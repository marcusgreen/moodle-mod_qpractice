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

require_once $CFG->dirroot . '/course/moodleform_mod.php';
require_once $CFG->libdir . '/questionlib.php';
require_once dirname(__FILE__) . '/locallib.php';

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
        global $PAGE, $CFG, $COURSE;
        $PAGE->requires->js_call_amd('mod_qpractice/qpractice', 'init');

        $mform = $this->_form;

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

        $course = $this->get_course();
        $coursecontext = context_course::instance($COURSE->id);
        $topcategory = null;
        $categories = qpractice_get_question_categories($coursecontext, $mform, $topcategory);

        $mform->addElement('html', '<div class="categories">');


        $el = $mform->createElement('html', $categories);
        $this->add_checkbox_controller(1);

        $mform->addGroup([$el], 'categories');


        $mform->addElement('html', '</div>');

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

        if (isset($default_values->topcategory)) {
          $this->_form->setDefault('selectcategories', '0');
        } else {
            $this->_form->setDefault('selectcategories', '1');
        }

        $categories = $DB->get_records('qpractice_categories', ['qpracticeid' => $default_values->id]);
        foreach ($categories as $c) {
            $el = 'categories[' . $c->categoryid . ']';
            $this->_form->setDefault($el, true);
        }
        parent::set_data($default_values);
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {

        // if ($data->displaytype['selectcategories'] == 'selectcat') {
        //     $data->topcategory = null;
        // }
        // parent::data_postprocessing($data);
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
        xdebug_break();

        $categories = optional_param_array('categories', '', PARAM_INT );
        if (!$categories) {
            $errors['categories'] = 'No categories selected';
        }
        if (!isset($data['behaviour'])) {
            $errors['behaviour[adaptive]'] = get_string('selectonebehaviourerror', 'qpractice');
        }
        // if ($data['selectcategories'] == 1) {
        //     if (empty($data['categories'])) {
        //         $errors['displaytype'] = get_string('atleastonecategory', 'qpractice');
        //     }
        // }
        return $errors;
    }

}