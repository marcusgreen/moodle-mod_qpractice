### Moodle Question practice Next release

Lint fixes to styles.css

### Moodle Question practice 1.4 October 2025

This is the first update to the Moodle plugins database since 2016 and the functionality may have changed unexpectdly.

It is now has a setting to configure multiple question categories to be selected by a user making an attempt.

Updated to be compatible with Moodle v5 Migrated to use mustache and a Moodle 4.x style report

### Moodle Question Practice 1.3 Changes June 2019
- Added a top category of questions for instance setting. Thanks to Steve Gallagher and Scott Hallman for feedback on this.
- Codechecker and PHPDoc Check conformance

### Moodle Question Practice 1.2 Changes Nov 2016
 - Fixed parameters in question_extend_settings_navigation, essential to make menu work in MDL3.2
 - Removed display of empty question categories in the questions dropdown (startattempt.php)
 - Deleted functions to do with scales that were never implemented
 - Tweaks to conform with codechecker suggestions https://moodle.org/plugins/local_codechecker
 - Created 1 phpUnit test and 1 behat test, will create more of both in the future


### Moodle Question Practice 1.1 Changes Mar 2016
- Creation of new icon more inline with other Moodle activity icons
- Creation of event classes for logging instead of add_to_log
- Added FEATURE_USES_QUESTIONS as warning was thrown during backup
- Reformatted code based on results of code_checker plugin
- If a category is deleted from db jump out of loop when displaying previous sessions
- Only show past sessions link if past sessions exist
- Code to ensure jquery initialisation e.g in gapfill and ordering 3rd party questions
- Replaced deprecatied function call add_intro_editor with standard_intro_elements
- Replaced save_questionss_usage_by_activity with inline code to avoid exception message





