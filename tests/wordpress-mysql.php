<?php
/**
 * Exercise the report against WordPress's real wpdb adapter and MySQL.
 *
 * Run with WP-CLI only on a disposable local WordPress installation.
 * Copyright 2026 Max Wellner / Nakaryu GmbH.
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || wp_parse_url(home_url(), PHP_URL_HOST) !== 'localhost') {
    fwrite(STDERR, "This test requires WP-CLI on an isolated local WordPress site.\n");
    exit(1);
}
if (!class_exists('Nakaryu\\AutoloadInspector\\Inspector')) {
    fwrite(STDERR, "Activate Nakaryu Autoload Inspector before running this test.\n");
    exit(1);
}

function nakaryu_autoload_expect($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}

global $wpdb;
$inspector = 'Nakaryu\\AutoloadInspector\\Inspector';
$states = array_values(wp_autoload_values_to_autoload());
sort($states);
nakaryu_autoload_expect($states === array('auto', 'auto-on', 'on', 'yes'), 'Unexpected WordPress autoload states.');

$prefix = 'nakaryu_inspector_ci_' . bin2hex(random_bytes(6)) . '_';
$large = str_repeat('x', 250000);
$fixtures = array(
    'yes' => array('é', 'yes'),
    'on' => array($large, 'on'),
    'auto_on' => array('abcd', 'auto-on'),
    'auto' => array('1234567', 'auto'),
    'off' => array('not included', 'off'),
    'auto_off' => array('also excluded', 'auto-off'),
);
$baseline = $inspector::collect($wpdb, false, 1);
$on_only = function ($values) { return array('on'); };
add_filter('wp_autoload_values_to_autoload', $on_only);
try {
    $baseline_on = $inspector::collect($wpdb, false, 1);
} finally {
    remove_filter('wp_autoload_values_to_autoload', $on_only);
}

$inserted = array();
try {
    foreach ($fixtures as $suffix => $fixture) {
        $name = $prefix . $suffix;
        nakaryu_autoload_expect(
            $wpdb->insert($wpdb->options, array('option_name' => $name, 'option_value' => $fixture[0], 'autoload' => $fixture[1]), array('%s', '%s', '%s')) === 1,
            'Could not insert a synthetic test option.'
        );
        $inserted[] = $name;
    }

    $report = $inspector::collect($wpdb, false, 1);
    nakaryu_autoload_expect($report['total_options'] === $baseline['total_options'] + 4, 'Autoloaded option count differs on MySQL.');
    nakaryu_autoload_expect($report['total_bytes'] === $baseline['total_bytes'] + strlen('é') + strlen($large) + 4 + 7, 'Stored byte total differs on MySQL.');
    nakaryu_autoload_expect(!$report['names_included'] && !array_key_exists('name', $report['largest'][0]), 'Default report exposed an option name.');

    $named = $inspector::collect($wpdb, true, 1);
    nakaryu_autoload_expect($named['largest'][0]['name'] === $prefix . 'on' && $named['largest'][0]['bytes'] === strlen($large), 'Opt-in top option differs.');

    add_filter('wp_autoload_values_to_autoload', $on_only);
    try {
        $filtered = $inspector::collect($wpdb, false, 1);
        nakaryu_autoload_expect($filtered['autoload_states'] === array('on'), 'Runtime autoload filter was ignored.');
        nakaryu_autoload_expect($filtered['total_options'] === $baseline_on['total_options'] + 1, 'Filtered option count differs.');
        nakaryu_autoload_expect($filtered['total_bytes'] === $baseline_on['total_bytes'] + strlen($large), 'Filtered byte total differs.');
    } finally {
        remove_filter('wp_autoload_values_to_autoload', $on_only);
    }
} finally {
    // Keep even a failed test from leaving synthetic options behind.
    foreach ($inserted as $name) {
        $wpdb->delete($wpdb->options, array('option_name' => $name), array('%s'));
    }
}

echo "PASS: WordPress/MySQL autoload report matches six synthetic options.\n";
