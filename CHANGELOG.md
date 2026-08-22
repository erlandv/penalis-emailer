# Changelog

All notable changes to this project will be documented in this file.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [2.3.3] (current)

### Fixed
- Fixed a bug where clearing Automatic Email History via "Clear All Automatic History" did not also clear the `_penalis_email_sent` post meta — after a clear, `has_email_been_sent()` would permanently return `true` for all previously published posts, silently blocking both email delivery and history recording for any subsequent publish events on those posts

---

## [2.3.2]

### Fixed
- Fixed a bug in `Penalis_Email_Log_DB_Repository::cleanup()` where entries sharing the same `sent_at` timestamp as the cutoff row could be incorrectly deleted — replaced `DELETE WHERE sent_at <= cutoff` with an explicit `DELETE WHERE id NOT IN (keep_ids)` strategy using primary keys, with `id DESC` as a tiebreaker for deterministic ordering
- Implemented automatic log pruning: `cleanup_old_logs()` is now called after every log entry is saved (both automatic and manual), keeping storage bounded at `LOG_CLEANUP_KEEP_COUNT` (100) entries — this makes the UI label "Older entries are automatically archived" accurate
- `LOG_CLEANUP_KEEP_COUNT` constant (value: `100`) in `Penalis_Config` was previously declared but never used anywhere; it is now actively consumed by the auto-cleanup calls above

---

## [2.3.1]


### Fixed
- Applied `wp_unslash()` before `wp_kses_post()` on all `$_POST['body']` reads in AJAX handlers (`preview_email`, `preview_auto_email`, `send_test_email`, `autosave_draft`) — prevents WordPress magic quotes from inserting unwanted backslashes around quotation marks in email body output
- Applied `wp_unslash()` before `wp_kses_post()` on `$_POST['email_body']` in the settings page `handle_save()` — same fix for the auto-email template editor
- Applied `wp_unslash()` inside `sanitize_inputs()` in the compose page — same fix for manual email body submitted via the compose form; this affected actual sent emails, not only previews
- Replaced bare string interpolation in `SHOW TABLES LIKE` query with `$wpdb->prepare()` + `$wpdb->esc_like()` in `Penalis_Database::tables_exist()` — follows WordPress DB coding standards

---

## [2.3.0]

### Added
- CC (Carbon Copy) support on Manual Email compose page
- Admins can select one or more administrator/editor users as CC recipients when sending an email to a single contributor
- CC field renders as a tag-pill combobox — searchable by name or email, with removable tags for each selected user
- CC field is automatically disabled with an informational notice when more than 1 recipient is selected (CC is intentionally single-recipient only)
- CC email addresses are stored in `wp_penalis_email_queue`, `wp_penalis_email_log`, and `wp_penalis_email_draft` tables
- CC data persists across draft save/load and is included in the autosave payload
- CC recipients are displayed in the Manual Emails tab of Email History
- New AJAX endpoint `penalis_get_cc_users` to fetch administrator and editor users for the CC selector
- New constant `Penalis_Config::CC_ELIGIBLE_ROLES` defines which roles are eligible as CC targets

### Changed
- Database schema bumped to `2.0.3` — adds `cc_emails TEXT` column to queue, log, and draft tables (non-destructive migration via `dbDelta`)

---

## [2.2.0]

### Added
- Editor role can now access the plugin with limited permissions — all menu items are visible, but only the Email History page is accessible
- Email History for editors is read-only and shows only the Automatic Emails tab; attempting to access the Manual tab via URL falls back to Automatic
- Admin-only pages (Dashboard, Compose, Drafts, Template Settings, Queue Monitor) show a clear "Access Denied" notice for editors instead of a blank page
- New "Uninstall Settings" section in Template Settings to control whether plugin data is deleted on uninstall
- Default uninstall behavior is now **preserve data** — data is only deleted if explicitly opted in; a warning banner is shown when deletion is enabled

### Changed
- Email History now defaults to the Automatic Emails tab (previously Manual Emails)
- Automatic Emails tab is now positioned first (left), Manual Emails second

---

## [2.1.0]

### Added
- Dashboard now shows Active Jobs and Recently Completed Jobs queue widgets
- Placeholder & Formatting Guide card added to the Compose Email page

### Fixed
- "Sent By" column in Email History always showing "Unknown" for emails sent via the async queue

---

## [2.0.0]

### Added
- Background email queue system — manual emails are now sent asynchronously via WP-Cron
- Queue Monitor admin page with job progress, settings, cancel job, and cron health status
- Real-time progress banner after queuing emails, with live sent/total counter
- Email draft management — save, load, edit, auto-save, and send drafts
- Draft management page with bulk delete and team transparency (created by, last edited by, sent by)
- Custom database tables for email logs, queue, and drafts (migrated from `wp_options`)
- Infinite scroll and AJAX search for the recipients list on the Compose page
- Automatic retry with exponential backoff for failed queue items (up to 3 attempts)
- Queue settings configurable from the admin UI (batch size, throttle delay, interval, max retries)

### Changed
- Compose page redesigned with a 2-column layout (content left, recipients sidebar right)
- Template Settings page redesigned with card layout and 3-column guide grid
- Dashboard now includes a Recent Drafts widget and Manage Drafts quick action

### Fixed
- Drafts no longer appear in Email History or manual email logs
- `created_by` field now always populated correctly when saving a new draft

### Removed
- Email log and draft storage from `wp_options` (replaced by custom tables)

---

## [1.3.3]

### Added
- Dedicated "Email" column in Manual Emails history tab showing recipient email addresses
- Clickable author profile links on recipient names in Email History
- Preheader text support for email previews

### Fixed
- Tooltip positioning on recipient name overflow
- Selection counting accuracy in bulk operations

### Removed
- Deprecated `render_with_template()` and `render_without_template()` methods

---

## [1.3.2]

### Added
- "Select All Users" button to select all eligible users across all pages via AJAX
- Role-based bulk selection ("Authors Only", "Contributors Only") now selects across all pages

### Fixed
- "Sent By" counter showing numbers higher than total users (string/integer type mismatch)
- Role selection showing incorrect counts
- Compose Email menu not highlighted as active in the sidebar

---

## [1.3.1]

### Fixed
- Compose Email menu not showing as active when on the compose page
- Pagination links not maintaining correct page context

---

## [1.3.0]

### Added
- Dashboard page as the default landing page with email statistics, quick actions, and recent activity feed
- "Recipient Names" column in Manual Emails history tab with tooltip for overflow

### Changed
- Full design system overhaul aligned with WordPress admin UI (Dashicons, color palette, spacing)
- Replaced 26 manual `require_once` calls with an intelligent pattern-based autoloader

### Fixed
- Timezone display in Email History now respects WordPress timezone settings

---

## [1.2.0]

### Added
- Bulk delete and "Clear All History" per tab in Email History
- Tab-based navigation in Email History (Manual / Automatic)

### Changed
- Email History moved to a dedicated admin page with its own menu item

---

## [1.1.0]

### Changed
- Full architecture refactor: service container, repository pattern, validation system, custom exceptions, interfaces

---

## [1.0.0]

### Added
- Initial release: automatic post publication notifications, manual email interface, HTML email templates
