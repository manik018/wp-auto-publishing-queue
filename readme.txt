=== WP Auto Publishing Queue ===
Contributors: manik018
Author: Md. Fakharuddin (Manik)
Author URI: https://bloggingshout.com
Tags: publishing, queue, drafts, wp-cron
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically publish queued draft posts on a controlled daily schedule using WordPress Cron.

== Description ==

WP Auto Publishing Queue lets administrators add standard draft posts to a publishing queue and publish them automatically during a configured daily time frame.

The plugin includes:

* Enable or disable automatic publishing.
* A configurable publishing start and end time.
* Posts-per-day and minimum-gap controls.
* Randomized publishing times inside the allowed window.
* A Publishing Queue admin page.
* Bulk add-to-queue support from the Posts list.
* Add and remove controls in the post editor and Posts list row actions.
* WP-Cron based publishing.
* WordPress timezone support.
* Publishing logs.
* Queue, failed, scheduled-today, published-today, and next-publish counts.
* Retry handling for failed publishing attempts.
* Admin email notifications for permanent failures, empty queue, and low queue.
* Overview, Queue, Logs, and Settings admin pages.
* A WordPress dashboard widget.

Only standard WordPress posts with draft status can be queued. Pages, custom post types, and non-draft posts are not managed by this version.

== Installation ==

1. Upload the `wp-auto-publishing-queue` folder to `/wp-content/plugins/`.
2. Activate WP Auto Publishing Queue from the WordPress Plugins screen.
3. Open Auto Publisher > Settings.
4. Configure the publishing window, posts per day, minimum gap, retry settings, and low queue threshold.
5. Enable automatic publishing when ready.

== Basic Setup ==

After activation, the plugin creates its queue and log tables and stores default settings without overwriting existing settings on reactivation.

To start using it:

1. Create or edit standard draft posts.
2. Add drafts to the queue from the post editor, Posts list row actions, bulk actions, or the Publishing Queue page.
3. Use Auto Publisher > Publishing Queue to review queued, scheduled, retrying, and failed items.
4. Use Auto Publisher > Publishing Logs to review publishing, retry, cleanup, cron, and notification events.

== How The Publishing Queue Works ==

Queued posts are stored separately from WordPress posts. The plugin does not delete posts.

Active queue items are rows with one of these statuses:

* Queued
* Scheduled
* Retrying

Failed rows remain visible on the queue page but do not count as active queue items. When a queued post is manually published, trashed, deleted, or moved out of draft status, the plugin removes its queue row and normalizes active queue positions.

== Scheduling Behavior ==

The scheduler uses the WordPress site timezone. Each WordPress-local calendar day is limited by the Posts Per Day setting.

Daily capacity is calculated as:

* Successful plugin publish logs for that day
* Plus active scheduled or retrying queue rows for that day

This prevents the scheduler from replacing posts that were already successfully published earlier the same day and exceeding the configured daily target.

For today, new slots are never generated in the past. If the current time is inside the publishing window, the scheduler rounds up to the next full minute when needed.

The configured minimum gap applies to newly generated normal publishing slots. Retry times use the retry interval and do not need to preserve the random schedule gap.

Manual schedule regeneration affects only today's active scheduled and retrying rows. It does not erase historical publishing logs.

== Retry Behavior ==

Maximum Retry Attempts means retry attempts after the original publishing attempt.

For example, with Maximum Retry Attempts set to 3:

1. Original attempt
2. Retry 1
3. Retry 2
4. Retry 3
5. Permanent failure if Retry 3 fails

Later queue items continue processing even if one item fails.

== Notification Behavior ==

Notifications are sent to the WordPress administration email address.

The plugin can attempt notifications when:

* A post permanently fails after all retry attempts.
* The active queue becomes empty.
* The active queue drops below the configured low queue threshold.

Queue-empty and low-queue notifications are stateful so they are not sent on every cron run while the queue remains in the same state. If the admin email address is invalid or mail delivery fails, the event is logged.

== WordPress Cron Requirement ==

Automatic publishing uses WP-Cron.

WP-Cron runs when the WordPress site receives traffic unless the site has a real server cron configured. Low-traffic sites may experience delayed publishing unless server cron calls `wp-cron.php`.

The plugin registers a five-minute cron interval and processes up to 10 due queue items per run.

== WordPress Timezone Behavior ==

Scheduling, retry times, daily counts, and display formatting use the WordPress timezone configured in Settings > General.

Changing the WordPress timezone can affect how future schedule dates and dashboard counts are interpreted.

== Frequently Asked Questions ==

= Does the plugin publish pages or custom post types? =

No. This version supports standard WordPress posts only.

= Does the plugin publish posts immediately when I add them to the queue? =

No. Posts are published only when scheduled and due, and only after WP-Cron runs.

= Why was a post published later than its scheduled time? =

WP-Cron depends on site traffic unless a real server cron is configured. On low-traffic sites, due posts may publish late.

= Can I preserve plugin data on uninstall? =

Yes. By default, uninstall preserves the queue table, logs table, settings, and notification state options. Cron hooks and the processing transient are always cleared during uninstall.

= How do I delete plugin data on uninstall? =

Define `WPAPQ_DELETE_DATA_ON_UNINSTALL` as `true` before uninstalling. When enabled, uninstall removes the queue table, logs table, plugin settings, database version option, and notification state options. WordPress posts are never deleted.

== Troubleshooting ==

= Automatic publishing is not running. =

Confirm the plugin is enabled in Auto Publisher > Settings, verify WP-Cron is working, and check Auto Publisher > Publishing Logs for cron or publishing failures.

= No new slots are generated today. =

Check whether today's Posts Per Day capacity has already been used by successful publishes plus active scheduled or retrying rows. Also confirm the current time is still inside the configured publishing window.

= A post disappeared from the queue. =

Only draft posts can remain queued. If a queued post is manually published, trashed, deleted, or moved to another status, the plugin removes it from the queue.

== Changelog ==

= 1.0.0 =
* Production MVP release.

Stable tag:
= 1.0.1 =
   * Fix: minimum gap between posts is now enforced across separate schedule generation calls, not just within a single call.