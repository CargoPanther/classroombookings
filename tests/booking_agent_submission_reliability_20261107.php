<?php
// Standalone regression tests for booking-agent submission guards; no database required.
define('BASEPATH', __DIR__);
class Permission {
    const BK_SGL_CREATE = 'single.create', BK_RECUR_CREATE = 'recur.create';
    const BK_SGL_SET_USER = 'single.user', BK_SGL_SET_DEPT = 'single.dept';
    const BK_RECUR_SET_USER = 'recur.user', BK_RECUR_SET_DEPT = 'recur.dept';
    const BK_SGL_CANCEL_OTHER = 'single.cancel', BK_RECUR_CANCEL_OTHER = 'recur.cancel';
}
class Bookings_model { const STATUS_BOOKED = 10; }
function lang($key) { return $key; }
function has_permission($permission, $room = null) { return $GLOBALS['permissions'][$permission][$room] ?? false; }
function datetime_from_string($value) {
    if ($value instanceof DateTime) return $value;
    if (!is_string($value) || !$value) return false;
    try { return new DateTime($value); } catch (Throwable $e) { return false; }
}
function date_output_long($date) { return $date->format('Y-m-d'); }
require __DIR__ . '/../crbs-core/application/components/bookings/agent/BaseAgent.php';
require __DIR__ . '/../crbs-core/application/components/bookings/agent/SingleAgent.php';
require __DIR__ . '/../crbs-core/application/components/bookings/agent/MultiAgent.php';
require __DIR__ . '/../crbs-core/application/components/bookings/Slot.php';

class FakeInput {
    public array $data = [];
    function post($key = null) { return $key === null ? $this->data : ($this->data[$key] ?? null); }
}
class FakeUsers {
    public array $constraints = ['max_active_bookings' => null, 'range_min' => null, 'range_max' => null, 'recur_max_instances' => null];
    public int $count = 0;
    public $lockedCount = null;
    public array $quotaRows = [];
    public bool $failLock = false;
    function get_constraints($id) { return $this->constraints; }
    function get_scheduled_booking_count($id) { return $this->count; }
    function lock_scheduled_booking_quota($id, $rows) {
        $this->quotaRows = $rows;
        if ($this->failLock) return false;
        $requested = count(array_filter($rows, fn($row) => (int) ($row['user_id'] ?? 0) === $id
            && (int) ($row['created_by'] ?? 0) === $id && ($row['repeat_id'] ?? null) === null
            && ($row['date'] ?? '') > date('Y-m-d')));
        return ['count' => $this->lockedCount ?? $this->count, 'requested' => $requested];
    }
}
class FakeValidation {
    function set_rules($rules) {}
    function set_data($data) {}
    function reset_validation() {}
    function run() { return true; }
}
class FakeLoader { function library($name) {} }
class FakeDates { public $info; public array $byDate = []; function get_by_date($date) { return array_key_exists($date, $this->byDate) ? $this->byDate[$date] : $this->info; } }
class FakeRooms {
    public array $rooms = [];
    function get_bookable_rooms($params) { return $this->rooms[$params['room_id']] ?? false; }
}
class FakeSchedules { public $schedule; function get_applied_schedule($session, $group) { return $this->schedule; } }
class FakePeriods { public $period; function get($id) { return $id == 2 ? $this->period : false; } }
class FakeDB {
    public bool $commit = true;
    public bool $rolledBack = false;
    public int $depth = 0;
    function trans_active() { return $this->depth > 0; }
    function trans_start() { return true; }
    function trans_begin() { $this->depth++; return true; }
    function trans_rollback() { $this->rolledBack = true; $this->depth--; return true; }
    function trans_complete() { return $this->commit; }
    function trans_status() { return true; }
    function trans_commit() { if ($this->commit) $this->depth--; return $this->commit; }
}
class FakeBookings {
    public array $created = [], $existing = [];
    public bool $fail = false, $throw = false;
    function create($row) {
        if ($this->throw) throw new RuntimeException('injected booking failure');
        if ($this->fail) return false;
        $this->created[] = $row; return count($this->created);
    }
    function get_error() { return false; }
    function find_all_for_dates($dates, $period, $room) { return $this->existing; }
}
class FakeRepeats {
    public array $created = [];
    public bool $throw = false;
    public $throwAfter = null;
    function create($row) { if ($this->throw || ($this->throwAfter !== null && count($this->created) >= $this->throwAfter)) throw new RuntimeException('injected repeat failure'); $this->created[] = $row; return count($this->created); }
}
class FakeMulti { public bool $deleted = false; function delete($id) { $this->deleted = true; } }
function make_ci() {
    $ci = (object) ['input' => new FakeInput(), 'users_model' => new FakeUsers(), 'load' => new FakeLoader(),
        'form_validation' => new FakeValidation(), 'db' => new FakeDB(), 'bookings_model' => new FakeBookings(),
        'bookings_repeat_model' => new FakeRepeats(), 'multi_booking_model' => new FakeMulti(),
        'dates_model' => new FakeDates(), 'periods_model' => new FakePeriods(), 'rooms_model' => new FakeRooms(),
        'schedules_model' => new FakeSchedules()];
    $ci->dates_model->info = (object) ['session_id' => 1, 'week_id' => 3, 'weekday' => 1, 'holiday_id' => null];
    $ci->rooms_model->rooms = [1 => (object) ['room_id' => 1, 'room_group_id' => 3], 2 => (object) ['room_id' => 2, 'room_group_id' => 3]];
    $ci->schedules_model->schedule = (object) ['schedule_id' => 4];
    $ci->periods_model->period = (object) ['period_id' => 2, 'schedule_id' => 4, 'bookable' => 1];
    foreach (range(1, 7) as $weekday) $ci->periods_model->period->{'day_' . $weekday} = 1;
    return $ci;
}
function prop($object, $name, $value) {
    $class = new ReflectionObject($object);
    while (!$class->hasProperty($name)) $class = $class->getParentClass();
    $p = $class->getProperty($name); $p->setAccessible(true); $p->setValue($object, $value);
}
function invoke_private($object, $method, ...$args) {
    $m = new ReflectionMethod($object, $method); $m->setAccessible(true); return $m->invoke($object, ...$args);
}
function assert_case($condition, $name) { if (!$condition) throw new RuntimeException($name); echo "PASS $name\n"; }
function make_agent($class, $ci) {
    $agent = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    prop($agent, 'CI', $ci);
    prop($agent, 'user', (object) ['user_id' => 7, 'department_id' => 9]);
    return $agent;
}
$day = (new DateTime('tomorrow'))->setTime(0, 0);
$ymd = $day->format('Y-m-d');
$session = (object) ['session_id' => 1, 'date_start' => clone $day, 'date_end' => (clone $day)->modify('+1 month')];
$ci = make_ci();
$single = make_agent(app\components\bookings\agent\SingleAgent::class, $ci);
prop($single, 'room', (object) ['room_id' => 1, 'capacity' => 5]);
prop($single, 'period', (object) ['period_id' => 2]);
prop($single, 'datetime', $day);
prop($single, 'date_info', (object) ['week_id' => 3, 'weekday' => 1]);
prop($single, 'session', $session);
prop($single, 'department', (object) ['department_id' => 9]);
prop($single, 'booking_user', (object) ['user_id' => 7]);
prop($single, 'view_data', ['existing_bookings' => []]);
prop($single, 'recurring_dates', [(object) ['date' => $day]]);
$ci->input->data = ['date' => $ymd, 'capacity' => 1, 'recurring_start' => $ymd, 'recurring_end' => $ymd,
    'dates' => [$ymd => ['action' => 'book']], 'user_id' => 8, 'department_id' => 10];
$GLOBALS['permissions'] = [];
assert_case(invoke_private($single, 'create_single_booking') === false && !$ci->bookings_model->created, 'single create requires single permission');
$GLOBALS['permissions'][Permission::BK_SGL_CREATE][1] = true;
$ci->users_model->constraints['range_min'] = 5;
assert_case(invoke_private($single, 'create_single_booking') === false && !$ci->bookings_model->created, 'single create enforces date constraint');
$ci->users_model->constraints['range_min'] = null;
$ci->users_model->constraints['max_active_bookings'] = 1; $ci->users_model->count = 0; $ci->users_model->lockedCount = 2;
assert_case(invoke_private($single, 'create_single_booking') === false && !$ci->bookings_model->created, 'single create rejects over-limit count');
$GLOBALS['permissions'][Permission::BK_SGL_SET_USER][1] = true;
assert_case(invoke_private($single, 'create_single_booking') === true && $ci->bookings_model->created[0]['user_id'] === 8, 'single assigned to another user is quota exempt');
$GLOBALS['permissions'][Permission::BK_SGL_SET_USER][1] = false;
$ci->bookings_model->created = []; $ci->users_model->lockedCount = null; prop($single, 'success', false);
$ci->db->commit = false;
assert_case(invoke_private($single, 'create_single_booking') === false && !$single->is_success() && $ci->db->rolledBack, 'single failed commit rolls back and is not success');
$ci->db->commit = true; $ci->bookings_model->created = [];
$ci->users_model->constraints['max_active_bookings'] = null;
$ci->users_model->failLock = true; $ci->db->rolledBack = false;
assert_case(invoke_private($single, 'create_single_booking') === false && $ci->db->rolledBack && !$ci->bookings_model->created, 'single quota lock failure rolls back before writing');
$ci->users_model->failLock = false; $ci->bookings_model->fail = true; $ci->db->rolledBack = false;
assert_case(invoke_private($single, 'create_single_booking') === false && $ci->db->rolledBack, 'single model failure rolls back');
$ci->bookings_model->fail = false; $ci->bookings_model->throw = true; $ci->db->rolledBack = false;
try { invoke_private($single, 'create_single_booking'); assert_case(false, 'single exception rollback'); }
catch (RuntimeException $e) { assert_case($ci->db->rolledBack, 'single exception rolls back outer transaction'); }
$ci->bookings_model->throw = false;
assert_case(invoke_private($single, 'create_single_booking') === true && count($ci->bookings_model->created) === 1, 'authorized single creation succeeds');
$ci->db->depth = 1; $ci->db->rolledBack = false;
assert_case(invoke_private($single, 'create_single_booking') === false && $ci->db->depth === 1 && !$ci->db->rolledBack, 'single rejects an existing transaction without disturbing it');
$ci->db->depth = 0;
assert_case(invoke_private($single, 'create_single_recurring') === false && !$ci->bookings_repeat_model->created, 'recurring create requires recurring permission');
$GLOBALS['permissions'][Permission::BK_RECUR_CREATE][1] = true;
$GLOBALS['permissions'][Permission::BK_RECUR_SET_USER][1] = true;
$GLOBALS['permissions'][Permission::BK_RECUR_SET_DEPT][1] = true;
$ci->db->depth = 1;
assert_case(invoke_private($single, 'create_single_recurring') === false && $ci->db->depth === 1 && !$ci->bookings_repeat_model->created, 'single recurring rejects an existing transaction');
$ci->db->depth = 0;
$ci->users_model->constraints['max_active_bookings'] = 1; $ci->users_model->count = 1;
assert_case(invoke_private($single, 'create_single_recurring') === true && $ci->bookings_repeat_model->created[0]['user_id'] === 8 && $ci->bookings_repeat_model->created[0]['department_id'] === 10, 'recurring is quota exempt and uses recurring assignment permissions');
$ci->users_model->constraints['recur_max_instances'] = 0;
assert_case(invoke_private($single, 'create_single_recurring') === false && count($ci->bookings_repeat_model->created) === 1, 'single recurring still enforces instance limit');
$ci->users_model->constraints['recur_max_instances'] = null;
$key = app\components\bookings\Slot::generate_key($ymd, 2, 1);
$ci->bookings_model->existing[$key] = [(object) ['booking_id' => 20, 'room_id' => 1, 'user_id' => 7, 'capacity' => 1, 'repeat_id' => 1]];
$ci->input->data['dates'][$ymd] = ['action' => 'replace', 'replace_booking_id' => 20];
$ci->users_model->constraints['max_active_bookings'] = 1; $ci->users_model->count = 1;
assert_case(invoke_private($single, 'create_single_recurring') === true && count($ci->bookings_repeat_model->created) === 2, 'own replacement remains possible at active limit');

$ci = make_ci(); $multi = make_agent(app\components\bookings\agent\MultiAgent::class, $ci);
$ci->dates_model->info = (object) ['session_id' => 1, 'week_id' => 3, 'weekday' => 1, 'holiday_id' => null];
$slot = (object) ['mbs_id' => 11, 'date' => $ymd, 'period_id' => 2, 'room_id' => 1, 'weekday' => 1,
    'room' => (object) ['capacity' => 5], 'remaining_capacity' => 5, 'recurring_dates' => [(object) ['date' => $day]]];
$mb = (object) ['mb_id' => 4, 'session_id' => 1, 'week_id' => 3, 'slots' => [$slot]];
prop($multi, 'session', $session); prop($multi, 'multibooking', $mb); prop($multi, 'view_data', ['multibooking' => $mb]);
$ci->input->data = ['slot_single' => [11 => ['create' => 1, 'capacity' => 1]]];
$GLOBALS['permissions'][Permission::BK_SGL_CREATE][1] = true;
$ci->users_model->constraints['range_min'] = 5;
assert_case(invoke_private($multi, 'process_create_single') === false && !$ci->bookings_model->created, 'multi single rejects out-of-range slot');
$ci->users_model->constraints['range_min'] = null;
$ci->dates_model->info->session_id = 2;
assert_case(invoke_private($multi, 'process_create_single') === false && !$ci->bookings_model->created, 'multi single rejects another session date');
$ci->dates_model->info->session_id = 1; $ci->dates_model->info->holiday_id = 3;
assert_case(invoke_private($multi, 'process_create_single') === false && !$ci->bookings_model->created, 'multi single rejects holidays');
$ci->dates_model->info->holiday_id = null;
$ci->users_model->constraints['max_active_bookings'] = 1; $ci->users_model->count = 0; $ci->users_model->lockedCount = 2;
assert_case(invoke_private($multi, 'process_create_single') === false && !$ci->bookings_model->created, 'multi single rejects over-limit count');
$GLOBALS['permissions'][Permission::BK_SGL_SET_USER][1] = true;
$ci->input->data['slot_single'][11]['user_id'] = 8;
assert_case(invoke_private($multi, 'process_create_single') === true && $ci->bookings_model->created[0]['user_id'] == 8, 'multi single assigned to another user is quota exempt');
$GLOBALS['permissions'][Permission::BK_SGL_SET_USER][1] = false;
$ci->bookings_model->created = []; $ci->multi_booking_model->deleted = false; prop($multi, 'success', false);
$ci->users_model->lockedCount = null;
$ci->users_model->constraints['max_active_bookings'] = null; $ci->db->commit = false;
assert_case(invoke_private($multi, 'process_create_single') === false && !$ci->multi_booking_model->deleted && !$multi->is_success(), 'multi single failed commit is not success and retains selection');
$ci->db->commit = true;
$ci->db->depth = 1; $ci->db->rolledBack = false;
assert_case(invoke_private($multi, 'process_create_single') === false && $ci->db->depth === 1 && !$ci->db->rolledBack && !$ci->multi_booking_model->deleted, 'multi single rejects an existing transaction and retains selection');
$ci->db->depth = 0;
$_SESSION['mb_4_slots'] = [11 => ['recurring_start' => $ymd, 'recurring_end' => $ymd, 'capacity' => 1, 'user_id' => 8, 'department_id' => 10]];
$ci->input->data = ['dates' => [11 => [$ymd => ['action' => 'book']]]];
$GLOBALS['permissions'][Permission::BK_RECUR_CREATE][1] = false;
assert_case(invoke_private($multi, 'process_create_recurring') === false && !$ci->bookings_repeat_model->created && $ci->db->rolledBack, 'multi recurring rechecks each room permission');
$GLOBALS['permissions'][Permission::BK_RECUR_CREATE][1] = true;
$GLOBALS['permissions'][Permission::BK_RECUR_SET_USER][1] = false;
$GLOBALS['permissions'][Permission::BK_RECUR_SET_DEPT][1] = false;
$ci->db->depth = 1; $ci->db->rolledBack = false;
assert_case(invoke_private($multi, 'process_create_recurring') === false && $ci->db->depth === 1 && !$ci->db->rolledBack, 'multi recurring rejects an existing transaction');
$ci->db->depth = 0; $ci->bookings_repeat_model->throw = true;
try { invoke_private($multi, 'process_create_recurring'); assert_case(false, 'multi recurring exception rollback'); }
catch (RuntimeException $e) { assert_case($ci->db->rolledBack && $ci->db->depth === 0, 'multi recurring exception rolls back outer transaction'); }
$ci->bookings_repeat_model->throw = false;
$ci->db->commit = false;
assert_case(invoke_private($multi, 'process_create_recurring') === false && !$multi->is_success(), 'multi recurring failed commit is not success');
$record = end($ci->bookings_repeat_model->created);
assert_case($record['user_id'] === 7 && $record['department_id'] === 9, 'multi recurring rechecks assignment permissions');
$ci->db->commit = true; $ci->bookings_repeat_model->created = [];
$ci->users_model->constraints['max_active_bookings'] = 1; $ci->users_model->count = 1;
assert_case(invoke_private($multi, 'process_create_recurring') === true && count($ci->bookings_repeat_model->created) === 1, 'multi recurring is quota exempt');
$ci->bookings_repeat_model->created = []; prop($multi, 'success', false);
$ci->users_model->constraints['recur_max_instances'] = 0;
assert_case(invoke_private($multi, 'process_create_recurring') === false && !$ci->bookings_repeat_model->created, 'multi recurring still enforces instance limit');
$ci->users_model->constraints['recur_max_instances'] = null;
$ci->users_model->constraints['max_active_bookings'] = null;
$_SESSION['mb_4_slots'][11]['recurring_start'] = (clone $day)->modify('+1 day')->format('Y-m-d');
assert_case(invoke_private($multi, 'process_create_recurring') === false && !$ci->bookings_repeat_model->created, 'multi recurring rejects reversed range');
$_SESSION['mb_4_slots'][11]['recurring_start'] = $ymd;
$otherDay = (clone $day)->modify('+7 days');
$slot->recurring_dates[] = (object) ['date' => $otherDay];
$ci->input->data['dates'][11] = [$otherDay->format('Y-m-d') => ['action' => 'book']];
assert_case(invoke_private($multi, 'process_create_recurring') === false && !$ci->bookings_repeat_model->created, 'multi recurring rejects dates outside submitted bounds');
$slot2 = clone $slot; $slot2->mbs_id = 12; $slot2->room_id = 2;
$mb->slots[] = $slot2;
$_SESSION['mb_4_slots'][12] = $_SESSION['mb_4_slots'][11];
$ci->input->data['dates'] = [11 => [$ymd => ['action' => 'book']], 12 => [$ymd => ['action' => 'book']]];
$GLOBALS['permissions'][Permission::BK_RECUR_CREATE][2] = false;
$ci->db->rolledBack = false;
assert_case(invoke_private($multi, 'process_create_recurring') === false && $ci->db->rolledBack, 'multi recurring rolls back when another room is unauthorized');
$GLOBALS['permissions'][Permission::BK_RECUR_CREATE][2] = true;
$ci->bookings_repeat_model->created = [];
$ci->users_model->constraints['max_active_bookings'] = 1; $ci->users_model->count = 0;
$ci->db->rolledBack = false;
assert_case(invoke_private($multi, 'process_create_recurring') === true && count($ci->bookings_repeat_model->created) === 2, 'multiple recurring series are quota exempt');
$mb->slots = [$slot2, $slot]; $ci->bookings_repeat_model->created = []; $ci->bookings_repeat_model->throwAfter = 1; $ci->db->rolledBack = false; prop($multi, 'success', false);
try { invoke_private($multi, 'process_create_recurring'); assert_case(false, 'later recurring exception rollback'); }
catch (RuntimeException $e) { assert_case($ci->db->rolledBack && $ci->db->depth === 0 && !$multi->is_success() && $ci->bookings_repeat_model->created[0]['room_id'] === 1, 'later recurring exception rolls back earlier series and processes rooms in order'); }
$ci->bookings_repeat_model->throwAfter = null;

$ci = make_ci(); $multi = make_agent(app\components\bookings\agent\MultiAgent::class, $ci);
$past = (clone $day)->modify('-2 days');
$session2 = (object) ['session_id' => 1, 'date_start' => clone $past, 'date_end' => (clone $day)->modify('+1 month')];
$ci->dates_model->info = (object) ['session_id' => 1, 'week_id' => 3, 'weekday' => 1, 'holiday_id' => null];
$one = (object) ['mbs_id' => 21, 'date' => $ymd, 'period_id' => 2, 'room_id' => 1, 'remaining_capacity' => 5];
$two = clone $one; $two->mbs_id = 22;
$three = clone $one; $three->mbs_id = 23; $three->date = $past->format('Y-m-d');
$mb = (object) ['mb_id' => 5, 'session_id' => 1, 'slots' => [$one, $two, $three]];
prop($multi, 'session', $session2); prop($multi, 'view_data', ['multibooking' => $mb]);
$GLOBALS['permissions'][Permission::BK_SGL_SET_USER][1] = true;
$ci->input->data = ['slot_single' => [
    21 => ['create' => 1, 'capacity' => 1, 'user_id' => 7],
    22 => ['create' => 1, 'capacity' => 1, 'user_id' => 8],
    23 => ['create' => 1, 'capacity' => 1, 'user_id' => 7],
]];
$ci->users_model->constraints['max_active_bookings'] = 1;
assert_case(invoke_private($multi, 'process_create_single') === true && count($ci->bookings_model->created) === 3, 'mixed self, other and past rows consume exactly one place at the limit');
$ci->bookings_model->created = []; $ci->users_model->constraints['max_active_bookings'] = 0; $ci->db->rolledBack = false;
assert_case(invoke_private($multi, 'process_create_single') === false && !$ci->bookings_model->created && $ci->db->rolledBack, 'mixed rows exceeding limit are rejected atomically');
$ci->users_model->constraints['max_active_bookings'] = null; $ci->bookings_model->throw = true; $ci->db->rolledBack = false;
try { invoke_private($multi, 'process_create_single'); assert_case(false, 'multi exception rollback'); }
catch (RuntimeException $e) { assert_case($ci->db->rolledBack, 'multi exception rolls back outer transaction'); }
echo "All agent regression tests passed.\n";
