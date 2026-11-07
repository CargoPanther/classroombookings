<?php
namespace app\components\bookings\exceptions {
	class AgentException extends \Exception {}
}
namespace app\components\bookings\agent {
	class SingleAgent {
		public static $mode = 'construction';
		public $title = '', $title_html = '', $slot_header = null, $message = 'success';
		public static function create() {
			if (self::$mode === 'construction') throw new \app\components\bookings\exceptions\AgentException('construction failed');
			return new static();
		}
		public function load() {}
		public function process() {}
		public function render() { throw new \app\components\bookings\exceptions\AgentException('render failed'); }
		public function is_success() { return true; }
	}
	class MultiAgent extends SingleAgent {}
	class UpdateAgent extends SingleAgent {}
}
namespace {
	define('BASEPATH', __DIR__);
	class MY_Controller {
		public $data = [], $input, $load, $lang, $session, $bookings_model, $userauth;
		function render() { return $this->data; }
	}
	class Bookings_model { const STATUS_BOOKED = 10, STATUS_CANCELLED = 15; }
	function lang($key) {
		global $test_lang;
		if (isset($test_lang->language[$key])) return $test_lang->language[$key];
		$lines = [
			'booking.cancel_multi.number_cancelled' => '%d cancelled',
			'booking.cancel_multi.none_cancelled' => 'none cancelled',
			'booking.cancel_multi.failed' => '%1$d failed; %2$d retryable',
		];
		return $lines[$key] ?? $key;
	}
	function config_item($key) { return $key === 'language' ? 'french' : null; }
	function msgbox($type, $message) { return "[$type:$message]"; }
	function anchor($url, $text, $attrs = []) { return "[link:$url:$text]"; }
	function site_url($uri) { return $uri; }
	function redirect($uri) { $GLOBALS['redirected'] = $uri; }
	function booking_cancelable($booking) { return $booking->allowed; }
	require __DIR__ . '/../crbs-core/application/controllers/Bookings.php';

	class TestInput {
		public $post = [], $get = [];
		function post($key) { return $this->post[$key] ?? null; }
		function get($key) { return $this->get[$key] ?? null; }
		function post_get($key) { return $this->post($key) ?? $this->get($key); }
	}
	class TestLoader {
		public $views = [];
		function library($name) {}
		function view($name, $data, $return) { $this->views[] = [$name, $data]; return "[view:$name]"; }
	}
	class TestLang {
		public $language = [], $translations = [], $loads = [];
		function load($name, $language, $return = false) {
			$this->loads[] = [$name, $language, $return];
			$lang = [];
			require __DIR__ . '/../crbs-core/application/language/english/booking_cancel_multi_lang.php';
			if ($return) return $lang;
			$this->language = array_merge($this->language, $lang);
		}
		function load_from_db($name, $language) { return $this->translations[$language] ?? []; }
	}
	class TestSession {
		public $flash = [];
		function set_flashdata($key, $value) { $this->flash[$key] = $value; }
	}
	class TestBookings extends Bookings_model {
		public $rows = [], $results = [], $calls = [], $find_calls = [], $series_result = false;
		function include($objects) { return $this; }
		function get($id) { return $this->rows[$id] ?? false; }
		function find_by_ids(array $ids) {
			$this->find_calls[] = $ids;
			return array_values(array_filter($this->rows, function ($row) use ($ids) {
				return in_array($row->booking_id, $ids) && $row->status == self::STATUS_BOOKED;
			}));
		}
		function cancel_single($id) {
			$this->calls[] = (int) $id;
			if (empty($this->results[$id])) return false;
			$this->rows[$id]->status = self::STATUS_CANCELLED;
			return true;
		}
		function cancel_future($id) { $this->calls[] = 'future:' . $id; return $this->series_result; }
		function cancel_all($id) { $this->calls[] = 'all:' . $id; return $this->series_result; }
	}
	function controller($post = []) {
		$c = (new \ReflectionClass(Bookings::class))->newInstanceWithoutConstructor();
		$c->input = new TestInput(); $c->input->post = $post;
		$c->load = new TestLoader(); $c->lang = new TestLang();
		$GLOBALS['test_lang'] = $c->lang;
		$c->session = new TestSession(); $c->bookings_model = new TestBookings();
		$c->userauth = (object) ['user' => (object) ['user_id' => 1]];
		$GLOBALS['redirected'] = null; $_SESSION = [];
		return $c;
	}
	function row($id, $allowed = true, $status = Bookings_model::STATUS_BOOKED) {
		return (object) ['booking_id' => $id, 'allowed' => $allowed, 'status' => $status];
	}
	function check($condition, $name) {
		if (!$condition) throw new \RuntimeException($name);
		echo "PASS $name\n";
	}
	set_error_handler(function ($severity, $message) { throw new \ErrorException($message, 0, $severity); });

	$c = controller(['step' => 'confirm', 'bookings' => [1 => 'cancel', 2 => 'cancel', 3 => 'cancel', 4 => 'cancel', 5 => 'keep', 'invalid' => 'cancel'], 'params' => 'date=2026-11-07']);
	$c->bookings_model->rows = [1 => row(1), 2 => row(2), 3 => row(3, false), 4 => row(4, true, Bookings_model::STATUS_CANCELLED), 5 => row(5)];
	$c->bookings_model->results = [1 => true, 2 => false];
	$c->lang->language['booking.cancel_multi.failed'] = '%1$d failed; %2$d retryable';
	$result = $c->cancel_multi();
	check($c->bookings_model->calls === [1, 2], 'only active and authorized selected bookings are attempted');
	check(strpos($result['view'], '1 cancelled') !== false && strpos($result['view'], '4 failed; 1 retryable') !== false, 'mixed outcomes report only actual successes and all failures');
	check(array_column($result['bookings'], 'booking_id') === [2] && $GLOBALS['redirected'] === null, 'failed active booking remains available for retry');
	check($_SESSION['return_uri'] === 'bookings?date=2026-11-07', 'return location retained after partial failure');
	$c->bookings_model->results[2] = true; $c->input->post = ['step' => 'confirm', 'bookings' => [2 => 'cancel']];
	$c->cancel_multi();
	check($GLOBALS['redirected'] === 'bookings?date=2026-11-07' && strpos($c->session->flash['bookings'], '1 cancelled') !== false, 'retry success redirects with accurate count');
	check(!isset($_SESSION['return_uri']), 'successful completion clears return location');

	$c = controller(['step' => 'confirm', 'bookings' => [8 => 'cancel']]);
	$c->lang->translations['french']['booking.cancel_multi.failed'] = '%1$d failed; %2$d retryable';
	$c->bookings_model->rows = [8 => row(8, false)];
	$result = $c->cancel_multi();
	check(strpos($result['view'], 'none cancelled') !== false && strpos($result['view'], '1 failed; 0 retryable') !== false && !$c->bookings_model->calls, 'unauthorized failure is reported without retry or mutation');
	check(strpos($result['view'], '[link:bookings:') !== false, 'failure without retry offers a return link');
	$c = controller(['step' => 'confirm', 'bookings' => [9 => 'cancel']]);
	$c->lang->language['booking.cancel_multi.failed'] = '%1$d failed; %2$d retryable';
	$result = $c->cancel_multi();
	check(strpos($result['view'], '1 failed; 0 retryable') !== false && !$c->bookings_model->calls, 'missing booking is reported as a failure');

	$c = controller(['step' => 'selection', 'bookings' => [1, 2, ['bad'], 'invalid']]);
	$c->bookings_model->rows = [1 => row(1), 2 => row(2, false)];
	$result = $c->cancel_multi();
	check($c->bookings_model->find_calls[0] === [1, 2] && array_column($result['bookings'], 'booking_id') === [1], 'selection validates ids and excludes unauthorized bookings');
	$c = controller(['step' => 'selection', 'bookings' => [2]]); $c->bookings_model->rows = [2 => row(2, false)];
	$result = $c->cancel_multi();
	check(strpos($result['view'], 'booking.cancel_multi.error.none_selected') !== false, 'selection with no eligible bookings reports empty selection');
	$c = controller(['step' => 'confirm', 'bookings' => 'invalid']); $c->cancel_multi();
	check(strpos($c->session->flash['bookings'], 'none cancelled') !== false && !$c->bookings_model->calls, 'malformed confirmation does not cancel anything');
	$c = controller(['step' => 'confirm', 'bookings' => [1 => 'cancel', '01' => 'cancel']]);
	$c->bookings_model->rows = [1 => row(1)]; $c->bookings_model->results = [1 => true];
	$c->cancel_multi();
	check($c->bookings_model->calls === [1] && $c->session->flash['bookings'] === '[info:1 cancelled]', 'equivalent booking ids are not reported as a second failed cancellation');

	$c = controller(['cancel' => 'all']); $c->bookings_model->rows = [1 => row(1)];
	$c->bookings_model->series_result = true;
	$c->cancel(1);
	check(strpos($c->session->flash['bookings'], 'Some past bookings may remain') !== false, 'cancel-all success does not claim skipped past bookings were cancelled');
	check($c->bookings_model->calls === ['all:1'] && $GLOBALS['redirected'] === 'bookings', 'cancel-all success redirects after the model succeeds');
	$c = controller(['cancel' => 'all']); $c->bookings_model->rows = [1 => row(1)];
	$c->lang->language['booking.cancel.all.eligible.success'] = 'localized all';
	$c->bookings_model->series_result = true; $c->cancel(1);
	check($c->session->flash['bookings'] === '[info:localized all]', 'existing booking-set translation takes precedence over English fallback');
	$c = controller(['cancel' => 'all']); $c->bookings_model->rows = [1 => row(1)];
	$c->cancel(1);
	check($c->session->flash['bookings'] === '[error:booking.error.cancelling]', 'failed cancel-all reports an error, not success');
	$c = controller(['cancel' => 'future']); $c->bookings_model->rows = [1 => row(1)];
	$c->bookings_model->series_result = true; $c->cancel(1);
	check($c->session->flash['bookings'] === '[info:booking.cancel.future.success]', 'future cancellation retains its existing translated message');
	$c = controller(['cancel' => '1']); $c->bookings_model->rows = [1 => row(1)];
	$c->cancel(1);
	check($c->session->flash['bookings'] === '[error:booking.error.cancelling]', 'single cancellation failure does not report success');
	$c = controller(['cancel' => 'invalid']); $c->bookings_model->rows = [1 => row(1)]; $c->cancel(1);
	check($c->session->flash['bookings'] === '[error:booking.cancel.invalid_type.error]' && !$c->bookings_model->calls, 'invalid cancellation type is rejected');

	$c = controller(); $result = $c->create('unknown');
	check(strpos($result['view'], 'booking.error.bad_type') !== false, 'unknown creation type is rejected');
	$c = controller(); $result = $c->create('single');
	check(strpos($result['view'], 'construction failed') !== false, 'create displays agent construction exception');
	$c = controller(); $result = $c->edit(1);
	check(strpos($result['view'], 'construction failed') !== false, 'edit displays agent construction exception');
	\app\components\bookings\agent\SingleAgent::$mode = 'render';
	$c = controller(); $result = $c->create('single');
	check(strpos($result['view'], 'render failed') !== false && $GLOBALS['redirected'] === null, 'create does not redirect as success after an agent exception');
	$c = controller(); $result = $c->edit(1);
	check(strpos($result['view'], 'render failed') !== false && $GLOBALS['redirected'] === null, 'edit does not redirect as success after an agent exception');
	echo "All controller regression tests passed.\n";
}