# QPractice Plugin - Code Critique & Analysis

**Analysis Date**: 2025-10-27
**Plugin Version**: 1.4 (Build: 2025092899)
**Moodle Requirement**: 5.x
**Standards Applied**: Moodle 5.x Activity Module Development Guide

---

## Executive Summary

The QPractice plugin has a solid foundation with modern class-based architecture, proper event logging, and GDPR compliance. However, there are **3 critical bugs**, **2 high-priority issues**, and several code quality improvements needed before production deployment.

**Overall Assessment**: BETA-ready with critical fixes needed

---

## ✅ STRENGTHS

### 1. Good Foundational Architecture
- Proper separation of concerns: `lib.php` (Moodle hooks) vs `locallib.php` (business logic)
- Modern class-based approach with proper namespacing
- Event system implementation for activity logging
- Privacy API compliance (GDPR)

### 2. Capability Management
- Well-defined capability structure (`db/access.php`)
- Proper risk bitmasks (RISK_XSS, RISK_SPAM, RISK_PERSONAL)
- Appropriate context levels for each capability

### 3. Question Engine Integration
- Proper use of Moodle's question banking system
- Question behaviors properly configured
- Feature flag `FEATURE_USES_QUESTIONS` correctly declared

---

## 🚨 CRITICAL ISSUES (Fix Immediately)

### Issue #1: Event URL Generation Bug
**Severity**: 🔴 CRITICAL
**File**: `/var/www/mdl51/public/mod/qpractice/classes/event/qpractice_viewed.php:62`
**Type**: Logic Bug

#### Problem
```php
public function get_url(): \moodle_url {
    return new \moodle_url('/mod/qpractice/view.php', ['id' => $this->courseid]);
    //                                               ^^^^^^^^^^^^^^^^^^^^^^^^
    //                    WRONG: Should use contextinstanceid, not courseid
}
```

The URL is being constructed with `courseid` instead of the course module ID (`contextinstanceid`). This will break event logs and navigation.

#### Impact
- Users clicking on event logs won't navigate to the correct activity page
- Event logging becomes unreliable
- Course reports will show broken links

#### Fix
```php
public function get_url(): \moodle_url {
    return new \moodle_url('/mod/qpractice/view.php', ['id' => $this->contextinstanceid]);
}
```

---

### Issue #2: Incomplete Delete Implementation
**Severity**: 🔴 CRITICAL
**File**: `/var/www/mdl51/public/mod/qpractice/lib.php:145-155`
**Type**: Data Integrity Issue

#### Problem
```php
function qpractice_delete_instance($id) {
    global $DB;
    if (!$qpractice = $DB->get_record('qpractice', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('qpractice', ['id' => $qpractice->id]);

    return true;
    // MISSING:
    // - qpractice_session records (orphaned sessions)
    // - qpractice_categories records (orphaned category links)
    // - qpractice_session_cats records
    // - File cleanup from storage
    // - Question usage cleanup
}
```

The function only deletes the main record, leaving orphaned child records in the database.

#### Impact
- Database bloat from orphaned sessions
- Foreign key constraint failures on reinstallation
- Memory leaks from uncleaned question usages
- Cascading delete failures

#### Fix
According to Moodle Activity Module standards (lib.php best practices), delete children first:

```php
function qpractice_delete_instance($id) {
    global $DB;

    if (!$qpractice = $DB->get_record('qpractice', ['id' => $id])) {
        return false;
    }

    // Use transactions for data integrity
    $transaction = $DB->start_delegated_transaction();
    try {
        // Get all sessions for this qpractice instance
        $sessions = $DB->get_records('qpractice_session', ['qpracticeid' => $id], '', 'id');
        $sessionids = array_keys($sessions);

        // Delete in correct order (children first)
        if (!empty($sessionids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($sessionids);
            $DB->delete_records_select('qpractice_session_cats', "session $insql", $inparams);
        }

        $DB->delete_records('qpractice_session', ['qpracticeid' => $id]);
        $DB->delete_records('qpractice_categories', ['qpracticeid' => $id]);

        // Clean up files
        $cm = get_coursemodule_from_instance('qpractice', $id);
        if ($cm) {
            $context = context_module::instance($cm->id);
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'mod_qpractice');
        }

        // Finally delete main record
        $DB->delete_records('qpractice', ['id' => $id]);

        $transaction->allow_commit();
    } catch (Exception $e) {
        $transaction->rollback($e);
        return false;
    }

    return true;
}
```

---

### Issue #3: XMLDB Foreign Key Constraint Error
**Severity**: 🔴 CRITICAL
**File**: `/var/www/mdl51/public/mod/qpractice/db/install.xml:68`
**Type**: Schema Error

#### Problem
```xml
<TABLE NAME="qpractice_session_cats" COMMENT="Categories selected by user for a question attempt session">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="session" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="category" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <!-- ⚠️ ISSUE: Key name has typo (3 P's) -->
    <KEY NAME="qppractice_session_cat" TYPE="foreign"
         FIELDS="category" REFTABLE="qpractice_categories" REFFIELDS="id"/>
    <KEY NAME="qpractice_sessions" TYPE="foreign"
         FIELDS="session" REFTABLE="qpractice_session" REFFIELDS="id"/>
  </KEYS>
</TABLE>
```

The foreign key name `qppractice_session_cat` has a typo (three P's).

#### Impact
- Inconsistent database naming conventions
- Makes debugging and maintenance harder
- Could cause issues with some database management tools

#### Fix
```xml
<KEY NAME="qpractice_session_cat" TYPE="foreign"
     FIELDS="category" REFTABLE="qpractice_categories" REFFIELDS="id"/>
```

---

## ⚠️ MAJOR CONCERNS (High Priority)

### Issue #4: Missing Completion Tracking Support
**Severity**: 🟠 HIGH
**File**: `/var/www/mdl51/public/mod/qpractice/lib.php:38-51`
**Type**: Missing Feature

#### Problem
```php
function qpractice_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_USES_QUESTIONS:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        // MISSING: Completion tracking features
        default:
            return null;
    }
}
```

The plugin doesn't declare support for completion tracking, preventing teachers from setting up automatic completion rules.

#### Impact
- Teachers cannot set "Completion when student practices" rules
- Reduced integration with Moodle's completion system
- Less useful for course progress tracking

#### Fix
```php
function qpractice_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_USES_QUESTIONS:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        default:
            return null;
    }
}

// Then implement get_completion_state() function in lib.php:
function qpractice_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    $qpractice = $DB->get_record('qpractice', ['id' => $cm->instance], '*', MUST_EXIST);

    // Check if student has at least one completed session
    if ($type == COMPLETION_AND) {
        $session = $DB->get_record('qpractice_session', [
            'qpracticeid' => $qpractice->id,
            'userid' => $userid,
            'status' => 'completed'
        ]);
        return !empty($session);
    }

    return $type == COMPLETION_OR;
}
```

---

### Issue #5: Insufficient Input Validation
**Severity**: 🟠 HIGH
**File**: `/var/www/mdl51/public/mod/qpractice/lib.php:65-83`
**Type**: Security

#### Problem
```php
function qpractice_add_instance(stdClass $qpractice, ?mod_qpractice_mod_form $mform = null) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/questionlib.php');

    $qpractice->timecreated = time();
    $behaviour = $qpractice->behaviour;
    $comma = implode(",", array_keys($behaviour));
    $qpractice->behaviour = $comma;
    // ⚠️ ISSUE: No validation that $behaviour is an array or contains valid values
```

The behaviour handling doesn't validate:
- That `$behaviour` is actually an array
- That the values are valid question behaviors
- Input sanitization before database insertion

#### Impact
- Potential for invalid data in database
- Could crash if malformed data is provided
- Security risk if untrusted data reaches this function

#### Fix
```php
function qpractice_add_instance(stdClass $qpractice, ?mod_qpractice_mod_form $mform = null) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/questionlib.php');

    $qpractice->timecreated = time();
    $qpractice->timemodified = $qpractice->timecreated;

    // Validate behaviour input
    if (!is_array($qpractice->behaviour)) {
        throw new coding_exception('behaviour must be an array');
    }

    // Only keep valid behaviours
    $valid_behaviours = array_keys(question_engine::get_behaviour_options());
    $selected_behaviours = array_intersect(
        array_keys($qpractice->behaviour),
        $valid_behaviours
    );

    if (empty($selected_behaviours)) {
        throw new moodle_exception('nobehaviours', 'mod_qpractice');
    }

    $qpractice->behaviour = implode(',', $selected_behaviours);

    // Handle categories with validation
    $categories = optional_param_array('categories', [], PARAM_INT);
    if (empty($categories)) {
        throw new moodle_exception('nocategories', 'mod_qpractice');
    }
    $qpractice->categories = $categories;

    $qpractice->id = $DB->insert_record('qpractice', $qpractice);

    upsert_categories($qpractice);
    qpractice_after_add_or_update($qpractice);

    return $qpractice->id;
}
```

---

## 📋 CODE QUALITY ISSUES (Medium Priority)

### Issue #6: Missing Intro Field Processing
**Severity**: 🟡 MEDIUM
**File**: `/var/www/mdl51/public/mod/qpractice/lib.php:65-83`
**Type**: Feature Incomplete

#### Problem
```php
function qpractice_add_instance(stdClass $qpractice, ?mod_qpractice_mod_form $mform = null) {
    // ...
    $qpractice->id = $DB->insert_record('qpractice', $qpractice);
    // ❌ MISSING: No intro field processing with file_postupdate_standard_editor
```

The intro editor field won't work correctly because file uploads aren't being processed.

#### Impact
- Teachers can't embed files/images in activity introduction
- Intro content won't display with correct formatting
- Files uploaded will be orphaned

#### Fix
According to Moodle standards, add file handling:

```php
function qpractice_add_instance(stdClass $qpractice, ?mod_qpractice_mod_form $mform = null) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/questionlib.php');

    $qpractice->timecreated = time();
    $qpractice->timemodified = $qpractice->timecreated;

    // Handle intro field processing BEFORE database insert
    if ($mform && isset($qpractice->intro_editor)) {
        $qpractice = file_postupdate_standard_editor(
            $qpractice, 'intro',
            qpractice_get_editor_options(),
            $mform->get_context(),
            'mod_qpractice', 'intro', 0
        );
    }

    // Ensure defaults for intro fields
    if (!isset($qpractice->intro) || $qpractice->intro === null) {
        $qpractice->intro = '';
    }
    if (!isset($qpractice->introformat) || $qpractice->introformat === null) {
        $qpractice->introformat = FORMAT_HTML;
    }

    // ... rest of code ...
    $qpractice->id = $DB->insert_record('qpractice', $qpractice);
    // ... rest of code ...
}

// Add helper function for editor options
function qpractice_get_editor_options() {
    return [
        'subdirs' => 1,
        'maxbytes' => 0,
        'maxfiles' => -1,
        'changeformat' => 1,
        'noclean' => 1,
        'trusttext' => 0
    ];
}
```

Also update `qpractice_update_instance()` similarly.

---

### Issue #7: Localization Issue - Hardcoded String
**Severity**: 🟡 MEDIUM
**File**: `/var/www/mdl51/public/mod/qpractice/mod_form.php:80`
**Type**: i18n

#### Problem
```php
$mform->addElement('button', 'select_all_none', 'Select All/None');
// ❌ Hardcoded English string, not internationalized
```

#### Impact
- Plugin won't be properly translated
- Non-English users see English button text
- Violates Moodle localization standards

#### Fix
```php
$mform->addElement('button', 'select_all_none', get_string('selectallnone', 'qpractice'));
```

Add to `lang/en/qpractice.php`:
```php
$string['selectallnone'] = 'Select All/None';
```

---

### Issue #8: Deprecated Parameter Type
**Severity**: 🟡 MEDIUM
**File**: `/var/www/mdl51/public/mod/qpractice/mod_form.php:62`
**Type**: Standards

#### Problem
```php
if (!empty($CFG->formatstringstriptags)) {
    $mform->setType('name', PARAM_TEXT);
} else {
    $mform->setType('name', PARAM_CLEAN);  // ❌ PARAM_CLEAN is deprecated
}
```

`PARAM_CLEAN` was deprecated in Moodle and should not be used.

#### Fix
```php
$mform->setType('name', PARAM_TEXT);
```

---

### Issue #9: Duplicate Config Require
**Severity**: 🟡 MEDIUM
**File**: `/var/www/mdl51/public/mod/qpractice/view.php:25-28`
**Type**: Code Quality

#### Problem
```php
require_once('../../config.php');

global $CFG, $USER;
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
// ⚠️ config.php is being required TWICE with different paths
```

#### Impact
- Redundant code execution
- Could cause issues in edge cases
- Violates DRY principle

#### Fix
Remove the first require_once:
```php
global $CFG, $USER;
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once("$CFG->libdir/formslib.php");
```

---

## 🗄️ DATABASE SCHEMA ISSUES

### Issue #10: Missing Performance Indexes
**Severity**: 🟡 MEDIUM
**File**: `/var/www/mdl51/public/mod/qpractice/db/install.xml:26-48`
**Type**: Performance

#### Problem
```xml
<TABLE NAME="qpractice_session">
  <FIELDS>
    <!-- ... -->
  </FIELDS>
  <KEYS>
    <!-- Foreign keys only, no performance indexes -->
  </KEYS>
  <!-- NO INDEXES for common query patterns -->
</TABLE>
```

Common queries will do full table scans:
- `get_records('qpractice_session', ['userid' => $uid, 'qpracticeid' => $qid])`
- `get_records('qpractice_session', ['practicedate' => $date])`
- `get_records('qpractice_session', ['status' => 'completed'])`

#### Impact
- Slow queries with large datasets
- Database server load increases
- Poor user experience on reports

#### Fix
```xml
<TABLE NAME="qpractice_session" COMMENT="Practice session records">
  <!-- ... fields ... -->
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="userid" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
    <KEY NAME="questionusageid" TYPE="foreign" FIELDS="questionusageid"
         REFTABLE="question_usages" REFFIELDS="id"/>
    <KEY NAME="qpracticeid" TYPE="foreign" FIELDS="qpracticeid"
         REFTABLE="qpractice" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="userid_qpracticeid" UNIQUE="false" FIELDS="userid, qpracticeid"/>
    <INDEX NAME="practicedate" UNIQUE="false" FIELDS="practicedate"/>
    <INDEX NAME="status" UNIQUE="false" FIELDS="status"/>
  </INDEXES>
</TABLE>
```

---

### Issue #11: Placeholder Table Comments
**Severity**: 🟣 LOW
**File**: `/var/www/mdl51/public/mod/qpractice/db/install.xml:7`
**Type**: Documentation

#### Problem
```xml
<TABLE NAME="qpractice" COMMENT="Default comment for qpractice, please edit me">
<!-- ^^^^^^^^^^^^^^^^^^^^^^^^^^^ Placeholder comment not replaced -->
```

#### Fix
```xml
<TABLE NAME="qpractice" COMMENT="Main qpractice activity instances">
```

---

## 📝 MINOR ISSUES (Low Priority)

### Issue #12: Dead Code
**Severity**: 🟣 LOW
**File**: `/var/www/mdl51/public/mod/qpractice/locallib.php:29-44`
**Type**: Maintenance

#### Problem
```php
/**
 * Consider for deletion.
 * This doesn't seem to be used
 *
 * @param \context $context
 * @return void
 */
function qpractice_make_default_categories($context) {
    // ...
}
```

Function explicitly marked as unused but still in codebase.

#### Recommendation
Either remove or update with proper implementation.

---

### Issue #13: Incomplete User Outline
**Severity**: 🟣 LOW
**File**: `/var/www/mdl51/public/mod/qpractice/lib.php:187-192`
**Type**: Feature

#### Problem
```php
function qpractice_user_outline(int $course, int $user, int $mod, int $qpractice) {
    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}
```

Returns empty activity information. Should show last practice session details.

#### Recommendation
Implement to show user activity summary:
```php
function qpractice_user_outline(int $course, int $user, int $mod, int $qpractice) {
    global $DB;

    $session = $DB->get_record('qpractice_session', [
        'qpracticeid' => $qpractice,
        'userid' => $user
    ], '*', IGNORE_MULTIPLE);

    if (!$session) {
        return null;
    }

    $return = new stdClass();
    $return->time = $session->practicedate;
    $return->info = get_string('participated', 'qpractice');
    return $return;
}
```

---

## 🎯 SUMMARY & RECOMMENDATIONS

### Critical Fixes (Do Immediately)
1. ✅ Fix event URL in `qpractice_viewed.php:62` - 5 minutes
2. ✅ Complete `qpractice_delete_instance()` - 30 minutes
3. ✅ Fix XMLDB foreign key naming - 5 minutes

### High Priority (Do Soon)
4. ✅ Add completion tracking support - 20 minutes
5. ✅ Add behaviour validation - 15 minutes
6. ✅ Add intro field processing - 20 minutes

### Medium Priority (This Sprint)
7. ✅ Add database indexes - 10 minutes
8. ✅ Fix i18n string - 5 minutes
9. ✅ Replace PARAM_CLEAN - 5 minutes
10. ✅ Remove duplicate config require - 5 minutes

### Low Priority (Polish)
11. ⚠️ Remove or fix dead code - 10 minutes
12. ⚠️ Implement user outline - 15 minutes
13. ⚠️ Update table comments - 5 minutes

### Total Estimated Fix Time: **2-3 hours**

---

## 📋 Testing Checklist After Fixes

- [ ] Create new qpractice instance
- [ ] Edit qpractice instance with intro content including files
- [ ] Delete qpractice instance and verify all data removed
- [ ] Verify event logs show correct URLs
- [ ] Test all question behaviors
- [ ] Check activity completion rules work
- [ ] Run PHPUnit tests: `phpunit tests/lib_test.php`
- [ ] Run Behat tests: `behat tests/behat/add_qpractice.feature`
- [ ] Verify no orphaned records in qpractice_session
- [ ] Test with multiple categories
- [ ] Verify privacy API exports data correctly

---

## 📚 Relevant Standards References

- [Moodle Activity Module Guide](https://docs.moodle.org/dev/Activity_modules)
- [Moodle Coding Standards](https://moodledev.io/general/development/policies/codingstyle)
- [Moodle Data Model](https://docs.moodle.org/dev/Data_model)
- [Question Engine](https://docs.moodle.org/dev/Question_engine)
- [Completion API](https://docs.moodle.org/dev/Completion_API)

---

## Generated By

Claude Code - Moodle Plugin Development Assistant
Applied standards from `.prompts/` configuration files
Analysis date: 2025-10-27
