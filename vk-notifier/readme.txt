=== VK Notifier ===
Contributors: studioavp
Tags: vk, vkontakte, notifications, email, wp_mail
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Intercepts outgoing WordPress email notifications and forwards them to VKontakte — as direct messages or community chat messages.

== Description ==

VK Notifier intercepts outgoing WordPress emails via the standard `wp_mail` filter and sends their content to VKontakte using the official VK API.

**Features:**

* Intercepts all outgoing WordPress emails via the `wp_mail` filter
* Sends messages to VK users as direct messages
* Sends messages to VK community chats (by peer_id)
* Two forwarding modes: all emails, or whitelist-only
* Configurable message prefix and attachment notice text
* Built-in conversation finder tool to get correct peer_id values
* One-click test message sending
* Event log with filtering by level and event type
* Automatic log rotation with configurable retention period
* Sending mode: instant or via WP-Cron

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin via the WordPress Plugins menu
3. Go to Settings → VK Notifier
4. Enter your VK access token and recipient IDs
5. Click "Send test message" to verify the setup

**Getting a VK token:**

For a community token: Community Management → API Usage → Access Tokens.
Required permission: `messages`.

== Frequently Asked Questions ==

= Does the plugin support personal VK user tokens? =

Yes, both community tokens and personal user tokens are supported. For community tokens, you must also provide the community ID in the plugin settings.

= What is a chat peer_id? =

A peer_id is a numeric chat identifier in VK, in the format 2000000XXX. Use the "Find available community chats" button in the plugin settings to get the correct value for your token.

= Where can I find the sending logs? =

Go to Settings → VK Notifier Logs. You can filter by event level, event type, and search query.

= Can I disable logging for certain event types? =

Yes. The "Log level" setting lets you choose: all events, errors only, successful only, or informational only.

== Screenshots ==

1. Plugin settings page
2. Event log with filtering

== Changelog ==

= 1.0.0 =
* Initial public release.

== Upgrade Notice ==

= 1.0.0 =
Initial public release.
