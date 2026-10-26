# Validation scope

## Public version 0.1.1

The [WordPress.org listing](https://wordpress.org/plugins/nakaryu-autoload-inspector/) currently serves version 0.1.1. Its downloaded ZIP was compared file by file with the locally built 0.1.1 package on 2026-10-26; all ten allowlisted plugin files matched. GitHub has not published a corresponding 0.1.1 release tag yet.

Before WordPress.org publication, the installable ZIP passed official Plugin Check 2.1.0 in new-submission mode with the full default check set, runtime checks and low-severity findings included. Activation and a basic WP-CLI report without option names passed in a disposable WordPress 7.1.3 / PHP 8.5.5 installation with SQLite Database Integration 3.0.2 and synthetic data. These are point checks, not a compatibility matrix.

## Source checks added after 0.1.1 publication

`php tests/run.php` uses an isolated in-memory SQLite options table and a small WordPress database adapter. It verifies current-site table selection, the runtime's filtered autoload states, UTF-8 byte counts, size ordering and limits, default omission and explicit inclusion of option names, empty-state behavior, invalid limits and database-error handling. It checks that the report queries are SELECT-only and that error suppression is restored. The test runs in CI on PHP 7.4, 8.1, 8.3 and 8.5 alongside syntax and deterministic packaging checks.

SQLite is not MySQL or MariaDB, and this adapter does not prove WordPress's query preparation, admin capability/nonce handling, rendering, WP-CLI behavior, multisite operation or production performance. Two live queries are not a snapshot. Minimum WordPress/PHP versions are implementation targets; the full matrix remains unverified. This source update has not been released as a new plugin version.
