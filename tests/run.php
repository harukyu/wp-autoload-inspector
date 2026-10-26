<?php
/**
 * Behavioral checks against an isolated SQLite options table.
 * Copyright 2026 Max Wellner / Nakaryu GmbH. GPL-2.0-or-later.
 */
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$autoload_states = array('yes', 'on', 'auto', 'auto-on');
function wp_autoload_values_to_autoload() {
    global $autoload_states;
    return $autoload_states;
}

require_once dirname(__DIR__) . '/includes/Inspector.php';

final class TestDatabase {
    public $options = 'wp_options';
    public $last_error = '';
    public $queries = array();
    public $suppressed = false;
    private $pdo;

    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('OCTET_LENGTH', 'strlen', 1);
        foreach (array('wp_options', 'wp_2_options') as $table) {
            $this->pdo->exec('CREATE TABLE ' . $table . ' (option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)');
        }
    }

    public function add($table, $id, $name, $value, $autoload) {
        $stmt = $this->pdo->prepare('INSERT INTO ' . $table . ' VALUES (?, ?, ?, ?)');
        $stmt->execute(array($id, $name, $value, $autoload));
    }

    public function suppress_errors($suppress = true) {
        $previous = $this->suppressed;
        $this->suppressed = $suppress;
        return $previous;
    }

    public function prepare($query, $args) {
        $position = 0;
        $pdo = $this->pdo;
        return preg_replace_callback('/%[sd]/', function ($match) use ($args, &$position, $pdo) {
            $value = $args[$position++];
            return $match[0] === '%d' ? (string) (int) $value : $pdo->quote($value);
        }, $query);
    }

    private function read($query, $single) {
        $this->queries[] = $query;
        $this->last_error = '';
        try {
            $result = $this->pdo->query($query);
            return $single ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $error) {
            $this->last_error = $error->getMessage();
            return null;
        }
    }

    public function get_row($query, $format) {
        return $this->read($query, true);
    }

    public function get_results($query, $format) {
        return $this->read($query, false);
    }
}

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = new TestDatabase();
$db->add('wp_options', 1, 'accent', 'é', 'yes'); // Two UTF-8 bytes, one character.
$db->add('wp_options', 2, 'large', 'abcdefgh', 'on');
$db->add('wp_options', 3, 'first_tie', 'abcd', 'auto');
$db->add('wp_options', 4, 'second_tie', 'wxyz', 'auto-on');
$db->add('wp_options', 5, 'excluded', 'never count this', 'off');
$db->add('wp_options', 6, 'also_excluded', 'nor this', 'auto-off');
$db->add('wp_2_options', 1, 'other_site', '12345', 'yes');

$report = \Nakaryu\AutoloadInspector\Inspector::collect($db, false, 2);
check($report['total_options'] === 4 && $report['total_bytes'] === 18, 'Autoload states or byte counts differ.');
check(count($report['largest']) === 2 && $report['largest'][0]['bytes'] === 8, 'Top limit or ordering differs.');
check($report['largest'][0]['share_percent'] === 44.44, 'Share differs.');
check(!array_key_exists('name', $report['largest'][0]) && !$report['names_included'], 'Default report exposes an option name.');
check(count($db->queries) === 2 && strpos($db->queries[1], 'option_name') === false, 'Default query selects names or unexpected queries ran.');
check(strpos($db->queries[0], 'SELECT COUNT(*)') === 0 && strpos($db->queries[1], 'SELECT OCTET_LENGTH') === 0, 'A query selects option values or changes data.');
check($db->suppressed === false, 'Database error setting was not restored.');

$named = \Nakaryu\AutoloadInspector\Inspector::collect($db, true, 4);
check($named['largest'][0]['name'] === 'large', 'Opt-in names missing.');
check($named['largest'][1]['name'] === 'first_tie' && $named['largest'][2]['name'] === 'second_tie', 'Equal-size ordering differs.');
check($named['largest'][3]['bytes'] === 2, 'Multibyte value was not counted in bytes.');

$autoload_states = array('on');
$filtered = \Nakaryu\AutoloadInspector\Inspector::collect($db);
check($filtered['total_options'] === 1 && $filtered['total_bytes'] === 8, 'Runtime autoload filter was ignored.');

$db->options = 'wp_2_options';
$autoload_states = array('yes');
$other_site = \Nakaryu\AutoloadInspector\Inspector::collect($db);
check($other_site['total_options'] === 1 && $other_site['total_bytes'] === 5, 'Current-site table was ignored.');

$autoload_states = array();
$before = count($db->queries);
$empty = \Nakaryu\AutoloadInspector\Inspector::collect($db);
check($empty['total_options'] === 0 && count($db->queries) === $before, 'Empty state list should not query the database.');

try {
    \Nakaryu\AutoloadInspector\Inspector::collect($db, false, 0);
    throw new RuntimeException('Invalid limit was accepted.');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'Invalid report limit.', 'Unexpected limit error.');
}

$autoload_states = array('yes');
$db->options = 'missing_options';
try {
    \Nakaryu\AutoloadInspector\Inspector::collect($db);
    throw new RuntimeException('Database failure was ignored.');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'Could not read database sizes. No database error details are included.', 'Database details leaked.');
    check($db->suppressed === false, 'Database error setting was not restored after failure.');
}

echo "Autoload report behavior passed.\n";
