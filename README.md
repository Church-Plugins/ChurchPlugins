# ChurchPlugins Framework

Shared framework and utility library for Church Plugins products (CP Sync, CP Groups, CP
Library, CP Staff, and others). Provides plugin setup base classes, licensing + updates,
logging, REST helpers, CMB2 integration, background processing, templates, and icons.

**Contributors:**      [tannerm](https://github.com/tannerm), [jjroley](https://github.com/jjroley)
**Stable tag:**        1.1.15
**License:**           GPLv2 or later
**License URI:**       [http://www.gnu.org/licenses/gpl-2.0.html](http://www.gnu.org/licenses/gpl-2.0.html)

## How it loads (version arbitration)

Every Church Plugins product bundles its own copy of this framework (usually as a git
submodule at `includes/ChurchPlugins`). At runtime **only one copy loads: the highest
version present on the site** — each copy registers a versioned loader class
(`ChurchPlugins_X_Y_Z`) on a hook priority that decrements with each release, so the
newest bundled copy wins.

Practical consequences:

- A fix shipped in one plugin's bundled copy is **dormant** on any site where another
  active plugin bundles a newer version. Framework fixes only reliably reach users when
  they land on `master`, get a **version bump**, and roll out through plugin releases.
- When debugging framework behavior on a site, first determine *which plugin's copy won*
  (compare `const VERSION` across `*/includes/ChurchPlugins/init.php`).

## Development workflow (submodule rule)

1. Land changes in **this repo** first (branch → merge to `master`).
2. Bump `const VERSION` and the loader class name in `init.php` (and the return type hint
   in `functions.php`) so the new copy wins arbitration; add a changelog entry below.
3. Consuming plugins update their submodule gitlink to the **merged master SHA** — never
   to a feature branch that may be deleted.

## Changelog

### 1.1.18
* Fix: `Models\Table::insert()`/`update()` retry a failed write without characters the
  column cannot physically store. Our tables are created without an explicit charset, so
  on many existing installs they are 3-byte `utf8` while core's are `utf8mb4`. `$wpdb`
  does not truncate in that case — since WP 4.2 `process_fields()` compares the value
  against a stripped copy and refuses the write — so any row with an emoji in a text
  column silently failed to save (one site was losing 152 of 502 sermons on every sync).
  The retry runs only after a failure, so installs whose tables are already `utf8mb4`
  never reach it and nothing they store is altered. Fires `cp_table_text_stripped` when
  characters are dropped.

### 1.1.17
* Update lazy Table insert to include the title

### 1.1.16
* New: shared "Church Plugins" top-level admin menu (`Admin\Menu`). Products opt in with
  `Menu::add_support()` (before `admin_menu` fires) and attach their screens as submenus
  under `Menu::get_slug()`; the parent menu only registers when at least one product has
  declared support. The self-referential parent submenu item is removed after products
  attach (priority 999) so the top-level menu lands on the first product's screen.
* New: `assets/icons/church-plugins.svg` menu icon, inlined as a base64 data URI and
  pre-colored to the admin color scheme's base icon color to avoid the black flash
  before svg-painter repaints it. Capability and position are filterable via
  `cp_admin_menu_capability` and `cp_admin_menu_position`.

### 1.1.15
* License (REST): activation and deactivation endpoints now call the license workers
  directly — previously they invoked the admin form handlers, which returned early
  without the form nonce/button fields, making REST license actions silent no-ops.
* License (REST): activation persists the submitted key before contacting the license
  server, so the daily check, deactivation, and the updater can read it.
* License: deactivation now clears the local license status even when the license server
  reports the activation was already gone ("failed") — a lingering local `valid` status
  locked the UI in a deactivate loop that could never succeed. The license-check
  transient is cleared at the same point.
* License (REST): deactivation responses report honestly when the server said the
  activation was already inactive rather than always claiming a fresh deactivation.

### 1.1.10 – 1.1.14
* Add local copy of `wp_background_process` (background processing without an external
  dependency)
* Add support for license key constants (`{ID}_LICENSE` / `CHURCHPLUGINS_LICENSE`)
* Fix encoding issues in CSV batch imports
* Update action order and include Migrations
* Template support + pagination updates; make sure template exists before including it
* Add `cp-template` filter; only run shortcode once
* Update pagination styling and add button sass templates
* Add new icons; strict handling for Material Icons
* Update logging
* Remove CMB2 readme

### 1.1.9
* Updates to Logging

### 1.1.8
* Add `virtual` icon

### 1.1.4 - 09/25/2024
* Style updates for template (add box-sizing)

### 1.1.2 - 09/13/2024
* Add action to delete log file

### 1.1.1 - 09/03/2024
* Add a base class for a plugin initialization class to give core access to plugin-contextual information

### 1.1.0 - 06/09/2024
* Add a base class for logging information

### 1.0.22 - 2/28/2024
* Add support for FSE templates
* Add new CMB2 field for specifying social links.

### 1.0.21 - 2/6/2024
* Change default table prefix to use  prefix instead of base_prefix

### 1.0.20 - 1/29/2024
* Protect against XSS when retrieving $_GET, $_POST, and $_REQUEST data.

### 1.0.19 - 11/30/2023
* Add force option to update_install

### 1.0.18 - 11/17/2023
* Add Helper function to output pagination with smooth scrolling disabled

### 1.0.16 - 9/28/2023
* Fix deprecation error
* Register styles and scripts for plugins
* Updater now supports beta updates

### 1.0.15 - 8/09/2023
* Fix issues with importer

### 1.0.13 - 5/23/2023
* Add import method to break on error
* Always unlink the temp file after processing on import
* Add conditional logic script to CMB2

### 1.0.12 - 5/22/2023
* Fix metabox issues
* Add sideload functionality to importer

### 1.0.9 - 4/27/2023
* Add icons
