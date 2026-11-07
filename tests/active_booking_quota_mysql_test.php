<?php
// Standalone MySQL integration test. BOOKING_TEST_MYSQL_* selects an account
// permitted to create and drop a temporary database.
define('BASEPATH', __DIR__);
class CI_Model { public $db; }
require __DIR__ . '/../crbs-core/application/models/Users_model.php';
class QuotaResult {
    private $rows;
    function __construct($result) { $this->rows = $result->fetch_all(MYSQLI_ASSOC); }
    function row() { return isset($this->rows[0]) ? (object) $this->rows[0] : null; }
    function row_array() { return $this->rows[0] ?? null; }
    function result_array() { return $this->rows; }
    function num_rows() { return count($this->rows); }
}
class QuotaDb {
    private $connection;
    public $fail_matching = null;
    function __construct($connection) { $this->connection = $connection; }
    function query($sql, $bindings = []) {
        if ($this->fail_matching !== null && strpos($sql, $this->fail_matching) !== false) return false;
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);
        return new QuotaResult($stmt->get_result());
    }
}
class QuotaUsers extends Users_model {
    function __construct($connection) { $this->db = new QuotaDb($connection); }
}
function check_quota($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS $message\n";
}
$host = getenv('BOOKING_TEST_MYSQL_HOST') ?: '127.0.0.1';
$user = getenv('BOOKING_TEST_MYSQL_USER') ?: 'root';
$password = getenv('BOOKING_TEST_MYSQL_PASSWORD') ?: '';
$schema = 'booking_quota_test_' . bin2hex(random_bytes(6));
$admin = new mysqli($host, $user, $password);
$admin->query("CREATE DATABASE `$schema`");
try {
    $a = new mysqli($host, $user, $password, $schema);
    $b = new mysqli($host, $user, $password, $schema);
    $a->query('CREATE TABLE users (user_id INT PRIMARY KEY) ENGINE=InnoDB');
    $a->query('CREATE TABLE rooms (room_id INT PRIMARY KEY) ENGINE=InnoDB');
    $a->query('CREATE TABLE periods (period_id INT PRIMARY KEY, time_start TIME) ENGINE=InnoDB');
    $a->query('CREATE TABLE bookings_repeat (repeat_id INT PRIMARY KEY) ENGINE=InnoDB');
    $a->query('CREATE TABLE bookings (booking_id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NULL, created_by INT NULL, status INT NOT NULL, date DATE NOT NULL, repeat_id INT NULL, period_id INT NOT NULL, room_id INT NOT NULL,
        KEY idx_bookings_date (date), KEY fk_bookings_user (user_id), KEY fk_bookings_created_user (created_by),
        CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_bookings_created_user FOREIGN KEY (created_by) REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_bookings_period FOREIGN KEY (period_id) REFERENCES periods (period_id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_bookings_room FOREIGN KEY (room_id) REFERENCES rooms (room_id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_bookings_repeat FOREIGN KEY (repeat_id) REFERENCES bookings_repeat (repeat_id) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB');
    $a->query('INSERT INTO users VALUES (1), (2)');
    $a->query('INSERT INTO rooms VALUES (1), (2)');
    $a->query('INSERT INTO bookings_repeat VALUES (7)');
    $now = date('H:i') . ':00';
    $a->query("INSERT INTO periods VALUES (1, '12:00:00'), (2, '23:59:59'), (3, '00:00:00'), (4, '$now')");
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $a->query("INSERT INTO bookings (user_id,created_by,status,date,repeat_id,period_id,room_id) VALUES
        (1,1,10,'$tomorrow',NULL,1,1), (1,2,10,'$tomorrow',NULL,1,1),
        (2,1,10,'$tomorrow',NULL,1,1), (1,1,10,'$tomorrow',7,1,1),
        (1,1,10,'$yesterday',NULL,1,1), (1,1,15,'$tomorrow',NULL,1,1),
        (1,1,10,'$today',NULL,2,1), (1,1,10,'$today',NULL,3,1),
        (1,1,10,'$today',NULL,4,1)");
    $users = new QuotaUsers($a);
    $users2 = new QuotaUsers($b);
    check_quota($users->get_scheduled_booking_count(1) === '2', 'display count uses documented predicates');
    $rows = [
        ['user_id' => 1, 'created_by' => 1, 'date' => $tomorrow, 'period_id' => 1],
        ['user_id' => 2, 'created_by' => 1, 'date' => $tomorrow, 'period_id' => 1],
        ['user_id' => 1, 'created_by' => 2, 'date' => $tomorrow, 'period_id' => 1],
        ['user_id' => 1, 'created_by' => 1, 'date' => $tomorrow, 'period_id' => 1, 'repeat_id' => 7],
        ['user_id' => 1, 'created_by' => 1, 'date' => $yesterday, 'period_id' => 1],
        ['user_id' => 1, 'created_by' => 1, 'date' => $today, 'period_id' => 2],
        ['user_id' => 1, 'created_by' => 1, 'date' => $today, 'period_id' => 3],
        ['user_id' => 1, 'created_by' => 1, 'date' => $today, 'period_id' => 4],
        ['user_id' => 1, 'created_by' => 1, 'date' => $tomorrow, 'period_id' => 1, 'status' => 15],
    ];
    $a->begin_transaction();
    check_quota($users->lock_scheduled_booking_quota(1, $rows) === ['count' => 2, 'requested' => 2], 'current count and mixed delta exclude other users, creators, recurring, past, current-time and cancelled');
    check_quota($users->lock_scheduled_booking_quota(1, [$rows[0]], [$rows[4]]) === ['count' => 2, 'requested' => 1], 'moving a past self booking into the future increases quota');
    check_quota($users->lock_scheduled_booking_quota(1, [$rows[4]], [$rows[0]]) === ['count' => 2, 'requested' => -1], 'moving a self booking into the past decreases quota');
    check_quota($users->lock_scheduled_booking_quota(1, [$rows[0]], [$rows[0]]) === ['count' => 2, 'requested' => 0], 'moving an already scheduled self booking is quota neutral');
    check_quota($users->lock_scheduled_booking_quota(1, [$rows[0]], [$rows[1]]) === ['count' => 2, 'requested' => 1], 'reassigning the creator booking to its creator increases quota');
    check_quota($users->lock_scheduled_booking_quota(1, [['user_id' => 1, 'created_by' => 1, 'date' => $today, 'period_id' => 999]]) === false, 'missing proposed period fails closed');
    $users->db->fail_matching = 'FROM bookings FORCE INDEX';
    check_quota($users->lock_scheduled_booking_quota(1, []) === false, 'booking current read error fails closed');
    $users->db->fail_matching = 'SELECT time_start FROM periods';
    check_quota($users->lock_scheduled_booking_quota(1, []) === false, 'period read error fails closed');
    $users->db->fail_matching = null;
    $b->begin_transaction();
    $b->query("UPDATE periods SET time_start='23:58:00' WHERE period_id=2", MYSQLI_ASYNC);
    $read = [$b]; $errors = []; $reject = [];
    check_quota(mysqli_poll($read, $errors, $reject, 3) === 1 && (bool) $b->reap_async_query(), 'today quota reads do not lock period rows');
    $b->rollback();
    $a->rollback();
    $a->query('DELETE FROM bookings');
    $b->begin_transaction();
    $b->query('SELECT * FROM bookings')->fetch_all(); // Establish an old snapshot.
    $a->begin_transaction();
    check_quota($users->lock_scheduled_booking_quota(1, [])['count'] === 0, 'quota read ignores cached display count');
    $a->query("INSERT INTO bookings (user_id,created_by,status,date,repeat_id,period_id,room_id) VALUES (1,1,10,'$tomorrow',NULL,1,1)");
    $b->query('SELECT user_id FROM users WHERE user_id = 1 FOR UPDATE', MYSQLI_ASYNC);
    $read = [$b]; $errors = []; $reject = [];
    check_quota(mysqli_poll($read, $errors, $reject, 0, 100000) === 0, 'same-user quota lock waits');
    $a->commit();
    $read = [$b]; $errors = []; $reject = [];
    check_quota(mysqli_poll($read, $errors, $reject, 5) === 1 && (bool) $b->reap_async_query(), 'waiting quota lock resumes after commit');
    $quota = $users2->lock_scheduled_booking_quota(1, [$rows[0]]);
    check_quota($quota === ['count' => 1, 'requested' => 1] && $quota['count'] + $quota['requested'] > 1, 'current read sees committed booking despite stale snapshot and exceeds limit');
    $b->rollback();

    $a->query('DELETE FROM bookings');
    $b->begin_transaction();
    $b->query('SELECT * FROM periods')->fetch_all(); // Repeatable-read snapshot.
    $a->query("INSERT INTO bookings (user_id,created_by,status,date,repeat_id,period_id,room_id) VALUES (1,1,10,'$today',NULL,2,1)");
    $a->query("UPDATE periods SET time_start='00:00:00' WHERE period_id=2");
    check_quota($users2->lock_scheduled_booking_quota(1, [$rows[5]], [$rows[5]]) === ['count' => 1, 'requested' => 0], 'current today booking uses the same period snapshot as both delta sides');
    $b->rollback();
    $b->begin_transaction();
    check_quota($users2->lock_scheduled_booking_quota(1, [$rows[5]]) === ['count' => 0, 'requested' => 0], 'new transaction observes changed period time');
    $b->rollback();
    $a->query("UPDATE periods SET time_start='23:59:59' WHERE period_id=2");

    // Both users edit in opposite directions. The quota calls run while both
    // transactions are open, before the normal ascending room-lock order.
    // A joined quota read would leave each transaction holding its old room,
    // and A's request for room 2 would wait on B (the original deadlock).
    $a->query('DELETE FROM bookings');
    $a->query("INSERT INTO bookings (user_id,created_by,status,date,repeat_id,period_id,room_id) VALUES
        (1,1,10,'$tomorrow',NULL,1,1), (2,2,10,'$tomorrow',NULL,2,2)");
    $ids = $a->query('SELECT booking_id, user_id FROM bookings ORDER BY user_id')->fetch_all(MYSQLI_ASSOC);
    $id1 = (int) $ids[0]['booking_id'];
    $id2 = (int) $ids[1]['booking_id'];
    $old1 = ['user_id' => 1, 'created_by' => 1, 'date' => $tomorrow, 'period_id' => 1, 'room_id' => 1];
    $old2 = ['user_id' => 2, 'created_by' => 2, 'date' => $tomorrow, 'period_id' => 2, 'room_id' => 2];
    $new1 = array_merge($old1, ['room_id' => 2]);
    $new2 = array_merge($old2, ['room_id' => 1]);
    $a->begin_transaction();
    $b->begin_transaction();
    check_quota($users->lock_scheduled_booking_quota(1, [$new1], [$old1]) === ['count' => 1, 'requested' => 0], 'first swap has neutral quota');
    check_quota($users2->lock_scheduled_booking_quota(2, [$new2], [$old2]) === ['count' => 1, 'requested' => 0], 'second user quota does not wait on first user');
    $a->query('SELECT room_id FROM rooms WHERE room_id=1 FOR UPDATE');
    $a->query('SELECT room_id FROM rooms WHERE room_id=2 FOR UPDATE', MYSQLI_ASYNC);
    $read = [$a]; $errors = []; $reject = [];
    check_quota(mysqli_poll($read, $errors, $reject, 3) === 1 && (bool) $a->reap_async_query(), 'first swap acquires second room despite other quota read');
    $b->query('SELECT room_id FROM rooms WHERE room_id=1 FOR UPDATE', MYSQLI_ASYNC);
    $read = [$b]; $errors = []; $reject = [];
    check_quota(mysqli_poll($read, $errors, $reject, 0, 100000) === 0, 'second swap waits for ordered room lock');
    $a->query("UPDATE bookings SET room_id=2 WHERE booking_id=$id1");
    $a->commit();
    $read = [$b]; $errors = []; $reject = [];
    check_quota(mysqli_poll($read, $errors, $reject, 3) === 1 && (bool) $b->reap_async_query(), 'second swap resumes without deadlock');
    $b->query('SELECT room_id FROM rooms WHERE room_id=2 FOR UPDATE');
    $b->query("UPDATE bookings SET room_id=1 WHERE booking_id=$id2");
    $b->commit();
    check_quota((int) $a->query("SELECT room_id FROM bookings WHERE booking_id=$id1")->fetch_row()[0] === 2
        && (int) $a->query("SELECT room_id FROM bookings WHERE booking_id=$id2")->fetch_row()[0] === 1, 'both cross-room edits commit');
    $a->begin_transaction();
    check_quota($users->lock_scheduled_booking_quota(1, [$new1], [$old1]) === ['count' => 1, 'requested' => 0], 'first user remains within quota after swap');
    $a->rollback();
    $b->begin_transaction();
    check_quota($users2->lock_scheduled_booking_quota(2, [$new2], [$old2]) === ['count' => 1, 'requested' => 0], 'second user remains within quota after swap');
    $b->rollback();
    echo "All active booking quota MySQL tests passed.\n";
} finally {
    $admin->query("DROP DATABASE `$schema`");
}
