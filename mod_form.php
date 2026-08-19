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
        $banks = $this->get_categories($COURSE->id);

        $banks = array_values($banks);
        if (count($banks) <= 1) {
            // Only one bank available: show its categories directly, no dropdown needed.
            foreach ($banks as $bank) {
                $this->add_categories($mform, $bank->items);
            }
        } else {
            // Multiple banks: a dropdown at the top of the display selects which bank's
            // categories are shown. Only one bank is visible at a time; selecting another
            // hides the current one. Hidden categories still remain selectable/submitted.
            $selectedids = $this->get_selected_category_ids();
            $preselect = 0;
            $found = false;
            $options = [];
            foreach ($banks as $i => $bank) {
                $options[$i] = $bank->name;
                if (!$found && $this->bank_has_selected($bank->items, $selectedids)) {
                    $preselect = $i;
                    $found = true;
                }
            }

            $mform->addElement('select', 'otherbankselect', get_string('questionbank', 'qpractice'), $options);
            $mform->setDefault('otherbankselect', $preselect);

            foreach ($banks as $i => $bank) {
                $divattrs = ['id' => 'qp-otherbank-' . $i, 'class' => 'qp-otherbank'];
                if ($i !== $preselect) {
                    $divattrs['hidden'] = 'hidden';
                }
                $mform->addElement('html', html_writer::start_tag('div', $divattrs));
                $this->add_categories($mform, $bank->items);
                $mform->addElement('html', html_writer::end_tag('div'));
            }
        }

        $mform->addElement(
            'button',
            'select_all_none',
            get_string('selectallnone', 'qpractice'),
            ['class' => 'qpbtn']
        );

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
     * Return the selectable question categories, grouped by question bank.
     *
     * Includes the question banks in the given course plus any shareable banks
     * the user may use that live within this course's category hierarchy (or at
     * site level). Each returned bank carries its full category tree so the tree
     * can be rendered with question counts and descriptions.
     *
     * @param int $courseid The course id to fetch categories for.
     * @return array List of banks, each a stdClass with ->name and ->items (category tree).
     */
    public function get_categories(int $courseid): array {
        global $DB, $PAGE;

        $module = $DB->get_record('modules', ['name' => 'qbank']);
        if (!$module) {
            return [];
        }

        // Collect candidate qbank course-module ids, mapped to whether the bank is
        // "shared" (from another context). This course's own banks come first and
        // are not shared, so they display up front.
        $cmids = [];
        $localbanks = $DB->get_records(
            'course_modules',
            ['module' => $module->id, 'course' => $courseid, 'deletioninprogress' => 0]
        );
        foreach ($localbanks as $cm) {
            $cmids[$cm->id] = false;
        }

        // ...then shareable banks the user may use, limited to this course's
        // category ancestry (or site-level shared resources).
        $allowedcats = $this->get_category_ancestry($courseid);
        $sharedbanks = \core_question\local\bank\question_bank_helper::get_activity_instances_with_shareable_questions(
            havingcap: ['moodle/question:useall'],
        );
        foreach ($sharedbanks as $bank) {
            $bankcourse = $bank->cminfo->get_course();
            if (
                ($bankcourse->id == SITEID || isset($allowedcats[$bankcourse->category]))
                    && !isset($cmids[$bank->cminfo->id])
            ) {
                $cmids[$bank->cminfo->id] = true;
            }
        }

        if (empty($cmids)) {
            $msg = get_string('noquestionbanks', 'qpractice');
            \core\notification::add($msg, \core\notification::WARNING);
            return [];
        }

        // Build the full category tree (with counts and descriptions) for each bank.
        $banks = [];
        foreach ($cmids as $cmid => $shared) {
            $cats = new question_categories($PAGE->url, null, $cmid);
            if (empty($cats->editlist->items)) {
                continue;
            }
            $cm = get_coursemodule_from_id('qbank', $cmid);
            $banks[] = (object) [
                'name' => format_string($cm->name),
                'items' => $cats->editlist->items,
                'shared' => $shared,
            ];
        }

        return $banks;
    }

    /**
     * Return the given course's category id and all of its ancestor category ids.
     *
     * @param int $courseid The course id.
     * @return array Set of category ids, keyed by id, that make up the ancestry.
     */
    protected function get_category_ancestry(int $courseid): array {
        global $DB;

        $catid = (int) $DB->get_field('course', 'category', ['id' => $courseid]);
        $ancestry = [];
        if ($catid) {
            $ancestry[$catid] = $catid;
            $cat = \core_course_category::get($catid, IGNORE_MISSING);
            if ($cat) {
                foreach ($cat->get_parents() as $parentid) {
                    $ancestry[$parentid] = $parentid;
                }
            }
        }
        return $ancestry;
    }

    /**
     * Return the question category ids already selected for this instance.
     *
     * @return array List of selected category ids (empty when creating a new instance).
     */
    protected function get_selected_category_ids(): array {
        global $DB;

        if (empty($this->_instance)) {
            return [];
        }
        return $DB->get_fieldset_select('qpractice_categories', 'categoryid', 'qpracticeid = ?', [$this->_instance]);
    }

    /**
     * Recursively check whether a bank's category tree contains any selected category.
     *
     * @param array $items The category tree items.
     * @param array $selectedids The selected category ids.
     * @return bool True if any category (at any depth) is selected.
     */
    protected function bank_has_selected(array $items, array $selectedids): bool {
        foreach ($items as $c) {
            if (in_array($c->id, $selectedids)) {
                return true;
            }
            if (isset($c->children) && $this->bank_has_selected($c->children, $selectedids)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Recursively adds category checkboxes to the form.
     *
     * @param MoodleQuickForm $mform The Moodle form object to which the checkboxes will be added.
     * @param array $categories An array of category objects, each containing properties like id, name, questioncount, and children.
     * @param int $depth The current depth of the category in the tree. Defaults to 0.
     * @return void This method modifies the $mform object by adding checkboxes.
     */
    public function add_categories($mform, $categories, $depth = 0) {
        foreach ($categories as $c) {
            // Skip categories with no children and no questions.
            if (!isset($c->children) && $c->questioncount == 0) {
                continue;
            }

            $nameattrs = ['class' => 'category_name'];
            // Show the category description (if any) as a hover tooltip.
            if (!empty($c->info)) {
                $description = content_to_text(
                    format_text($c->info, $c->infoformat ?? FORMAT_HTML, ['context' => $this->context]),
                    false
                );
                if ($description !== '') {
                    $nameattrs['title'] = $description;
                }
            }
            $labelattrs = ['class' => 'qp-category-label', 'data-depth' => $depth];
            if ($depth > 0) {
                // Indent proportionally to tree depth (scales beyond Bootstrap's capped ps-* utilities).
                $labelattrs['style'] = 'padding-left: ' . ($depth * 1.5) . 'rem;';
            }
            $label = html_writer::span(
                html_writer::span($c->name, '', $nameattrs)
                    . html_writer::span('(' . $c->questioncount . ')', 'question_count'),
                '',
                $labelattrs
            );
            $mform->addElement('advcheckbox', "categories[$c->id]", null, $label);
            if (isset($c->children)) {
                $depth++;
                $this->add_categories($mform, $c->children, $depth);
                $depth--;
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
     * @param mixed $defaultvalues object or array of default values
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
