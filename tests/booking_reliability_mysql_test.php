<?php
// Standalone integration test. Requires mysqli and a MySQL user permitted to
// create/drop a database, supplied via BOOKING_TEST_MYSQL_* environment vars.
define('BASEPATH', __DIR__);
eval('namespace app\components\bookings\agent; class UpdateAgent { const EDIT_ONE="1", EDIT_FUTURE="future", EDIT_ALL="all"; }');
require __DIR__ . '/../crbs-core/application/components/bookings/exceptions/BookingValidationException.php';
class CI_Model { public $db; public $userauth; }
class Permission {
	const BK_RECUR_VIEW_OTHER_NOTES = 'rec_notes', BK_SGL_VIEW_OTHER_NOTES = 'notes';
	const BK_RECUR_VIEW_OTHER_USERS = 'rec_users', BK_SGL_VIEW_OTHER_USERS = 'users';
	const BK_RECUR_SET_USER = 'rec_set_user', BK_SGL_SET_USER = 'set_user';
	const BK_RECUR_SET_DEPT = 'rec_set_dept', BK_SGL_SET_DEPT = 'set_dept';
	const BK_RECUR_CANCEL_OTHER = 'rec_cancel', BK_SGL_CANCEL_OTHER = 'cancel';
	const BK_RECUR_EDIT_OTHER = 'rec_edit', BK_SGL_EDIT_OTHER = 'edit';
}
$permissions = [];
function lang($key) { return $key; }
function has_permission($permission, $room = NULL) {
	global $permissions, $allow_cancel_other, $allow_cancel_global, $allow_edit_other, $cancel_types, $cancel_room;
	if (in_array($permission, [Permission::BK_SGL_CANCEL_OTHER, Permission::BK_RECUR_CANCEL_OTHER], TRUE)) {
		return in_array($permission, $cancel_types, TRUE) && ($allow_cancel_global || ($room !== NULL && (int) $room === $cancel_room && $allow_cancel_other));
	}
	if (in_array($permission, [Permission::BK_SGL_EDIT_OTHER, Permission::BK_RECUR_EDIT_OTHER], TRUE)) return $allow_edit_other;
	return in_array($permission, $permissions, TRUE);
}
function &get_instance() { global $ci; return $ci; }
function datetime_from_string($value) { return $value instanceof DateTime ? $value : new DateTime($value); }
function element($key, $data, $default = NULL) { return $data[$key] ?? $default; }
require __DIR__ . '/../crbs-core/application/helpers/booking_helper.php';
require __DIR__ . '/../crbs-core/application/models/Bookings_model.php';
require __DIR__ . '/../crbs-core/application/models/Bookings_repeat_model.php';

class TestResult {
	private $rows;
	public function __construct($result) { $this->rows = $result->fetch_all(MYSQLI_ASSOC); }
	public function row() { return isset($this->rows[0]) ? (object) $this->rows[0] : NULL; }
	public function result() { return array_map(fn($row) => (object) $row, $this->rows); }
}
class TestDb {
	public $connection;
	public $fail_repeat_update = FALSE;
	public $fail_booking_update = FALSE;
	public $throw_booking_insert = FALSE;
	public $fail_commit = FALSE;
	public $last_error = NULL;
	public $cancel_updates = 0;
	public $booking_inserts = 0;
	private $depth = 0;
	private $status = TRUE;
	private $affected = 0;
	private $insert_id = 0;
	public function __construct($connection) { $this->connection = $connection; }
	public function query($sql, $bindings = [], $return_object = NULL) {
		try {
			if (preg_match('/^(SAVEPOINT|ROLLBACK TO SAVEPOINT|RELEASE SAVEPOINT) /', $sql)) {
				if ($return_object !== FALSE) throw new RuntimeException('savepoint must request no result object');
				return $this->connection->query($sql);
			}
			$stmt = $this->connection->prepare($sql);
			$stmt->execute($bindings);
			$this->affected = $stmt->affected_rows;
			if ($stmt->insert_id) $this->insert_id = $stmt->insert_id;
			$result = $stmt->get_result();
			return $result ? new TestResult($result) : TRUE;
		} catch (mysqli_sql_exception $e) { $this->last_error = $e->getMessage(); $this->status = FALSE; return FALSE; }
	}
	public function trans_active() { return $this->depth > 0; }
	public function trans_begin() { if ($this->depth === 0) { if (!$this->connection->begin_transaction()) return FALSE; $this->status = TRUE; } $this->depth++; return TRUE; }
	public function trans_commit() { if (!$this->depth || ($this->depth === 1 && ($this->fail_commit || !$this->connection->commit()))) return FALSE; $this->depth--; return TRUE; }
	public function trans_rollback() { if (!$this->depth || ($this->depth === 1 && !$this->connection->rollback())) return FALSE; $this->depth--; return TRUE; }
	public function trans_status() { return $this->status; }
	public function affected_rows() { return $this->affected; }
	public function insert_id() { return $this->insert_id; }
	public function insert($table, $data) {
		if ($table === 'bookings' && $this->throw_booking_insert) throw new RuntimeException('injected insert exception');
		$keys = array_keys($data);
		$result = $this->query('INSERT INTO ' . $table . ' (`' . implode('`,`', $keys) . '`) VALUES (' . implode(',', array_fill(0, count($keys), '?')) . ')', array_values($data));
		if ($result && $table === 'bookings') $this->booking_inserts++;
		return $result;
	}
	public function update($table, $data, $where, $limit = NULL) {
		if ($table === 'bookings_repeat' && $this->fail_repeat_update) return FALSE;
		if ($table === 'bookings' && $this->fail_booking_update) return FALSE;
		$sets = array_map(fn($key) => "`$key` = ?", array_keys($data));
		$conditions = array_map(fn($key) => "`$key` = ?", array_keys($where));
		$result = $this->query('UPDATE ' . $table . ' SET ' . implode(',', $sets) . ' WHERE ' . implode(' AND ', $conditions) . ($limit ? ' LIMIT ' . (int) $limit : ''), array_merge(array_values($data), array_values($where)));
		if ($result && $table === 'bookings' && ($data['status'] ?? NULL) === Bookings_model::STATUS_CANCELLED && $this->affected_rows() === 1) $this->cancel_updates++;
		return $result;
	}
}
class TestBookings extends Bookings_model {
	public function __construct($db) { $this->db = $db; $this->userauth = (object) ['user' => (object) ['user_id' => 1]]; }
	public function get($id) {
		$query = $this->db->query('SELECT * FROM bookings WHERE booking_id = ?', [$id]);
		$row = $query->row();
		if ($row) $row->date = new DateTime($row->date);
		return $row ?: FALSE;
	}
}
class TestRepeat extends Bookings_repeat_model {
	public $bookings_model;
	public function __construct($db, $bookings) { $this->db = $db; $this->bookings_model = $bookings; $this->userauth = $bookings->userauth; }
}
function check($condition, $message) { if ( ! $condition) throw new RuntimeException($message); echo "PASS $message\n"; }

$host = getenv('BOOKING_TEST_MYSQL_HOST') ?: '127.0.0.1';
$user = getenv('BOOKING_TEST_MYSQL_USER') ?: 'root';
$password = getenv('BOOKING_TEST_MYSQL_PASSWORD') ?: '';
$schema = 'booking_reliability_test_' . bin2hex(random_bytes(6));
$admin = new mysqli($host, $user, $password);
$admin->query("CREATE DATABASE `$schema`");
try {
	$a = new mysqli($host, $user, $password, $schema);
	$b = new mysqli($host, $user, $password, $schema);
	$a->query('CREATE TABLE rooms (room_id INT PRIMARY KEY, capacity INT NOT NULL) ENGINE=InnoDB');
	$a->query('CREATE TABLE periods (period_id INT PRIMARY KEY, time_start TIME, time_end TIME) ENGINE=InnoDB');
	$a->query('CREATE TABLE bookings_repeat (repeat_id INT PRIMARY KEY AUTO_INCREMENT, session_id INT, period_id INT, room_id INT, user_id INT NULL, capacity INT, department_id INT NULL, notes VARCHAR(255) NULL, created_at DATETIME, created_by INT, updated_at DATETIME NULL, updated_by INT NULL, status INT DEFAULT 10, cancelled_at DATETIME NULL, cancelled_by INT NULL) ENGINE=InnoDB');
	$a->query('CREATE TABLE bookings (booking_id INT PRIMARY KEY AUTO_INCREMENT, repeat_id INT NULL, session_id INT, period_id INT, room_id INT, user_id INT NULL, capacity INT, department_id INT NULL, date DATE, status INT, notes VARCHAR(255) NULL, created_at DATETIME NULL, created_by INT, updated_at DATETIME NULL, updated_by INT NULL, cancelled_at DATETIME NULL, cancelled_by INT NULL, INDEX slot_idx (room_id, date, period_id, status), INDEX repeat_idx (repeat_id, session_id, date)) ENGINE=InnoDB');
	$a->query("INSERT INTO rooms VALUES (1, 3), (2, 3); ");
	$a->query("INSERT INTO periods VALUES (1, '09:00:00', '12:00:00')");
	$db = new TestDb($a);
	$model = new TestBookings($db);
	$repeat = new TestRepeat($db, $model);
	$ci = (object) ['userauth' => $model->userauth];
	$allow_edit_other = FALSE;
	$allow_cancel_other = FALSE;
	$allow_cancel_global = FALSE;
	$cancel_types = [Permission::BK_SGL_CANCEL_OTHER, Permission::BK_RECUR_CANCEL_OTHER];
	$cancel_room = 1;
	$base = ['session_id' => 1, 'period_id' => 1, 'room_id' => 1, 'user_id' => 1, 'capacity' => 1, 'date' => '2030-01-01', 'status' => 10, 'created_by' => 1];
	check( ! $model->create(array_merge($base, ['capacity' => '1invalid'])), 'invalid capacity is not silently coerced');

	// An earlier consistent read must not hide a later committed booking.
	$db->trans_begin();
	$a->query('SELECT * FROM bookings')->fetch_all();
	$b->query("INSERT INTO bookings (session_id,period_id,room_id,user_id,capacity,date,status,created_by) VALUES (1,1,1,2,3,'2030-01-01',10,2)");
	try { $model->validate_booking($base); check(FALSE, 'current capacity read'); }
	catch (\app\components\bookings\exceptions\BookingValidationException $e) { check($e->getMessage() === 'booking.capacity.invalid', 'current capacity read'); }
	$db->trans_rollback();
	$a->query('DELETE FROM bookings');
	// The second transaction waits on the same room and must see the first
	// transaction's booking, despite an earlier consistent-read snapshot.
	$db2 = new TestDb($b);
	$model2 = new TestBookings($db2);
	$db2->trans_begin();
	$b->query('SELECT * FROM bookings')->fetch_all();
	$db->trans_begin();
	$a->query('SELECT room_id FROM rooms WHERE room_id=1 FOR UPDATE');
	$a->query("INSERT INTO bookings (session_id,period_id,room_id,user_id,capacity,date,status,created_by) VALUES (1,1,1,2,3,'2030-01-01',10,2)");
	$b->query('SELECT room_id FROM rooms WHERE room_id=1 FOR UPDATE', MYSQLI_ASYNC);
	$read = [$b]; $errors = []; $reject = [];
	check(mysqli_poll($read, $errors, $reject, 0, 100000) === 0, 'competing room lock waits');
	$db->trans_commit();
	$read = [$b]; $errors = []; $reject = [];
	check(mysqli_poll($read, $errors, $reject, 5) === 1 && (bool) $b->reap_async_query(), 'competing room lock resumes after commit');
	try { $model2->validate_booking($base); check(FALSE, 'waiting transaction sees committed capacity'); }
	catch (\app\components\bookings\exceptions\BookingValidationException $e) { check($e->getMessage() === 'booking.capacity.invalid', 'waiting transaction sees committed capacity'); }
	$db2->trans_rollback();
	$a->query('DELETE FROM bookings');
	$db->trans_begin();
	$a->query('SELECT * FROM bookings')->fetch_all();
	$b->query("INSERT INTO bookings (session_id,period_id,room_id,user_id,capacity,date,status,created_by) VALUES (1,1,1,1,1,'2030-01-01',10,1)");
	try { $model->validate_booking($base); check(FALSE, 'current duplicate read'); }
	catch (\app\components\bookings\exceptions\BookingValidationException $e) { check($e->getMessage() === 'booking.capacity.duplicate', 'current duplicate read'); }
	$db->trans_rollback();
	$id = (int) $a->query('SELECT booking_id FROM bookings')->fetch_row()[0];
	$snapshot = $model->get($id);
	check( ! $model->update($id, ['notes' => 'bad'], 'all', $snapshot), 'non-series all rejected');
	$b->query("UPDATE bookings SET notes='concurrent' WHERE booking_id=$id");
	check( ! $model->update($id, ['notes' => 'stale'], '1', $snapshot), 'stale edit rejected');
	check($a->query("SELECT notes FROM bookings WHERE booking_id=$id")->fetch_row()[0] === 'concurrent', 'stale edit preserves newer value');
	check( ! $model->cancel_all(99999) && ! $model->cancel_future($id), 'invalid series cancellation rejected');

	// The model must use a current locking read, not an earlier authorization snapshot.
	$db->trans_begin();
	$a->query('SELECT * FROM bookings')->fetch_all();
	$b->query("UPDATE bookings SET user_id=2 WHERE booking_id=$id");
	check( ! $model->cancel_single($id), 'single cancellation rechecks current owner after stale snapshot');
	check($db->trans_commit(), 'outer transaction remains balanced after rejected cancellation');
	check((int) $a->query("SELECT status FROM bookings WHERE booking_id=$id")->fetch_row()[0] === 10, 'unauthorized single cancellation leaves booking active');
	check( ! $model->cancel_single(99999), 'missing single cancellation rejected');
	$allow_cancel_other = TRUE;
	$db->fail_booking_update = TRUE;
	check( ! $model->cancel_single($id), 'single cancellation update failure reported');
	$db->fail_booking_update = FALSE;
	$db->fail_commit = TRUE;
	check( ! $model->cancel_single($id), 'single cancellation commit failure reported');
	$db->fail_commit = FALSE;
	check((int) $a->query("SELECT status FROM bookings WHERE booking_id=$id")->fetch_row()[0] === 10, 'failed single cancellation rolls back');
	check($model->cancel_single($id), 'authorized single cancellation succeeds');
	$cancelled = $a->query("SELECT status, cancelled_by, cancelled_at FROM bookings WHERE booking_id=$id")->fetch_assoc();
	check((int) $cancelled['status'] === 15 && (int) $cancelled['cancelled_by'] === 1 && $cancelled['cancelled_at'] !== NULL, 'single cancellation records actor and time');
	check( ! $model->cancel_single($id), 'already cancelled booking rejected');
	check($a->query("SELECT cancelled_at FROM bookings WHERE booking_id=$id")->fetch_row()[0] === $cancelled['cancelled_at'], 'repeat cancellation preserves audit data');
	$allow_cancel_other = FALSE;
	$a->query("INSERT INTO bookings (session_id,period_id,room_id,user_id,capacity,date,status,created_by) VALUES (1,1,1,1,1,'2030-04-01',10,1), (1,1,1,1,1,'2030-04-02',10,1)");
	$stale_status = (int) $a->query("SELECT booking_id FROM bookings WHERE date='2030-04-01'")->fetch_row()[0];
	$nested_single = (int) $a->query("SELECT booking_id FROM bookings WHERE date='2030-04-02'")->fetch_row()[0];
	$db->trans_begin();
	$a->query('SELECT * FROM bookings')->fetch_all();
	$b->query("UPDATE bookings SET status=15, cancelled_by=2 WHERE booking_id=$stale_status");
	check( ! $model->cancel_single($stale_status), 'single cancellation rechecks status after stale snapshot');
	check($db->trans_commit(), 'outer transaction remains balanced after stale status');
	check((int) $a->query("SELECT cancelled_by FROM bookings WHERE booking_id=$stale_status")->fetch_row()[0] === 2, 'stale cancellation preserves original actor');
	$db->trans_begin();
	check($model->cancel_single($nested_single), 'nested single cancellation succeeds');
	check($db->trans_rollback(), 'outer rollback after nested single cancellation succeeds');
	check((int) $a->query("SELECT status FROM bookings WHERE booking_id=$nested_single")->fetch_row()[0] === 10, 'outer rollback restores nested single cancellation');
	$a->query("INSERT INTO bookings (session_id,period_id,room_id,user_id,capacity,date,status,created_by) VALUES (1,1,1,1,1,'2000-01-01',10,1)");
	$past = (int) $a->insert_id;
	check( ! $model->cancel_single($past), 'owner cannot cancel a past booking');
	$allow_cancel_other = TRUE;
	check( ! $model->cancel_single($past), 'room permission cannot cancel a past booking');
	$allow_cancel_global = TRUE;
	check($model->cancel_single($past), 'global cancellation permission can cancel a past booking');
	$allow_cancel_global = FALSE;
	$allow_cancel_other = FALSE;
	$a->query("INSERT INTO bookings (repeat_id,session_id,period_id,room_id,user_id,capacity,date,status,created_by) VALUES (99,1,1,1,2,1,'2030-03-01',10,2), (NULL,1,1,2,2,1,'2030-03-01',10,2)");
	$recurring_other = (int) $a->query("SELECT booking_id FROM bookings WHERE repeat_id=99")->fetch_row()[0];
	$other_room = (int) $a->query("SELECT booking_id FROM bookings WHERE room_id=2 AND date='2030-03-01'")->fetch_row()[0];
	$allow_cancel_other = TRUE;
	$cancel_types = [Permission::BK_SGL_CANCEL_OTHER];
	check( ! $model->cancel_single($recurring_other), 'single permission does not authorize recurring instance');
	check( ! $model->cancel_single($other_room), 'room permission does not authorize a different room');
	$cancel_types = [Permission::BK_RECUR_CANCEL_OTHER];
	check($model->cancel_single($recurring_other), 'recurring permission authorizes its instance');
	$cancel_types = [Permission::BK_SGL_CANCEL_OTHER, Permission::BK_RECUR_CANCEL_OTHER];
	$allow_cancel_other = FALSE;

	$a->query('DELETE FROM bookings');
	$a->query("INSERT INTO bookings_repeat (repeat_id, session_id, room_id, period_id, user_id, notes) VALUES (7,1,1,1,1,'original')");
	$a->query("INSERT INTO bookings (repeat_id,session_id,period_id,room_id,user_id,capacity,date,status,created_by,notes) VALUES (7,1,1,1,1,1,'2030-01-01',10,1,'original'), (7,1,1,1,2,1,'2030-01-08',10,2,'other')");
	$id = (int) $a->query('SELECT MIN(booking_id) FROM bookings')->fetch_row()[0];
	check( ! $model->update($id, ['notes' => 'changed'], 'all'), 'series edit checks every owner');
	check( ! $model->cancel_all($id), 'series cancellation checks every owner');
	$allow_edit_other = TRUE;
	$allow_cancel_other = TRUE;
	$permissions = [Permission::BK_RECUR_VIEW_OTHER_NOTES, Permission::BK_RECUR_VIEW_OTHER_USERS, Permission::BK_RECUR_SET_USER];
	$db->fail_repeat_update = TRUE;
	check( ! $model->update($id, ['notes' => 'changed'], 'all'), 'repeat metadata failure reported');
	check($a->query("SELECT notes FROM bookings WHERE booking_id=$id")->fetch_row()[0] === 'original', 'repeat metadata failure rolls back instances');
	$db->fail_repeat_update = FALSE;
	check($model->update($id, ['notes' => NULL], 'all'), 'series update succeeds');
	check($a->query('SELECT notes FROM bookings_repeat WHERE repeat_id=7')->fetch_row()[0] === NULL, 'series metadata preserves explicit null');
	$a->query("INSERT INTO bookings (session_id,period_id,room_id,user_id,capacity,date,status,created_by) VALUES (1,1,1,3,1,'2030-01-08',10,3)");
	check( ! $model->update($id, ['user_id' => 3], 'all'), 'series duplicate identity rejected');
	check((int) $a->query("SELECT user_id FROM bookings WHERE booking_id=$id")->fetch_row()[0] === 1, 'series conflict rolls back all instances');

	$allow_edit_other = FALSE;
	$allow_cancel_other = FALSE;
	$existing = (int) $a->query('SELECT MAX(booking_id) FROM bookings')->fetch_row()[0];
	$input = ['session_id' => 1, 'period_id' => 1, 'room_id' => 1, 'user_id' => 1, 'capacity' => 1, 'dates' => ['2030-01-08' => ['action' => 'replace', 'replace_booking_id' => $existing]]];
	check( ! $repeat->create($input), 'unauthorized recurring replacement rejected');
	check((int) $a->query("SELECT status FROM bookings WHERE booking_id=$existing")->fetch_row()[0] === 10, 'rejected replacement leaves booking active');
	$input['dates'] = ['2030-01-09' => ['action' => 'replace', 'replace_booking_id' => $existing]];
	$allow_edit_other = TRUE;
	$allow_cancel_other = TRUE;
	check( ! $repeat->create($input), 'replacement must match requested slot');
	$input['capacity'] = 2;
	$input['dates'] = ['2030-02-01' => ['action' => 'book'], '2030-01-08' => ['action' => 'book']];
	$before = (int) $a->query('SELECT COUNT(*) FROM bookings_repeat')->fetch_row()[0];
	check( ! $repeat->create($input), 'recurring creation fails on later conflict');
	check((int) $a->query('SELECT COUNT(*) FROM bookings_repeat')->fetch_row()[0] === $before
		&& (int) $a->query("SELECT COUNT(*) FROM bookings WHERE date='2030-02-01'")->fetch_row()[0] === 0, 'later conflict rolls back earlier instance and repeat');

	$a->query("INSERT INTO bookings (session_id,period_id,room_id,user_id,capacity,date,status,created_by) VALUES (1,1,1,1,1,'2031-01-01',10,1), (1,1,1,1,1,'2031-01-08',10,1)");
	$replacement_id = (int) $a->query("SELECT booking_id FROM bookings WHERE date='2031-01-01'")->fetch_row()[0];
	$replacement = ['session_id' => 1, 'period_id' => 1, 'room_id' => 1, 'user_id' => 1, 'capacity' => 1,
		'dates' => ['2031-01-01' => ['action' => 'replace', 'replace_booking_id' => $replacement_id], '2031-01-08' => ['action' => 'book']]];
	$before = (int) $a->query('SELECT COUNT(*) FROM bookings_repeat')->fetch_row()[0];
	$cancel_count = $db->cancel_updates; $insert_count = $db->booking_inserts;
	check( ! $repeat->create($replacement), 'replacement followed by conflict fails');
	check($db->cancel_updates === $cancel_count + 1 && $db->booking_inserts === $insert_count + 1, 'replacement and insertion occurred before later conflict');
	check((int) $a->query("SELECT status FROM bookings WHERE booking_id=$replacement_id")->fetch_row()[0] === 10
		&& (int) $a->query('SELECT COUNT(*) FROM bookings_repeat')->fetch_row()[0] === $before
		&& (int) $a->query("SELECT COUNT(*) FROM bookings WHERE date='2031-01-01'")->fetch_row()[0] === 1, 'failed replacement restores original and removes inserted rows');

	$db->trans_begin();
	$a->query('UPDATE rooms SET capacity=4 WHERE room_id=2');
	$cancel_count = $db->cancel_updates; $insert_count = $db->booking_inserts;
	check( ! $repeat->create($replacement), 'nested replacement followed by conflict fails');
	check($db->cancel_updates === $cancel_count + 1 && $db->booking_inserts === $insert_count + 1, 'nested replacement and insertion occurred before conflict');
	check($db->trans_commit(), 'outer transaction can commit after nested replacement failure');
	check((int) $a->query('SELECT capacity FROM rooms WHERE room_id=2')->fetch_row()[0] === 4
		&& (int) $a->query("SELECT status FROM bookings WHERE booking_id=$replacement_id")->fetch_row()[0] === 10
		&& (int) $a->query('SELECT COUNT(*) FROM bookings_repeat')->fetch_row()[0] === $before
		&& (int) $a->query("SELECT COUNT(*) FROM bookings WHERE date='2031-01-01'")->fetch_row()[0] === 1, 'savepoint restores nested replacement without discarding outer work');

	$replacement['dates'] = ['2031-01-01' => ['action' => 'replace', 'replace_booking_id' => $replacement_id]];
	$db->trans_begin();
	$db->throw_booking_insert = TRUE;
	$cancel_count = $db->cancel_updates;
	try { $result = $repeat->create($replacement); throw new RuntimeException('replacement returned ' . var_export($result, TRUE) . '; model error: ' . var_export($model->get_error(), TRUE) . '; db error: ' . var_export($db->last_error, TRUE)); }
	catch (RuntimeException $e) { if ($e->getMessage() !== 'injected insert exception') throw $e; check(TRUE, 'injected exception propagates'); }
	check($db->cancel_updates === $cancel_count + 1, 'replacement was cancelled before injected exception');
	$db->throw_booking_insert = FALSE;
	check($db->trans_commit(), 'nested exception leaves transaction depth balanced');
	check((int) $a->query("SELECT status FROM bookings WHERE booking_id=$replacement_id")->fetch_row()[0] === 10
		&& (int) $a->query('SELECT COUNT(*) FROM bookings_repeat')->fetch_row()[0] === $before, 'nested exception restores cancelled booking and series');

	$db->trans_begin();
	check((bool) $repeat->create($replacement), 'nested replacement succeeds');
	check((int) $a->query("SELECT status FROM bookings WHERE booking_id=$replacement_id")->fetch_row()[0] === 15, 'replacement cancels original inside outer transaction');
	check($db->trans_rollback(), 'outer rollback after successful replacement succeeds');
	check((int) $a->query("SELECT status FROM bookings WHERE booking_id=$replacement_id")->fetch_row()[0] === 10
		&& (int) $a->query('SELECT COUNT(*) FROM bookings_repeat')->fetch_row()[0] === $before, 'outer rollback restores successful nested replacement');
	$db->fail_commit = TRUE;
	check( ! $repeat->create($replacement), 'replacement commit failure reported');
	$db->fail_commit = FALSE;
	check((int) $a->query("SELECT status FROM bookings WHERE booking_id=$replacement_id")->fetch_row()[0] === 10
		&& (int) $a->query('SELECT COUNT(*) FROM bookings_repeat')->fetch_row()[0] === $before, 'failed replacement commit rolls back original and series');
	$replacement_repeat_id = $repeat->create($replacement);
	check((bool) $replacement_repeat_id, 'replacement commits successfully');
	check((int) $a->query("SELECT status FROM bookings WHERE booking_id=$replacement_id")->fetch_row()[0] === 15
		&& (int) $a->query('SELECT COUNT(*) FROM bookings WHERE repeat_id=' . (int) $replacement_repeat_id . ' AND status=10')->fetch_row()[0] === 1, 'committed replacement contains active instance and cancelled original');
	$db->fail_repeat_update = TRUE;
	check( ! $model->cancel_all($id), 'series cancellation metadata failure reported');
	check((int) $a->query("SELECT status FROM bookings WHERE booking_id=$id")->fetch_row()[0] === 10, 'series cancellation failure rolls back instances');
	$db->fail_repeat_update = FALSE;
	check($model->cancel_all($id), 'series cancellation succeeds atomically');
	check((int) $a->query('SELECT status FROM bookings_repeat WHERE repeat_id=7')->fetch_row()[0] === 15, 'series cancellation updates metadata');

	// Cancel-all can succeed while leaving past occurrences intact for a non-global user.
	$allow_cancel_other = FALSE;
	$allow_cancel_global = FALSE;
	$yesterday = date('Y-m-d', strtotime('-1 day'));
	$tomorrow = date('Y-m-d', strtotime('+1 day'));
	$a->query("INSERT INTO bookings_repeat (repeat_id, session_id, room_id, period_id, user_id) VALUES (100,1,1,1,1), (101,1,1,1,1), (102,1,1,1,1)");
	$a->query("INSERT INTO bookings (repeat_id,session_id,period_id,room_id,user_id,capacity,date,status,created_by) VALUES
		(100,1,1,1,1,1,'$yesterday',10,1), (100,1,1,1,1,1,'$tomorrow',10,1),
		(101,1,1,1,1,1,'$yesterday',10,1), (101,1,1,1,1,1,'$tomorrow',10,1),
		(102,1,1,1,2,1,'$yesterday',10,2), (102,1,1,1,1,1,'$tomorrow',10,1)");
	$selected = (int) $a->query("SELECT booking_id FROM bookings WHERE repeat_id=100 AND date='$tomorrow'")->fetch_row()[0];
	check($model->cancel_all($selected), 'cancel-all succeeds when an owned past occurrence is skipped');
	check((int) $a->query("SELECT status FROM bookings WHERE repeat_id=100 AND date='$yesterday'")->fetch_row()[0] === 10
		&& (int) $a->query("SELECT status FROM bookings WHERE repeat_id=100 AND date='$tomorrow'")->fetch_row()[0] === 15, 'cancel-all leaves the past occurrence active and cancels the selected occurrence');
	$selected = (int) $a->query("SELECT booking_id FROM bookings WHERE repeat_id=102 AND date='$tomorrow'")->fetch_row()[0];
	check( ! $model->cancel_all($selected), 'cancel-all rejects an unauthorized past occurrence rather than silently skipping it');
	check((int) $a->query("SELECT status FROM bookings WHERE booking_id=$selected")->fetch_row()[0] === 10, 'unauthorized series failure leaves the selected booking active');
	$allow_cancel_global = TRUE;
	$selected = (int) $a->query("SELECT booking_id FROM bookings WHERE repeat_id=101 AND date='$tomorrow'")->fetch_row()[0];
	check($model->cancel_all($selected), 'global permission allows cancel-all to include past occurrences');
	check((int) $a->query('SELECT COUNT(*) FROM bookings WHERE repeat_id=101 AND status=10')->fetch_row()[0] === 0, 'global cancel-all cancels both past and future occurrences');
	echo "All booking reliability tests passed.\n";
} finally {
	$admin->query("DROP DATABASE `$schema`");
}