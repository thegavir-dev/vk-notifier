# Changelog

## [1.0.1] — 2026-05-25

### Fixed
- Preserved line breaks from common HTML block tags when forwarding email text to VK.

## [1.0.0] — 2026-05-13

### Added
- Initial public release
- Intercepts outgoing WordPress emails via `wp_mail` filter
- Sends messages to VK users and community chats via VK API (`messages.send`)
- Two forwarding modes: all emails or whitelist-only
- Configurable message prefix and attachment notice text
- Built-in conversation finder to get correct `peer_id` values
- One-click test message sending from settings page
- Event log with filtering by level and event type
- Automatic log rotation with configurable retention period (default: 30 days)
- Instant and WP-Cron sending modes
- Full uninstall cleanup (table, options, cron events)
