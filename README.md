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

## Shared admin menu (1.1.23)

Every product's screens live under one top-level **Church Plugins** menu
(slug `church-plugins`), whether or not the product calls anything:

    Home (CP Essentials) · Messages · Events (CP Essentials) · Groups · Staff ·
    Locations · Resources · Notification Bars · Church Info (CP Essentials) ·
    Sync · Settings · Licenses

- **How.** Products register their post types and pages as before. On
  `admin_menu` priority 998, `Admin\Menu` removes each product's top-level
  menu (from the product table, `Menu::products()`, plus any other top-level
  list of a post type registered through `Setup\PostTypes\PostType`) and adds
  one entry for it here. The old `$submenu` arrays stay, so every screen keeps
  its URL, hook suffix and capability check. The pure rules are in
  `Admin\MenuLayout`.
- **Tabs.** The items of a product's old submenu (minus "Add New" and the
  pages that moved to Settings/Licenses) become a tab bar on the list,
  add-new, edit (classic and block editor) and taxonomy screens, with the
  current tab marked `aria-current="page"` and the area's entry highlighted.
  Markup matches CP Essentials' tab bars. Filter: `cp_admin_area_tabs`.
- **Settings.** One entry; a tab per product on top of each product's
  existing settings pages (their own tabs stay below as sub-tabs). Pages
  keep their URLs, forms and save redirects. CP Live's page leaves
  WordPress's Settings menu (old `options-general.php?page=…` URLs forward to
  `admin.php?page=…`). Registry: `cp_admin_settings_tabs`
  (`key => { label, path, capability, pages[], position }`). When CP
  Essentials registers `cp-essentials-settings` under the shared menu, that
  page is the Settings entry and its first tab.
- **Licenses.** `Admin\Licenses` lists every `Setup\Admin\License` (each
  registers itself when constructed). A row shows the product's existing
  CMB2 license box posted to the same `admin-post.php` handler, or, without a
  box (CP Sync), a field using the existing license REST routes. Activation
  logic is unchanged. Old License tabs are taken out of the products' tabs
  and their URLs forward to Licenses with `sl_activation` / `message`. A hub
  can host the rows: return its page slug from `cp_admin_licenses_page`,
  register that page and call `Licenses::get_instance()->render_rows()`
  (it prints nothing to a user without `manage_options`). Each row's field
  ids are prefixed with its license id, so rows do not share ids; field names
  are unchanged.
- **Capability and position.** `edit_posts` and position 26 while the menu
  holds content screens (as CP Essentials does), else `manage_options` and 80
  (filters `cp_admin_menu_capability`, `cp_admin_menu_position`). An area or
  the Settings entry is added only for a user who can open it; with no entry
  left for a user (a Subscriber), the menu is not shown. The parent is
  registered at `admin_menu` priority 8 (9 up to 1.1.22), so a product that
  adds its screens at priority 9 finds it.
- **Notice.** `Admin\MenuNotice` records once per site (option
  `cp_admin_menu_layout`) whether the site was already in use (product
  content, saved product settings or a license key; never when CP Essentials
  is active at that moment, since it fills the same post types under this
  same menu). Only those sites show a
  one-time notice, to users who could open the moved screens and were
  registered before the move; dismissed per user per site
  (`cp_admin_menu_notice_dismissed`).
- **Opt-out.** `add_filter( 'cp_admin_shared_menu', '__return_false' )` keeps
  the old layout. Meant for one release cycle.

## Changelog

### 1.1.23
* Shared admin menu: every product's screens move under **Church Plugins**, with tab bars, one Settings screen, one Licenses screen and a one-time notice. See "Shared admin menu (1.1.23)" above.
* `Setup\Admin\License` registers itself with `Admin\Licenses` and gains `get_id()`, `get_plugin_file()` and `get_settings_url()`. Activation, deactivation, checks and updates are unchanged.
* `Setup\PostTypes\PostType::registered_post_types()` lists post types registered through the class.
* Integration suite (`phpunit-integration.xml.dist`) that boots a real site. `CP_FW_PRELOAD=1` loads the copy under test for the run without placing it inside a plugin on the site.
* Code that hides a product's menu with `remove_menu_page( 'edit.php?post_type=cpl_item' )` (or another product's old top-level slug) still works when it runs on `admin_menu` before priority 998: the product is then not moved either. Run later (priority 998 and up, `admin_init`, `admin_head`) it no longer hides anything, because that item is gone and the screens are entries of `church-plugins`. There, use `remove_submenu_page( 'church-plugins', 'edit.php?post_type=cpl_item' )`, or the `cp_admin_shared_menu` opt-out.

### 1.1.22
* Legacy template path (block template handoff off) on block themes: whether the
  plugin's view replaces the template is now decided from the block template core
  actually resolved (`Templates::resolved_block_template_overrides()`), not from a
  guessed slug. Fixes a sermon taxonomy page falling back to the theme's generic archive
  when `archive-cpl_item` was customized; customized `taxonomy-*` templates are now
  honored; a customization saved under another theme no longer counts. The framework's
  body classes (`cp-template`, `cp-page-template`, the theme class) are added when
  deferring, with whatever a plugin's own `template_body_class()` override adds. CP
  Sermons' `cpl-page-template` is **not** among them, on any path, now or before: its
  `Templates` class redeclares `protected static $template`, so its override reads a
  copy the framework never writes. It prints `cpl-template`; the bare `page-template-`
  class it used to print as well is now dropped on every path
  (`Templates::strip_empty_template_class()`).
* **Behavior change, legacy path only.** Two cases now keep the resolved template where
  1.1.21 replaced it with the plugin's view, matching what the handoff already does:
  (1) a theme that ships its own `single-{post_type}`, `archive-{post_type}` or
  `taxonomy-{taxonomy}` block template for a CP type, and (2) a post whose custom
  template, assigned in the editor, contains the `churchplugins/view` block. In case
  (1), if that template has no `churchplugins/view` block, the plugin's output no longer
  appears on the page. Add the "Church Plugins Content" block to the template, or opt
  out so the plugin's view replaces it again:
  `add_filter( 'cp_template_block_template_overrides', '__return_false' );`
  The filter runs whenever core resolved a block template for the request, which
  includes classic themes with a `theme.json` (assigned custom templates). It receives
  the decision, the resolved `WP_Block_Template` (null before WordPress 6.3, and on 6.3+
  when the recorded template cannot be loaded), the `Templates` instance and the
  template path, so it can be narrowed to one plugin or one template source.
* An assigned custom template without the view block does not defer: the plugin's view
  replaces it, as on 1.1.21, because the block can only be inserted in the Site Editor.
  Only the template's own content is checked; a view block inside a template part or a
  pattern the template references is not seen (place it in the template, or return true
  from the filter).
* A request a plugin handles only through `cp_template_is_query` (not one of its own
  post types or taxonomies) no longer defers to a customized template on the legacy path.
* WordPress 6.0 to 6.2 (no `$_wp_current_template_id`) keeps the 1.1.21 slug guess.
* A search scoped to a CP post type (`/groups/?s=foo`, `/?s=foo&post_type=cp_group`)
  is not handed to the block template hierarchy: core resolves the theme's search
  template there, which has no view block, so the legacy path renders the archive view.
* View block: offered only in the Site Editor (`cp_view_block_inserter`), titled
  "Church Plugins Content", lists only the plugins that can show something on the
  template being edited.
* Template titles and descriptions say which page a template controls; taxonomy titles
  name the post type. `Templates::get_plugin_name()` passes through the new
  `cp_template_plugin_name` filter (an override that skips `parent::` bypasses it).
* Tests: `tests/Unit/TemplatesTest.php` covers the deferral rules, the handoff decision,
  what `template_include()` returns and hooks on each path, the body classes printed
  (also for a subclass shaped like CP Sermons') and taxonomy template labels.

### 1.1.19
* Request action hardening: `Admin\_Init::request_actions()` applies stricter checks
  before it dispatches a `cp_action` request. Names on the `cp_request_actions`
  allowlist run for a logged-in user who has the required capability (default
  `manage_options`, filter `cp_request_action_capability`) and a valid core nonce.
  `request_action_nonce_field()` and `request_action_nonce_url()` build that nonce
  for a form or a link. `request_action_nonce_url()` returns an HTML-escaped URL,
  the same way `wp_nonce_url()` does. Names on neither the allowlist, the legacy
  list, nor the public list are ignored.
* Front-end forms that verify their own nonce can opt in through
  `cp_public_request_actions`. `cp_send_email` (CP Groups) and `cp_staff_send_email`
  (CP Staff) are registered by default so those email forms keep working. Callbacks on
  that list must verify their own nonce.
* `cp_legacy_request_actions` keeps the CP Library admin actions
  (`cp_export_items`, `cpl_adapter_import_sermon_audio`,
  `cpl_adapter_pull_sermon_audio`, `cpl_import_transcript`, `cp_upload_import_file`)
  available to a logged-in user with the required capability. A valid core nonce,
  or the `_wpnonce` CP Library 1.8.0 already sends (for the action name on export and
  the SermonAudio buttons, for `cpl_import_transcript_<post_id>` on transcript
  import), authorizes the action on any request method. Without either nonce the
  request must be POST and same-site. `Sec-Fetch-Site` decides when the browser sends
  it: only `same-origin` passes. It is still sent under `Referrer-Policy: no-referrer`,
  when Origin is `null` and there is no Referer. Without it, the Origin header, or the
  Referer when Origin is absent, must have the same scheme, host, and port as
  `home_url()` or `admin_url()`. `Origin: null`, a URL with user info or a backslash,
  a GET or HEAD, missing headers, or a different scheme, host, or port does not run. Registering one of those names on `cp_request_actions` opts
  it into the nonce check. `cp_upload_import_file` is the CSV upload used by older
  CP Library releases.
* `cpl_import_transcript` is checked as `edit_post` against the requested `post_id`, so
  an author can import a transcript for their own sermon and not for anyone else's. A
  request without a `post_id` does not run. CP Library releases before 1.8.0 do no
  check of their own, so this has to happen in the dispatcher. Other request actions
  still default to `manage_options`. `cp_request_action_capability` can override either
  default; for the transcript import the filtered capability is still checked against
  the sermon.
* A refused request for a registered name fires `cp_request_action_refused` with the
  action and a reason (`capability`, `nonce`, or `same_site`). A JSON request also gets
  a 403 `wp_send_json_error( array( 'error' => ... ) )` instead of an empty response.
  Unregistered names are still ignored silently.
* Tests: PHPUnit lives in `tests/composer.json`, apart from the root `composer.json`, so
  installing it never rewrites the production autoloader under `vendor/`. Run
  `composer install && composer test` from `tests/`. Do this in a standalone clone, not
  inside a plugin's `includes/ChurchPlugins` submodule: plugin zips copy `includes/**`,
  and `tests/vendor/` would ship with them.

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
* Perf: `Setup\Tables\Log` gains a composite `(action, created)` index, and the table
  version moves to `1.1` so existing installs pick it up through `maybe_update()`.
  Reporting reads this table as "rows with action X since date Y", which `idx_action`
  could not serve — `action` has a handful of distinct values and the common ones cover
  most of the table, so the range on `created` was resolved by scanning matched rows.
  Measured on a 9.5k-row table, `EXPLAIN` goes from `key=idx_action, rows=13,
  filtered=33%` to `key=idx_action_created, rows=1, Using index` — the new index covers
  those queries outright. The version is recorded only once the index is confirmed
  present: `update_install()` runs on an admin request, and on a large log table the
  `ALTER` can outlast it, which would otherwise mark the table current with no index on
  it and nothing would retry. The add is guarded by the same check, so a retry after a
  partial run is a no-op rather than a duplicate-key error.

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
