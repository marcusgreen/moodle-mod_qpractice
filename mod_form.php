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

        $mform->addElement('advcheckbox', 'pathmode', get_string('pathmode', 'qpractice'));
        $mform->addHelpButton('pathmode', 'pathmode', 'qpractice');
        $mform->setDefault('pathmode', 0);

        $mform->addElement('advcheckbox', 'allowwrongonly', get_string('allowwrongonly', 'qpractice'));
        $mform->addHelpButton('allowwrongonly', 'allowwrongonly', 'qpractice');
        $mform->setDefault('allowwrongonly', 0);
        $mform->hideIf('allowwrongonly', 'pathmode', 'checked');

        $this->add_path_stages($mform, $banks);

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

        // Free-choice category checkboxes (and the bank picker) are only relevant when
        // path mode is off; hide them (they remain submitted/harmless if left checked
        // from before path mode was turned on, since upsert only runs for the active mode).
        if (!empty($mform->elementExists('otherbankselect'))) {
            $mform->hideIf('otherbankselect', 'pathmode', 'checked');
        }
        $mform->hideIf('select_all_none', 'pathmode', 'checked');
        foreach ($this->flatten_category_ids($banks) as $categoryid) {
            $elid = "categories[$categoryid]";
            if ($mform->elementExists($elid)) {
                $mform->hideIf($elid, 'pathmode', 'checked');
            }
        }

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
            $items = $this->build_category_items($cmid);
            if (empty($items)) {
                continue;
            }
            $cm = get_coursemodule_from_id('qbank', $cmid);
            $banks[] = (object) [
                'name' => format_string($cm->name),
                'items' => $items,
                'shared' => $shared,
            ];
        }

        return $banks;
    }

    /**
     * Build the category tree items for a question bank, across supported core versions.
     *
     * Moodle 5.0's question_categories takes a required, non-nullable $contexts array and
     * exposes the tree through $editlists (keyed by context id). Later cores make $contexts
     * optional (deprecated) and expose a single $editlist. Reflect the constructor to detect
     * which shape this core uses rather than branching on the version number.
     *
     * @param int $cmid The qbank course-module id.
     * @return array The category tree items, keyed by category id, or an empty array.
     */
    protected function build_category_items(int $cmid): array {
        global $PAGE;

        $context = \context_module::instance($cmid);
        $contextsparam = (new \ReflectionMethod(question_categories::class, '__construct'))->getParameters()[1] ?? null;

        if ($contextsparam && !$contextsparam->isOptional()) {
            // Moodle 5.0: pass the module context explicitly and read from $editlists.
            $cats = new question_categories($PAGE->url, [$context], $cmid);
            return $cats->editlists[$context->id]->items ?? [];
        }

        // Later cores: $contexts is deprecated; pass cmid by name and read $editlist.
        $cats = new question_categories($PAGE->url, cmid: $cmid);
        return $cats->editlist->items ?? [];
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
     * Flatten every category id out of the bank tree structure returned by get_categories().
     *
     * @param array $banks List of bank objects, each with ->items (category tree).
     * @return array Flat list of category ids.
     */
    protected function flatten_category_ids(array $banks): array {
        $ids = [];
        $walk = function (array $items) use (&$ids, &$walk) {
            foreach ($items as $c) {
                $ids[] = $c->id;
                if (isset($c->children)) {
                    $walk($c->children);
                }
            }
        };
        foreach ($banks as $bank) {
            $walk($bank->items);
        }
        return $ids;
    }

    /**
     * Flatten the bank tree into a single "categoryid => label" list suitable for a
     * <select>, prefixed with the bank name and indented to show depth.
     *
     * @param array $banks List of bank objects, each with ->name and ->items (category tree).
     * @return array Flat options list, categoryid => label.
     */
    protected function flatten_categories_for_select(array $banks): array {
        $options = [];
        $walk = function (array $items, string $prefix, int $depth) use (&$options, &$walk) {
            foreach ($items as $c) {
                if (!isset($c->children) && $c->questioncount == 0) {
                    continue;
                }
                $indent = str_repeat('— ', $depth);
                $options[$c->id] = $prefix . $indent . $c->name . ' (' . $c->questioncount . ')';
                if (isset($c->children)) {
                    $walk($c->children, $prefix, $depth + 1);
                }
            }
        };
        foreach ($banks as $bank) {
            $walk($bank->items, $bank->name . ' / ', 0);
        }
        return $options;
    }

    /**
     * Add the repeatable "category path" stage rows (category, target percent, on-achieve
     * action). Hidden unless "pathmode" is checked.
     *
     * @param MoodleQuickForm $mform The Moodle form object.
     * @param array $banks List of bank objects, used to build the category dropdown.
     * @return void
     */
    protected function add_path_stages(MoodleQuickForm $mform, array $banks): void {
        $categoryoptions = ['' => get_string('choosedots')] + $this->flatten_categories_for_select($banks);
        $onachieveoptions = [
            'nextstage' => get_string('onachieve_nextstage', 'qpractice'),
            'stay' => get_string('onachieve_stay', 'qpractice'),
        ];

        $existingstages = $this->get_path_stages();
        $repeatno = max(1, count($existingstages));

        $repeatarray = [
            $mform->createElement('select', 'pathstagecategory', get_string('pathstagecategory', 'qpractice'), $categoryoptions),
            $mform->createElement('text', 'pathstagetarget', get_string('pathstagetarget', 'qpractice'), ['size' => 3]),
            $mform->createElement('select', 'pathstageonachieve', get_string('onachieve', 'qpractice'), $onachieveoptions),
        ];
        $repeatoptions = [
            'pathstagecategory' => ['type' => PARAM_INT],
            'pathstagetarget' => ['type' => PARAM_INT],
            'pathstageonachieve' => ['type' => PARAM_ALPHA],
        ];

        $stagecount = $this->repeat_elements(
            $repeatarray,
            $repeatno,
            $repeatoptions,
            'pathstagerepeats',
            'pathstageadd',
            1,
            get_string('pathaddstage', 'qpractice'),
            true
        );

        for ($i = 0; $i < $stagecount; $i++) {
            $mform->hideIf("pathstagecategory[$i]", 'pathmode', 'notchecked');
            $mform->hideIf("pathstagetarget[$i]", 'pathmode', 'notchecked');
            $mform->hideIf("pathstageonachieve[$i]", 'pathmode', 'notchecked');
        }
        $mform->hideIf('pathstageadd', 'pathmode', 'notchecked');
    }

    /**
     * Return the saved path stages for this instance, ordered by sortorder.
     *
     * @return array List of stdClass rows from qpractice_category_path (empty when creating).
     */
    protected function get_path_stages(): array {
        global $DB;

        if (empty($this->_instance)) {
            return [];
        }
        return array_values($DB->get_records('qpractice_category_path', ['qpracticeid' => $this->_instance], 'sortorder ASC'));
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
            $used = explode(',', $toform['behaviour']);
            foreach (array_keys(question_engine::get_behaviour_options(null)) as $key) {
                $toform['behaviour[' . $key . ']'] = in_array($key, $used) ? 1 : 0;
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
            $elid = "categories[$c->categoryid]";
            // Skip saved categories that are no longer rendered (deleted, or in a
            // bank the user can no longer access) so the form does not fatal.
            if ($mform->elementExists($elid)) {
                $mform->getElement($elid)->setChecked(true);
            }
        }

        $stages = $DB->get_records('qpractice_category_path', ['qpracticeid' => $defaultvalues->id], 'sortorder ASC');
        $pathstagecategory = [];
        $pathstagetarget = [];
        $pathstageonachieve = [];
        foreach ($stages as $stage) {
            $pathstagecategory[] = $stage->categoryid;
            $pathstagetarget[] = $stage->targetpercent;
            $pathstageonachieve[] = $stage->onachieve;
        }
        if ($pathstagecategory) {
            $defaultvalues->pathstagecategory = $pathstagecategory;
            $defaultvalues->pathstagetarget = $pathstagetarget;
            $defaultvalues->pathstageonachieve = $pathstageonachieve;
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

        if (!empty($data['pathmode'])) {
            $errors += $this->validate_path_stages($data);
        } else {
            // Only inspect the category checkboxes, not the whole submission.
            $selected = array_filter($data['categories'] ?? [], function ($checked) {
                return $checked != 0;
            });

            if (!$selected) {
                // Anchor the error to the always-present button that sits under the list.
                $errors['select_all_none'] = get_string('nocategoriesselected', 'qpractice');
            }
        }

        if (!isset($data['behaviour'])) {
            $errors['behaviour[adaptive]'] = get_string('selectonebehaviourerror', 'qpractice');
        }

        return $errors;
    }

    /**
     * Validate the repeatable path stage rows: every stage needs a category, no
     * category may appear twice, and every non-final stage needs a target percent
     * (unless it's explicitly set to "stay" rather than advance).
     *
     * @param array $data Submitted form data.
     * @return array Errors keyed by element name.
     */
    protected function validate_path_stages(array $data): array {
        $errors = [];
        $categories = $data['pathstagecategory'] ?? [];
        $targets = $data['pathstagetarget'] ?? [];
        $onachieve = $data['pathstageonachieve'] ?? [];

        $seen = [];
        $lastfilled = -1;
        foreach ($categories as $i => $categoryid) {
            if ((int) $categoryid <= 0) {
                continue;
            }
            $lastfilled = $i;
            if (isset($seen[$categoryid])) {
                $errors["pathstagecategory[$i]"] = get_string('pathduplicatecategory', 'qpractice', $categoryid);
            }
            $seen[$categoryid] = true;
        }

        if ($lastfilled < 0) {
            $errors['pathstagecategory[0]'] = get_string('pathstagerequired', 'qpractice');
            return $errors;
        }

        foreach ($categories as $i => $categoryid) {
            if ((int) $categoryid <= 0) {
                continue;
            }
            if ($i === $lastfilled) {
                // Final stage: no target required, it's open-ended practice.
                continue;
            }
            $target = $targets[$i] ?? '';
            $stays = ($onachieve[$i] ?? 'nextstage') === 'stay';
            if ($target === '' && !$stays) {
                $errors["pathstagetarget[$i]"] = get_string('pathstagetargetrequired', 'qpractice', $i + 1);
            } else if ($target !== '' && ((int) $target < 1 || (int) $target > 100)) {
                $errors["pathstagetarget[$i]"] = get_string('pathstagetargetrequired', 'qpractice', $i + 1);
            }
        }

        return $errors;
    }
}
