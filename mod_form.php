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
    /** @var array HTML shown by the stage category autocomplete, keyed by category id. */
    protected static array $categoryoptionhtml = [];

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
     * Return the HTML the stage category autocomplete shows for a category.
     *
     * @param string $value Category id.
     * @return string|false HTML, or false to use the plain label.
     */
    public static function category_option_html($value) {
        return self::$categoryoptionhtml[$value] ?? false;
    }

    /**
     * Flatten the bank tree into options for the stage category autocomplete.
     *
     * Returns plain labels ("Past (28) — QPBank › Grammar › Tenses"), used without
     * JavaScript and by screen readers, plus matching HTML that the autocomplete shows
     * instead: indented by depth with a tree guide in the list, and name then muted path
     * once selected. The path stays in the HTML (visually hidden in the list) so a search
     * for a parent's name also finds everything under it.
     *
     * Empty categories are left out unless they have children, where they are kept (muted)
     * to show the tree's shape; validation stops them being chosen.
     *
     * @param array $banks List of bank objects, each with ->name and ->items (category tree).
     * @return array [categoryid => label, categoryid => html]
     */
    protected function flatten_categories_for_select(array $banks): array {
        $labels = [];
        $html = [];
        $walk = function (array $items, array $path) use (&$labels, &$html, &$walk) {
            foreach ($items as $c) {
                $haschildren = !empty($c->children);
                if ($c->questioncount > 0 || $haschildren) {
                    $a = (object) [
                        'name' => format_string($c->name),
                        'count' => $c->questioncount,
                        'path' => implode(' › ', array_map('format_string', $path)),
                    ];
                    $depth = count($path) - 1;
                    $labels[$c->id] = get_string('pathstagecategoryoption', 'qpractice', $a);
                    $html[$c->id] = html_writer::span(
                        ($depth > 0 ? html_writer::span('└', 'qp-catopt-guide', ['aria-hidden' => 'true']) : '')
                            . html_writer::span($a->name, 'qp-catopt-name')
                            . ' ' . html_writer::span('(' . $a->count . ')', 'qp-catopt-count')
                            . ' ' . html_writer::span($a->path, 'qp-catopt-path'),
                        'qp-catopt' . ($depth === 0 ? ' qp-catopt-top' : '') . ($c->questioncount > 0 ? '' : ' qp-catopt-empty'),
                        ['style' => '--qp-catdepth: ' . $depth]
                    );
                }
                if ($haschildren) {
                    $walk($c->children, array_merge($path, [$c->name]));
                }
            }
        };
        foreach ($banks as $bank) {
            $walk($bank->items, [$bank->name]);
        }
        return [$labels, $html];
    }

    /**
     * Add the repeatable "category path" stage rows (stage heading, category, target
     * percent, on-achieve action, remove button). Hidden unless "pathmode" is checked.
     *
     * @param MoodleQuickForm $mform The Moodle form object.
     * @param array $banks List of bank objects, used to build the category dropdown.
     * @return void
     */
    protected function add_path_stages(MoodleQuickForm $mform, array $banks): void {
        global $DB;

        [$categorylabels, self::$categoryoptionhtml] = $this->flatten_categories_for_select($banks);
        $categoryoptions = $categorylabels;
        $onachieveoptions = [
            'nextstage' => get_string('onachieve_nextstage', 'qpractice'),
            'stay' => get_string('onachieve_stay', 'qpractice'),
        ];

        $existingstages = $this->get_path_stages();
        $repeatno = max(1, count($existingstages));

        // A saved stage's category may since have been emptied; keep it selectable, or the
        // autocomplete would silently drop it.
        foreach ($existingstages as $stage) {
            if (!isset($categoryoptions[$stage->categoryid])) {
                $name = $DB->get_field('question_categories', 'name', ['id' => $stage->categoryid]);
                if ($name !== false) {
                    $categoryoptions[$stage->categoryid] = format_string($name);
                }
            }
        }

        // Labels are placeholders here; they are numbered in relabel_path_stages() once
        // we know which stages survived any "Remove stage" clicks.
        $repeatarray = [
            $mform->createElement('static', 'pathstageheading', ''),
            $mform->createElement('autocomplete', 'pathstagecategory', '', $categoryoptions, [
                'placeholder' => get_string('pathstagecategorysearch', 'qpractice'),
                'noselectionstring' => get_string('pathstagecategorynone', 'qpractice'),
                // A closure can't be used: repeat_elements() serializes each element to copy it.
                'valuehtmlcallback' => [self::class, 'category_option_html'],
            ]),
            $mform->createElement('text', 'pathstagetarget', '', ['size' => 3]),
            $mform->createElement('text', 'pathstageminquestions', '', ['size' => 3]),
            $mform->createElement('select', 'pathstageonachieve', '', $onachieveoptions),
            $mform->createElement('submit', 'pathstagedelete', '', [], false),
        ];
        $repeatoptions = [
            'pathstagecategory' => ['type' => PARAM_INT],
            'pathstagetarget' => ['type' => PARAM_INT],
            'pathstageminquestions' => ['type' => PARAM_INT, 'default' => 0],
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
            true,
            'pathstagedelete'
        );

        $this->relabel_path_stages($mform, $stagecount);
        $mform->hideIf('pathstageadd', 'pathmode', 'notchecked');
    }

    /**
     * Number the stages that are still on the form, set up the final stage, and hide
     * everything unless path mode is on.
     *
     * repeat_elements() numbers labels by repeat index, so after a stage is removed the
     * rest would read "Stage 1, Stage 3". Number them by position instead.
     *
     * The final stage has nowhere to advance to, so its target and "when target reached"
     * fields are replaced with a note. On other stages the target is hidden when the
     * stage is set to stay, as it then has no effect.
     *
     * @param MoodleQuickForm $mform The Moodle form object.
     * @param int $stagecount Number of repeats, including removed ones.
     * @return void
     */
    protected function relabel_path_stages(MoodleQuickForm $mform, int $stagecount): void {
        // Stages not removed by their "Remove stage" button.
        $indexes = array_values(array_filter(
            range(0, $stagecount - 1),
            fn($i) => $mform->elementExists("pathstageheading[$i]")
        ));
        $lastindex = end($indexes);

        foreach ($indexes as $position => $i) {
            $stageno = $position + 1;
            // Each of these fields has a lang string of the same name taking the stage number.
            $fields = ['pathstagecategory', 'pathstagetarget', 'pathstageminquestions', 'pathstageonachieve'];

            if ($i === $lastindex) {
                $mform->removeElement("pathstagetarget[$i]");
                $mform->removeElement("pathstageminquestions[$i]");
                $mform->removeElement("pathstageonachieve[$i]");
                $fields = ['pathstagecategory'];
                $mform->insertElementBefore(
                    $mform->createElement('static', "pathstagefinal[$i]", '', get_string('pathstagefinal', 'qpractice')),
                    "pathstagedelete[$i]"
                );
                $mform->hideIf("pathstagefinal[$i]", 'pathmode', 'notchecked');
            } else {
                $mform->addHelpButton("pathstagetarget[$i]", 'pathstagetargethelp', 'qpractice');
                $mform->hideIf("pathstagetarget[$i]", "pathstageonachieve[$i]", 'eq', 'stay');
                $mform->addHelpButton("pathstageminquestions[$i]", 'pathstageminquestionshelp', 'qpractice');
                $mform->hideIf("pathstageminquestions[$i]", "pathstageonachieve[$i]", 'eq', 'stay');
            }

            $mform->getElement("pathstageheading[$i]")->setLabel(get_string('pathstage', 'qpractice', $stageno));
            foreach ($fields as $name) {
                $mform->getElement("{$name}[$i]")->setLabel(get_string($name, 'qpractice', $stageno));
            }
            $mform->getElement("pathstagedelete[$i]")->setValue(get_string('pathremovestage', 'qpractice', $stageno));

            foreach (array_merge(['pathstageheading', 'pathstagedelete'], $fields) as $name) {
                $mform->hideIf("{$name}[$i]", 'pathmode', 'notchecked');
            }
        }
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
        $pathstageminquestions = [];
        $pathstageonachieve = [];
        foreach ($stages as $stage) {
            $pathstagecategory[] = $stage->categoryid;
            $pathstagetarget[] = $stage->targetpercent;
            $pathstageminquestions[] = $stage->minquestions;
            $pathstageonachieve[] = $stage->onachieve;
        }
        if ($pathstagecategory) {
            $defaultvalues->pathstagecategory = $pathstagecategory;
            $defaultvalues->pathstagetarget = $pathstagetarget;
            $defaultvalues->pathstageminquestions = $pathstageminquestions;
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
        global $DB;

        $errors = [];
        $categories = $data['pathstagecategory'] ?? [];
        $targets = $data['pathstagetarget'] ?? [];
        $minquestions = $data['pathstageminquestions'] ?? [];
        $onachieve = $data['pathstageonachieve'] ?? [];

        $seen = [];
        $lastfilled = -1;
        foreach ($categories as $i => $categoryid) {
            if ((int) $categoryid <= 0) {
                continue;
            }
            $lastfilled = $i;
            if (!$DB->record_exists('question_bank_entries', ['questioncategoryid' => $categoryid])) {
                $errors["pathstagecategory[$i]"] = get_string('pathstagecategoryempty', 'qpractice');
            }
            if (isset($seen[$categoryid])) {
                $errors["pathstagecategory[$i]"] = get_string('pathduplicatecategory', 'qpractice', $categoryid);
            }
            $seen[$categoryid] = true;
        }

        if ($lastfilled < 0) {
            // Stage 0 may have been removed, so attach the error to whichever stage is first.
            $first = array_key_first($categories);
            $errors[$first === null ? 'pathmode' : "pathstagecategory[$first]"] =
                get_string('pathstagerequired', 'qpractice');
            return $errors;
        }

        // Removed stages leave gaps in the indexes; number stages by position, as the form does.
        $stageno = 0;
        foreach ($categories as $i => $categoryid) {
            $stageno++;
            if ((int) $categoryid <= 0) {
                continue;
            }
            if ($i === $lastfilled) {
                // Final stage: no target required, it's open-ended practice.
                continue;
            }
            if (($onachieve[$i] ?? 'nextstage') === 'stay') {
                // The target field is hidden and ignored for a stage that never advances.
                continue;
            }
            $target = $targets[$i] ?? '';
            if ($target === '' || (int) $target < 1 || (int) $target > 100) {
                $errors["pathstagetarget[$i]"] = get_string('pathstagetargetrequired', 'qpractice', $stageno);
            }
            if ((int) ($minquestions[$i] ?? 0) < 0) {
                $errors["pathstageminquestions[$i]"] = get_string('pathstageminquestionsinvalid', 'qpractice', $stageno);
            }
        }

        return $errors;
    }
}
