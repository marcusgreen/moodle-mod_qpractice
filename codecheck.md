Moodle Coding Standards Compliance Report — mod_qpractice

Summary
- Overall close, but not fully compliant. Several fixes required.

Key Strengths
- File headers present with GPL in core files (e.g., `version.php:1`, `lib.php:1`).
- Classes under `classes/` follow Moodle autoloading layout; events implemented.
- Language strings centralised in `lang/en/qpractice.php` and used via `get_string()`.
- Capabilities defined in `db/access.php` and checked with `has_capability()`.

Issues and Required Changes

1) version.php
- Problem: Spurious `<?php` appears after `$plugin->release`, indicating accidental duplication; invalid PHP.
- Problem: `$plugin->requires = 2013040500` targets Moodle 2.5 (EOL). Consider updating to a supported minimum.
- Problem: `$plugin->cron = 0` is obsolete for supported versions; use `db/tasks.php` if needed.
- Action:
  - Remove duplicate `<?php` and any trailing content beyond `$plugin->release`.
  - Set an appropriate `$plugin->requires` for the Moodle versions you support.
  - Remove `$plugin->cron` or replace with scheduled tasks (if applicable).

2) lib.php
- Problem: `optional_param_array('categories', '', PARAM_INT )` uses string default and stray space.
- Problem: Some functions lack complete PHPDoc; ensure param/return docs.
- Action:
  - Use `optional_param_array('categories', [], PARAM_INT)`.
  - Audit and complete PHPDocs with Moodle style.

3) renderer.php
- Problem: Renderer methods `summary_table()` and `summary_form()` echo output; renderers should generally return strings.
- Problem: `report_table()` uses two independent `if` blocks; users with both caps will have the second overwrite the first. Use `if/elseif`.
- Problem: Table `align`/`size` counts do not match headers (5 columns vs arrays sized 7–8).
- Problem: Redirect in renderer mixes concerns; consider handling redirects in page script, with renderer returning markup only.
- Action:
  - Refactor renderer methods to return strings and fix callers to echo returned value.
  - Change capability logic to `if/elseif` and fix column arrays to match header count.
  - Move redirect logic to controller/page script.

4) Privacy API
- Problem: No `classes/privacy/provider.php` found; plugin stores user data in sessions.
- Action:
  - Implement Privacy API provider to describe, export, and delete user data for tables like `qpractice_session` and related.

5) Entry scripts (index.php, view.php, attempt.php, report.php, summary.php, startattempt.php)
- Checkpoints:
  - Must call `require_login()`, set `$PAGE` URL/context/title/heading, and output `$OUTPUT->header()`/`$OUTPUT->footer()`.
  - Must validate parameters with proper `PARAM_*` types and enforce capabilities.
- Action:
  - Verify each script; align with standard module page pattern.

6) Events under classes/event
- Checkpoints:
  - Each event should define `init()`, `get_name()`, `get_description()`, and `get_url()` as appropriate and use `get_string()` for names.
- Action:
  - Review each event class for completeness and correct `objecttable` and CRUD/edulevel.

7) AMD module and builds
- Checkpoints:
  - `amd/src/qpractice.js` should follow Moodle AMD style (`define([...], function(...) { ... });`), no globals, pass eslint.
  - Built files in `amd/build/` must be generated, not hand-edited.
- Action:
  - Lint and rebuild if needed with `grunt`.

8) Templates
- Checkpoints:
  - `templates/view.mustache` must be logic-less; strings provided via context, no PHP in templates.
- Action:
  - Review and adjust context/template accordingly.

9) Strings and capabilities
- Checkpoints:
  - Every capability in `db/access.php` must have a corresponding lang string `capability:mod/qpractice:<capability>`.
- Action:
  - Ensure completeness and consistency.

10) Miscellaneous
- `tests/fixtures/category_quesitons.xml` filename typo (“quesitons”). Rename if used in docs/UX; tests can remain if referenced.
- `styles.css` should namespace selectors under module scope to avoid leaks.

Suggested Next Patches (minimal set)
- Fix `version.php` duplication; update `requires`; remove `cron`.
- In `lib.php`, change `optional_param_array('categories', [], PARAM_INT)` and tidy PHPDocs.
- In `renderer.php`, return strings, fix capability branching, and align column arrays.
- Add `classes/privacy/provider.php` implementing metadata and user data export/delete for sessions.

Notes
- This report is based on repository scan paths listed by ripgrep and sampling key files. A full pass should include automated phpcs against Moodle ruleset and running unit/Behat tests on supported Moodle branches.

