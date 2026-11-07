<?php
// Exercise the real language loader and controller fallback without a web request.
define('BASEPATH', __DIR__ . '/../crbs-core/system/');
define('APPPATH', __DIR__ . '/../crbs-core/application/');
define('EXT', '.php');
function log_message($level, $message) {}
function show_error($message) { throw new RuntimeException($message); }
function &get_config() { static $config = ['language' => 'french']; return $config; }
function config_item($key) { $config = &get_config(); return $config[$key] ?? null; }
function &get_instance() { global $instance; return $instance; }
class CI { public static $APP; }
class Modules { static function find($file, $module, $path) { return [false, null]; } }
class TestResult {
	private $rows;
	function __construct($rows) { $this->rows = $rows; }
	function row() { return (object) $this->rows[0]; }
	function num_rows() { return count($this->rows); }
	function result_array() { return $this->rows; }
}
class TestDb {
	function table_exists($table) { return true; }
	function query($sql, $bindings = []) {
		if (strpos($sql, 'COUNT') !== false) return new TestResult([['total' => 1]]);
		return new TestResult(($bindings[0] ?? '') === 'french' ? [
			['set' => 'booking_cancel_multi', 'key' => 'booking.cancel_multi.failed', 'text' => 'erreur %1$d %2$d'],
			['set' => 'booking_cancel_multi', 'key' => 'booking.cancel.all.eligible.success', 'text' => 'traduction de remplacement'],
		] : []);
	}
}
function html_escape($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
require BASEPATH . 'core/Lang.php';
require APPPATH . 'core/MY_Lang.php';
class MY_Controller { public $lang; }
require APPPATH . 'controllers/Bookings.php';
$instance = (object) [
	'db' => new TestDb(),
	'load' => new class { function get_package_paths($include_base = false) { return [APPPATH]; } },
];
CI::$APP = (object) [
	'config' => new class { function item($key) { return config_item($key); } },
	'router' => new class { function fetch_module() { return ''; } },
];
$controller = (new ReflectionClass(Bookings::class))->newInstanceWithoutConstructor();
$controller->lang = new MY_Lang();
$controller->lang->language['booking.cancel.all.eligible.success'] = 'traduction existante';
$method = new ReflectionMethod(Bookings::class, 'load_cancellation_messages');
$method->setAccessible(true);
$method->invoke($controller);
if ($controller->lang->language['booking.cancel.all.eligible.success'] !== 'traduction existante') throw new RuntimeException('existing translation was overwritten');
if ($controller->lang->language['booking.cancel_multi.failed'] !== 'erreur %1$d %2$d') throw new RuntimeException('active-language database translation was not used');
if ($controller->lang->language['booking.cancel_multi.back'] !== 'Back to bookings') throw new RuntimeException('missing translation did not fall back to English');
echo "PASS real language loader preserves existing translations and supplies localized and English fallbacks\n";