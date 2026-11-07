<?php
// Behavioral tests for grid quota gating and minimum-only multi-booking constraints.
define('BASEPATH', __DIR__);
class Permission { const BK_SGL_CREATE = 'single', BK_RECUR_CREATE = 'recurring', BK_SGL_SET_USER = 'user', BK_SGL_SET_DEPT = 'dept', BK_RECUR_SET_USER = 'recur.user', BK_RECUR_SET_DEPT = 'recur.dept'; }
class CI_Model { public $db, $load, $users_model, $sessions_model, $dates_model; }
class Bookings_model { const STATUS_BOOKED = 10; }
function has_permission($key, $room = null) { return $GLOBALS['permissions'][$key] ?? false; }
function lang($key) { return $key; }
function datetime_from_string($date) { return new DateTime($date); }
function nest_object_keys($row) { return $row; }
function &get_instance() { return $GLOBALS['ci']; }
require __DIR__ . '/../crbs-core/application/components/bookings/Context.php';
require __DIR__ . '/../crbs-core/application/components/bookings/Slot.php';
require __DIR__ . '/../crbs-core/application/models/Multi_booking_model.php';
class Users { public $constraints = ['max_active_bookings' => 1, 'range_min' => null, 'range_max' => null]; function get_constraints($id) { return $this->constraints; } function get_scheduled_booking_count($id) { return 1; } }
class Loader { function model($models) {} }
class Query { public $rows; function result() { return $this->rows; } }
class Db { public $rows; function __call($method, $args) { return $this; } function get() { $q = new Query(); $q->rows = $this->rows; return $q; } }
class Dates { function get_recurring_dates(...$args) { return []; } }
class Sessions { public $session; function get($id) { return $this->session; } }
function set_private($object, $name, $value) { $p = new ReflectionProperty($object, $name); $p->setAccessible(true); $p->setValue($object, $value); }
function check_case($condition, $name) { if (!$condition) throw new RuntimeException($name); echo "PASS $name\n"; }
$today = (new DateTime())->setTime(0, 0); $future = (clone $today)->modify('+10 days');
$users = new Users(); $GLOBALS['ci'] = (object) ['users_model' => $users];
$context = (new ReflectionClass(app\components\bookings\Context::class))->newInstanceWithoutConstructor();
$session = (object) ['date_start' => (clone $today)->modify('-30 days'), 'date_end' => (clone $today)->modify('+30 days'), 'is_current' => 1];
foreach (['user' => (object) ['user_id' => 7], 'today' => $today, 'session' => $session, 'bookings' => [], 'holidays' => []] as $key => $value) set_private($context, $key, $value);
$period = (object) ['period_id' => 2, 'time_start' => '23:59:59']; foreach (range(1, 7) as $day) $period->{'day_'.$day} = 1;
$room = (object) ['room_id' => 1, 'capacity' => 2];
$make = function($date) use ($context, $period, $room) { return new app\components\bookings\Slot($context, (object) ['date' => $date->format('Y-m-d'), 'weekday' => (int) $date->format('N'), 'holiday_id' => null], $period, $room); };
$GLOBALS['permissions'] = [Permission::BK_SGL_CREATE => true, Permission::BK_RECUR_CREATE => false, Permission::BK_SGL_SET_USER => false];
check_case($make($future)->reason === app\components\bookings\Slot::UNAVAILABLE_LIMIT, 'quota blocks scheduled self-only single');
$key = app\components\bookings\Slot::generate_key($future->format('Y-m-d'), 2, 1);
set_private($context, 'bookings', [$key => [(object) ['booking_id' => 3, 'room_id' => 1, 'user_id' => 7, 'capacity' => 1, 'repeat_id' => null]]]);
check_case($make($future)->status === app\components\bookings\Slot::STATUS_AVAILABLE, 'partially occupied slot remains accessible at quota');
set_private($context, 'bookings', []);
$GLOBALS['permissions'][Permission::BK_RECUR_CREATE] = true;
check_case($make($future)->status === app\components\bookings\Slot::STATUS_AVAILABLE, 'recurring remains available at single quota');
$GLOBALS['permissions'][Permission::BK_RECUR_CREATE] = false; $GLOBALS['permissions'][Permission::BK_SGL_SET_USER] = true;
check_case($make($future)->status === app\components\bookings\Slot::STATUS_AVAILABLE, 'delegated single remains available at quota');
$GLOBALS['permissions'][Permission::BK_SGL_SET_USER] = false; $GLOBALS['permissions'][Permission::BK_SGL_CREATE] = true;
check_case($make((clone $today)->modify('-1 day'))->status === app\components\bookings\Slot::STATUS_AVAILABLE, 'past single remains available when globally permitted');
$db = new Db(); $db->rows = [(object) ['date' => $future->format('Y-m-d'), 'period_id' => 2, 'room_id' => 1, 'weekday' => 1, 'room' => (object) ['capacity' => 2], 'booked_capacity' => 0]];
$model = (new ReflectionClass(Multi_booking_model::class))->newInstanceWithoutConstructor();
$model->db = $db; $model->load = new Loader(); $model->users_model = $users; $model->dates_model = new Dates(); $model->sessions_model = new Sessions(); $model->sessions_model->session = $session;
$users->constraints['range_min'] = 2; $users->constraints['range_max'] = null;
$slots = $model->get_slots((object) ['mb_id' => 1, 'user_id' => 7, 'session_id' => 5, 'week_id' => 1]);
check_case(reset($slots)->capabilities['single.create'], 'minimum-only multibooking resolves session and permits in-range date');
$users->constraints['range_min'] = 11;
$slots = $model->get_slots((object) ['mb_id' => 1, 'user_id' => 7, 'session_id' => 5, 'week_id' => 1]);
check_case(!reset($slots)->capabilities['single.create'], 'minimum-only multibooking rejects date before minimum');
echo "All grid and multibooking tests passed.\n";
