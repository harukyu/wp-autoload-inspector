# WordPress Autoload Inspector

[![Syntax and packaging](https://github.com/harukyu/wp-autoload-inspector/actions/workflows/ci.yml/badge.svg)](https://github.com/harukyu/wp-autoload-inspector/actions/workflows/ci.yml)

A small, read-only WordPress plugin by **[Nakaryu GmbH](https://nakaryu.de)** for investigating the database size of autoloaded options. Admin screen and WP-CLI command. **Developer preview 0.1.1: Plugin Check and isolated activation/basic CLI checks passed; broader integration remains unverified.**

[Deutsche Anleitung](docs/DE.md) · [Downloads](https://github.com/harukyu/wp-autoload-inspector/releases) · [Validation scope](docs/VALIDATION.md)

## Why this exists

When a WordPress installation accumulates settings, developers need to see where the bytes are concentrated before deciding what to investigate. This tool reports a total and the largest contributors. It makes no automatic cleanup decision.

## Install and use

Build `nakaryu-autoload-inspector-0.1.1.zip` using `python3 tools/package.py`. Published release downloads are linked above. Upload it via **Plugins → Add New → Upload Plugin**, activate, then open **Tools → Autoload Inspector** and click **Inspect current site**. Use an isolated development installation for this preview.

Use the plugin ZIP, not the `-source.zip` archive or GitHub's automatic Source code ZIP. Designed for WordPress 6.0+ and PHP 7.4+; these are implementation targets, not a validated compatibility matrix. No Composer or external service account is needed.

```sh
wp nakaryu autoload inspect
wp nakaryu autoload inspect --top=10
# Only request names when you are allowed to view them:
wp nakaryu autoload inspect --top=20 --include-names
# Select one site in a multisite installation:
wp --url=https://example.test nakaryu autoload inspect
```

WP-CLI requires the plugin to be loaded and normal authorized shell access. The web screen requires `manage_options` and a valid nonce. There is no public REST endpoint, scheduled scan, stored report or settings page.

## What the report means

- `total_options`: count matching the current runtime's autoload states.
- `total_bytes`: summed `OCTET_LENGTH(option_value)` in the database.
- `largest`: top 1–100 options by stored byte size; the admin page shows 20.
- `share_percent`: each listed size as a share of the total, rounded to two decimals.
- `names_included`: false unless explicitly opted in. Ranks are not persistent option identifiers.

The plugin performs two SELECT queries. It does **not** fetch, unserialize, display or export option values. The default query does not select option names either. Names may contain private identifiers, so they are opt-in. JSON can be copied from the admin page or redirected from WP-CLI. No hostname, timestamp, database credentials or raw database error is added to the report.

## Autoload handling

On WordPress 6.6+, the tool uses `wp_autoload_values_to_autoload()` to follow the runtime's filtered states. Older supported versions use `yes`. An empty state list produces an empty report. The tool reads `$wpdb->options` for the **current site only**; it does not scan every multisite blog or network options.

This is a live database estimate. The two queries do not run in a snapshot transaction, so concurrent updates can make totals and top rows differ slightly. The query aggregates over the options table; a large table can make a manually requested scan expensive.

Stored bytes are **not** PHP memory usage, persistent-object-cache contents or a speed measurement. Filters and cache state can make actual runtime loading differ. Large options can be legitimate. Ownership cannot be inferred reliably from a name, and an option should not be deleted or have autoload changed based on this report alone.

## Privacy and scope

No telemetry, remote API calls, file writes, option updates/deletes, cache flushes or value deserialization are implemented. Normal WordPress bootstrap and other installed components may still write their own caches/logs. Treat the output, especially opted-in names, as potentially private.

This project is independently implemented. It contains no code or settings exported from Nakaryu's commercial plugins or customer installations. Nakaryu develops WordPress and WooCommerce solutions; this free developer tool demonstrates a focused maintenance workflow. No paid service is required.

## Development and validation

```sh
php -l nakaryu-autoload-inspector.php
php -l includes/Inspector.php
python3 tools/package.py
```

CI checks PHP syntax on 7.4, 8.1, 8.3 and 8.5 and builds deterministic plugin/source ZIPs with SHA-256 sidecars. These checks do not validate SQL results, admin permissions, hook behavior or actual activation. Version 0.1.1 additionally passed official Plugin Check and isolated activation/basic WP-CLI checks. Full functional QA remains incomplete. See [validation scope](docs/VALIDATION.md).

## References

- [WordPress autoload-state API](https://developer.wordpress.org/reference/functions/wp_autoload_values_to_autoload/)
- [WordPress alloptions loading implementation](https://developer.wordpress.org/reference/functions/wp_load_alloptions/)

## License

Copyright 2026 Nakaryu GmbH. GPL-2.0-or-later; see [LICENSE](LICENSE). WordPress is a trademark of the WordPress Foundation. This independent project is not an official WordPress product.
