# WP Auto Publishing Queue

Automatically publish queued draft posts on a controlled daily schedule using WordPress Cron.

*Built by [Md. Fakharuddin (Manik)](https://bloggingshout.com)*

![License: GPLv2](https://img.shields.io/badge/license-GPLv2-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)

## What it does

WP Auto Publishing Queue lets you add standard draft posts to a queue and have them publish automatically, one by one, inside a daily time window you control — instead of publishing everything at once or relying on manual clicks.

- Enable/disable automatic publishing
- Configurable daily publishing window (start/end time)
- Posts-per-day limit and minimum gap between publishes
- Randomized publish times inside the allowed window (not fixed intervals)
- Add/remove posts from the Posts list (row actions + bulk action) or from inside the post editor
- WP-Cron based background processing
- Retry handling for failed publish attempts, with a configurable retry limit and interval
- Publishing Queue, Publishing Logs, and Settings admin screens
- Dashboard widget showing queue status at a glance
- Email notifications for permanent failures, an empty queue, or a low queue
- Respects your site's WordPress timezone setting
- Random posts-per-day mode (a min/max range instead of a fixed number)
- Block publishing on specific weekdays and/or specific dates, with posts already scheduled on a date that becomes blocked automatically rescheduled onto the next valid day
- Clear All Logs option on the Publishing Logs page

Only standard WordPress posts with `draft` status can be queued. Pages and custom post types are not supported in this version.

## Installation

1. Download the latest release zip from the [Releases](../../releases) page.
2. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**, and upload the zip.
   *(Or unzip it into `/wp-content/plugins/` via FTP/File Manager.)*
3. Activate **WP Auto Publishing Queue** from the Plugins screen.
4. Go to **Auto Publisher → Settings**, enable automatic publishing, and set your preferred daily window, posts-per-day limit, and minimum gap.
5. Add drafts to the queue from the Posts list (row action or bulk action) or from the post editor sidebar.
6. Open **Auto Publisher → Publishing Queue** and click **Generate Today's Schedule**.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- A working WP-Cron (default on most hosts). On low-traffic or heavily cached sites, WP-Cron only fires when the site gets a visit, so for precise timing set up a real server cron job that calls `wp-cron.php` every 5 minutes instead of relying on page-load-triggered cron.

## How scheduling works

Each day, the plugin picks random publish times inside your configured window (respecting the minimum gap between posts and your posts-per-day limit) for as many queued drafts as fit. A background cron job checks every few minutes for posts that are due and publishes them. If a publish attempt fails, the post is retried according to your retry settings before being marked as permanently failed (visible in Publishing Logs).

## Uninstalling

By default, uninstalling the plugin keeps your settings and logs in the database in case you reinstall it. If you want a full cleanup (drop all plugin tables and options) on uninstall, add this to your `wp-config.php` before uninstalling:

```php
define( 'WPAPQ_DELETE_DATA_ON_UNINSTALL', true );
```

## License

GPLv2 or later — see [LICENSE](LICENSE).

## Author

Developed by **Md. Fakharuddin (Manik)** — [bloggingshout.com](https://bloggingshout.com)

## Contributing

Issues and pull requests are welcome. Please open an issue describing the bug or feature before submitting a large PR.
