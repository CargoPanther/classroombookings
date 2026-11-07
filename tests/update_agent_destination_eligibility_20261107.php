<?php
// Standalone UpdateAgent destination regression tests; no database required.
define('BASEPATH', __DIR__);
class Permission {
	const BK_SGL_CREATE = 'single.create', BK_RECUR_CREATE = 'recur.create';
	const BK_SGL_VIEW_OTHER_USERS = 'single.view_user', BK_RECUR_VIEW_OTHER_USERS = 'recur.view_user';
	const BK_SGL_SET_USER = 'single.set_user', BK_RECUR_SET_USER = 'recur.set_user';
	const BK_SGL_SET_DEPT = 'single.set_dept', BK_RECUR_SET_DEPT = 'recur.set_dept';
	const BK_SGL_VIEW_OTHER_NOTES = 'single.view_notes', BK_RECUR_VIEW_OTHER_NOTES = 'recur.view_notes';
	const BK_SGL_EDIT_OTHER = 'single.edit_other', BK_RECUR_EDIT_OTHER = 'recur.edit_other';
}
function has_permission($permission, $room = null) { return $GLOBALS['permissions'][$permission][$room ?? 'global'] ?? false; }
function lang($key) { return $key; }
function datetime_from_string($date) {
	if ($date instanceof DateTimeInterface) return $date;
	try { return $date ? new DateTime($date) : false; } catch (Throwable $e) { return false; }
}
require __DIR__ . '/../crbs-core/application/components/bookings/agent/BaseAgent.php';
require __DIR__ . '/../crbs-core/application/components/bookings/agent/UpdateAgent.php';
use app\components\bookings\agent\UpdateAgent;

class FakeRooms {
	public $rooms = [];
	function get_bookable_rooms($params) { return $this->rooms[$params['room_id']] ?? false; }
}
class FakePeriods { public $periods = []; function get($id) { return $this->periods[$id] ?? false; } }
class FakeSchedules { public $schedule; function get_applied_schedule($session, $group) { return $this->schedule; } }
class FakeDates { public $info; function get_by_date($date) { return $this->info; } }
class FakeUsers {
	public $constraints = ['range_min' => null, 'range_max' => null, 'max_active_bookings' => null];
	public $quota = ['count' => 0, 'requested' => 0], $rows;
	function get_constraints($id) { return $this->constraints; }
	function lock_scheduled_booking_quota($id, $after, $before = []) { $this->rows = [$id, $after, $before]; return $this->quota; }
}
class FakeInput { public $data = []; function post($key = null) { return $key === null ? $this->data : ($this->data[$key] ?? null); } }
class FakeValidation { function set_rules($rules) {} function run() { return true; } }
class FakeLoader { function library($name) {} }
class FakeBookings {
	public $updates = [];
	public $throw = false, $db;
	function update(...$args) { $this->updates[] = $args; if ($this->throw) { $this->db->trans_begin(); throw new RuntimeException('nested failure'); } return true; }
	function get_error() { return false; }
}
class FakeDb {
	public $depth = 0, $commit = true, $rollbacks = 0;
	function trans_active() { return $this->depth > 0; }
	function trans_begin() { $this->depth++; return true; }
	function trans_status() { return true; }
	function trans_commit() { if (!$this->commit) return false; $this->depth--; return true; }
	function trans_rollback() { $this->depth--; $this->rollbacks++; return true; }
}
function set_prop($object, $name, $value) {
	$class = new ReflectionObject($object);
	while (!$class->hasProperty($name)) $class = $class->getParentClass();
	$property = $class->getProperty($name); $property->setAccessible(true); $property->setValue($object, $value);
}
function call_private($object, $name, ...$args) {
	$method = new ReflectionMethod($object, $name); $method->setAccessible(true); return $method->invoke($object, ...$args);
}
function check($condition, $name) { if (!$condition) throw new RuntimeException($name); echo "PASS $name\n"; }

$today = (new DateTime())->setTime(0, 0, 0);
$original = (clone $today)->modify('+10 days');
$destination = (clone $today)->modify('+11 days');
$original_room = (object) ['room_id' => 1, 'room_group_id' => 3];
$room = (object) ['room_id' => 2, 'room_group_id' => 3];
$period = (object) ['period_id' => 2, 'schedule_id' => 4, 'bookable' => 1];
foreach (range(1, 7) as $day) $period->{'day_' . $day} = 1;
$booking = (object) ['booking_id' => 10, 'room_id' => 1, 'room' => $original_room, 'period_id' => 2,
	'date' => $original, 'session_id' => 5, 'repeat_id' => null, 'user_id' => 7, 'created_by' => 7, 'department_id' => 9];
$session = (object) ['session_id' => 5, 'is_current' => 1, 'date_start' => (clone $today)->modify('-30 days'),
	'date_end' => (clone $today)->modify('+40 days')];
$ci = (object) ['rooms_model' => new FakeRooms(), 'periods_model' => new FakePeriods(), 'schedules_model' => new FakeSchedules(),
	'dates_model' => new FakeDates(), 'users_model' => new FakeUsers(), 'input' => new FakeInput(),
	'form_validation' => new FakeValidation(), 'load' => new FakeLoader(), 'bookings_model' => new FakeBookings()];
$ci->db = new FakeDb(); $ci->bookings_model->db = $ci->db;
$ci->rooms_model->rooms = [1 => $original_room, 2 => $room];
$ci->periods_model->periods[2] = $period;
$ci->schedules_model->schedule = (object) ['schedule_id' => 4];
$ci->dates_model->info = (object) ['session_id' => 5, 'week_id' => 1, 'holiday_id' => null];
$GLOBALS['permissions'] = [Permission::BK_SGL_CREATE => [1 => true, 2 => true], Permission::BK_RECUR_CREATE => [1 => true, 2 => true]];
$agent = (new ReflectionClass(UpdateAgent::class))->newInstanceWithoutConstructor();
set_prop($agent, 'CI', $ci); set_prop($agent, 'user', (object) ['user_id' => 7]);
set_prop($agent, 'session', $session); set_prop($agent, 'booking', $booking); set_prop($agent, 'edit_mode', UpdateAgent::EDIT_ONE);
$move = ['date' => $destination->format('Y-m-d'), 'period_id' => 2, 'room_id' => 2];
$valid = fn($data) => call_private($agent, 'valid_destination', $data);
check($valid($move), 'eligible destination accepted');
unset($ci->rooms_model->rooms[2]);
check(!$valid($move), 'inaccessible or nonbookable destination room rejected');
check($valid(['notes' => 'updated']), 'metadata edit preserves an existing slot');
$ci->rooms_model->rooms[2] = $room;
unset($ci->rooms_model->rooms[1]);
check(!$valid(['date' => $move['date']]), 'date-only move rechecks access to unchanged room');
$ci->rooms_model->rooms[1] = $original_room;
$room->room_group_id = 8; check(!$valid($move), 'destination outside original group rejected'); $room->room_group_id = 3;
$GLOBALS['permissions'][Permission::BK_SGL_CREATE][2] = false;
check(!$valid($move), 'destination create permission required');
$GLOBALS['permissions'][Permission::BK_SGL_CREATE][2] = true;
$period->bookable = 0; check(!$valid($move), 'unchanged nonbookable period cannot be moved'); $period->bookable = 1;
$period->schedule_id = 99; check(!$valid($move), 'period in another schedule rejected'); $period->schedule_id = 4;
$ci->schedules_model->schedule = false; check(!$valid($move), 'missing applied schedule rejected'); $ci->schedules_model->schedule = (object) ['schedule_id' => 4];
$day_field = 'day_' . $destination->format('N'); $period->$day_field = 0;
check(!$valid($move), 'period unavailable on destination weekday rejected'); $period->$day_field = 1;
$ci->dates_model->info->holiday_id = 1; check(!$valid($move), 'holiday rejected'); $ci->dates_model->info->holiday_id = null;
$ci->dates_model->info->session_id = 6; check(!$valid($move), 'date in another session rejected'); $ci->dates_model->info->session_id = 5;
$ci->dates_model->info->week_id = null; check(!$valid($move), 'date without timetable week rejected'); $ci->dates_model->info->week_id = 1;
$outside = $move; $outside['date'] = (clone $today)->modify('+41 days')->format('Y-m-d');
check(!$valid($outside), 'date outside session bounds rejected even with a date record');
$ci->users_model->constraints['range_min'] = 12; check(!$valid($move), 'minimum notice enforced');
$ci->users_model->constraints['range_min'] = 11; $ci->users_model->constraints['range_max'] = 11;
check($valid($move), 'notice bounds inclusive');
$ci->users_model->constraints['range_min'] = null; $ci->users_model->constraints['range_max'] = 10;
check(!$valid($move), 'maximum advance enforced'); $ci->users_model->constraints['range_max'] = null;
$past = $move; $past['date'] = (clone $today)->modify('-1 day')->format('Y-m-d');
check(!$valid($past), 'past date rejected without global create permission');
$GLOBALS['permissions'][Permission::BK_SGL_CREATE]['global'] = true;
check($valid($past), 'global create permission retains session-wide past-date bypass');
$GLOBALS['permissions'][Permission::BK_SGL_CREATE]['global'] = false;
$ci->users_model->constraints['range_max'] = 5; $booking->repeat_id = 8;
check($valid($move), 'one-time notice limits do not apply to recurring occurrence');
$GLOBALS['permissions'][Permission::BK_RECUR_CREATE][2] = false;
check(!$valid($move), 'recurring occurrence requires recurring destination permission');
$GLOBALS['permissions'][Permission::BK_RECUR_CREATE][2] = true; $booking->repeat_id = null; $ci->users_model->constraints['range_max'] = null;
$change_user = $move + ['user_id' => 8]; check(!$valid($change_user), 'changing user requires destination permissions');
$GLOBALS['permissions'][Permission::BK_SGL_VIEW_OTHER_USERS][2] = true;
check(!$valid($change_user), 'view permission alone cannot assign user');
$GLOBALS['permissions'][Permission::BK_SGL_SET_USER][2] = true; check($valid($change_user), 'destination user permissions allow assignment');
$change_dept = $move + ['department_id' => 10]; check(!$valid($change_dept), 'changing department requires destination permission');
$GLOBALS['permissions'][Permission::BK_SGL_SET_DEPT][2] = true; check($valid($change_dept), 'destination department permission allows assignment');
$booking->user_id = 8; $booking->notes = 'old';
$change_notes = $move + ['notes' => 'new']; check(!$valid($change_notes), 'editing another user notes requires destination permission');
$GLOBALS['permissions'][Permission::BK_SGL_VIEW_OTHER_NOTES][2] = true;
check(!$valid($change_notes), 'viewing notes alone cannot edit them');
$GLOBALS['permissions'][Permission::BK_SGL_EDIT_OTHER][2] = true; check($valid($change_notes), 'destination note permissions allow editing');
$booking->user_id = 7;
set_prop($agent, 'features', [UpdateAgent::FEATURE_DATE => true, UpdateAgent::FEATURE_PERIOD => true, UpdateAgent::FEATURE_ROOM => true,
	UpdateAgent::FEATURE_DEPARTMENT => false, UpdateAgent::FEATURE_EDIT_USER => false, UpdateAgent::FEATURE_EDIT_NOTES => false, UpdateAgent::FEATURE_CAPACITY => false]);
$ci->input->data = ['booking_date' => $move['date'], 'period_id' => 2, 'room_id' => 2];
$GLOBALS['permissions'][Permission::BK_SGL_CREATE][2] = false;
check(!call_private($agent, 'process_edit_booking') && !$ci->bookings_model->updates, 'rejected submission never reaches update');
$GLOBALS['permissions'][Permission::BK_SGL_CREATE][2] = true;
check(call_private($agent, 'process_edit_booking') && count($ci->bookings_model->updates) === 1, 'eligible submission reaches update');
$ci->users_model->constraints['max_active_bookings'] = 1;
$ci->users_model->quota = ['count' => 1, 'requested' => 1];
check(!call_private($agent, 'process_edit_booking') && count($ci->bookings_model->updates) === 1 && $ci->db->depth === 0, 'positive quota edit over limit rejected and rolled back');
check($ci->users_model->rows[0] === 7 && $ci->users_model->rows[1][0]['date'] === $move['date'] && $ci->users_model->rows[2][0]['date'] === $original->format('Y-m-d'), 'edit passes original creator and before/after rows to quota lock');
$ci->users_model->quota = ['count' => 2, 'requested' => 0];
check(call_private($agent, 'process_edit_booking'), 'zero quota delta remains editable even above limit');
$ci->db->depth = 1; $rollbacks = $ci->db->rollbacks;
check(!call_private($agent, 'process_edit_booking') && $ci->db->depth === 1 && $ci->db->rollbacks === $rollbacks, 'nested update rejected without disturbing caller');
$ci->db->depth = 0; $ci->db->commit = false; set_prop($agent, 'success', false);
check(!call_private($agent, 'process_edit_booking') && $ci->db->depth === 0 && !$agent->is_success(), 'update commit failure rolls back');
$ci->db->commit = true; $ci->bookings_model->throw = true;
try { call_private($agent, 'process_edit_booking'); check(false, 'nested update exception'); }
catch (RuntimeException $e) { check($ci->db->depth === 0, 'nested update exception unwinds entire owned transaction'); }
echo "All UpdateAgent destination tests passed.\n";