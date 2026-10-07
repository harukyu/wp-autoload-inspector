<?php
namespace Nakaryu\AutoloadInspector;
if (!defined('ABSPATH')) { exit; }

final class Inspector {
    const VERSION = '0.1.1';

    /** SELECT-only inspection of current site's options table; never fetch values. */
    public static function collect($database, $include_names = false, $top = 20) {
        if (!is_int($top) || $top < 1 || $top > 100) { throw new \RuntimeException('Invalid report limit.'); }
        $states = function_exists('wp_autoload_values_to_autoload') ? array_values(wp_autoload_values_to_autoload()) : array('yes');
        if (!$states) {
            return array('schema_version' => 1, 'version' => self::VERSION, 'autoload_states' => array(), 'total_options' => 0, 'total_bytes' => 0, 'largest' => array(), 'names_included' => (bool) $include_names);
        }
        // Table identifier comes only from WordPress's configured wpdb instance.
        $table = '`' . str_replace('`', '``', $database->options) . '`';
        $where = 'autoload IN (' . implode(',', array_fill(0, count($states), '%s')) . ')';
        $previous = $database->suppress_errors(true);
        try {
            $totals = $database->get_row($database->prepare('SELECT COUNT(*) AS total_options, COALESCE(SUM(OCTET_LENGTH(option_value)), 0) AS total_bytes FROM ' . $table . ' WHERE ' . $where, $states), ARRAY_A);
            if ($database->last_error || !is_array($totals)) { throw new \RuntimeException('Could not read database sizes. No database error details are included.'); }
            $columns = $include_names ? 'option_name, ' : '';
            $params = array_merge($states, array($top));
            $rows = $database->get_results($database->prepare('SELECT ' . $columns . 'OCTET_LENGTH(option_value) AS bytes FROM ' . $table . ' WHERE ' . $where . ' ORDER BY bytes DESC, option_id ASC LIMIT %d', $params), ARRAY_A);
            if ($database->last_error || !is_array($rows)) { throw new \RuntimeException('Could not read database sizes. No database error details are included.'); }
        } finally { $database->suppress_errors($previous); }
        $total = (int) $totals['total_bytes'];
        $largest = array();
        foreach ($rows as $index => $row) {
            $bytes = (int) $row['bytes'];
            $item = array('rank' => $index + 1, 'bytes' => $bytes, 'share_percent' => $total > 0 ? round($bytes * 100 / $total, 2) : 0.0);
            if ($include_names) { $item['name'] = $row['option_name']; }
            $largest[] = $item;
        }
        return array('schema_version' => 1, 'version' => self::VERSION, 'autoload_states' => $states, 'total_options' => (int) $totals['total_options'], 'total_bytes' => $total, 'largest' => $largest, 'names_included' => (bool) $include_names);
    }
}
