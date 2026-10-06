<?php
/**
 * Plugin Name: Nakaryu Autoload Inspector
 * Plugin URI: https://github.com/harukyu/wp-autoload-inspector
 * Description: Read-only database size report for autoloaded WordPress options. No option values are read or displayed.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Nakaryu GmbH
 * Author URI: https://nakaryu.de
 * License: GPL-2.0-or-later
 * Text Domain: wp-autoload-inspector
 */
namespace Nakaryu\AutoloadInspector;
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/includes/Inspector.php';

add_action('admin_menu', function () {
    add_management_page('Autoload Inspector', 'Autoload Inspector', 'manage_options', 'nakaryu-autoload-inspector', __NAMESPACE__ . '\\render_page');
});

function render_page() {
    if (!current_user_can('manage_options')) { wp_die(esc_html__('Insufficient permissions.', 'wp-autoload-inspector')); }
    echo '<div class="wrap"><h1>Autoload Inspector</h1>';
    echo '<p>' . esc_html__('Inspect database byte sizes of autoloaded options. Values are never selected. Option names stay hidden unless requested.', 'wp-autoload-inspector') . '</p>';
    echo '<form method="post">';
    wp_nonce_field('nakaryu_autoload_scan');
    echo '<p><label><input type="checkbox" name="include_names" value="1"> ' . esc_html__('Show option names (may contain private identifiers)', 'wp-autoload-inspector') . '</label></p>';
    submit_button(__('Inspect current site', 'wp-autoload-inspector'));
    echo '</form>';
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('nakaryu_autoload_scan');
        try {
            global $wpdb;
            $report = Inspector::collect($wpdb, !empty($_POST['include_names']));
            echo '<h2>' . esc_html__('Report', 'wp-autoload-inspector') . '</h2>';
            echo '<p>' . esc_html(sprintf('%d options / %d stored bytes. Largest 20 listed below.', $report['total_options'], $report['total_bytes'])) . '</p>';
            echo '<table class="widefat striped"><thead><tr><th>Rank</th><th>Bytes</th><th>Share %</th><th>Option</th></tr></thead><tbody>';
            foreach ($report['largest'] as $row) {
                echo '<tr><td>' . esc_html((string) $row['rank']) . '</td><td>' . esc_html((string) $row['bytes']) . '</td><td>' . esc_html((string) $row['share_percent']) . '</td><td>' . esc_html(isset($row['name']) ? $row['name'] : 'Hidden') . '</td></tr>';
            }
            echo '</tbody></table><h3>JSON</h3><pre style="white-space:pre-wrap;overflow-wrap:anywhere">' . esc_html(wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
        } catch (\RuntimeException $e) {
            echo '<div class="notice notice-error"><p>' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
    echo '<p>' . esc_html__('This is a database estimate, not PHP memory usage or a speed benchmark. Results may change between queries. Review the owning component before changing any option.', 'wp-autoload-inspector') . '</p></div>';
}

if (defined('WP_CLI') && WP_CLI) {
    /**
     * Inspect stored bytes of autoloaded options on the current site.
     *
     * ## OPTIONS
     *
     * [--include-names]
     * : Include option names; default output contains ranks and sizes only.
     *
     * [--top=<number>]
     * : Number of largest options (1–100). Default: 20.
     *
     * ## EXAMPLES
     *
     *     wp nakaryu autoload inspect --top=20
     */
    \WP_CLI::add_command('nakaryu autoload inspect', function ($args, $assoc_args) {
        try {
            $top = isset($assoc_args['top']) ? $assoc_args['top'] : '20';
            if (!is_scalar($top) || !preg_match('/\A[0-9]+\z/', (string) $top) || (int) $top < 1 || (int) $top > 100) {
                throw new \RuntimeException('top must be an integer from 1 to 100.');
            }
            global $wpdb;
            \WP_CLI::line(wp_json_encode(Inspector::collect($wpdb, isset($assoc_args['include-names']), (int) $top), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\RuntimeException $e) { \WP_CLI::error($e->getMessage()); }
    });
}
