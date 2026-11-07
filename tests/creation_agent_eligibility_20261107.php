<?php
// Behavioral creation eligibility regressions; runs with the standalone agent fixtures.
function current_url() { return '/booking'; }
function redirect($url) { throw new RuntimeException('redirect'); }
require __DIR__ . '/booking_agent_submission_reliability_20261107.php';
require __DIR__ . '/../crbs-core/application/components/bookings/exceptions/AgentException.php';

function eligibility_fixture() {
    $date = (new DateTime('tomorrow'))->setTime(0, 0);
    $ymd = $date->format('Y-m-d');
    $ci = make_ci();
    $session = (object) ['session_id' => 1, 'is_current' => false,
        'date_start' => (clone $date)->modify('-30 days'), 'date_end' => (clone $date)->modify('+30 days')];
    $ci->dates_model->info->weekday = (int) $date->format('N');
    $GLOBALS['permissions'] = [Permission::BK_SGL_CREATE => [1 => true, 2 => true], Permission::BK_RECUR_CREATE => [1 => true, 2 => true]];
    $single = make_agent(app\components\bookings\agent\SingleAgent::class, $ci);
    prop($single, 'room', (object) ['room_id' => 1, 'capacity' => 5]);
    prop($single, 'period', $ci->periods_model->period);
    prop($single, 'datetime', $date);
    prop($single, 'date_info', $ci->dates_model->info);
    prop($single, 'session', $session);
    prop($single, 'department', (object) ['department_id' => 9]);
    prop($single, 'booking_user', (object) ['user_id' => 7]);
    prop($single, 'view_data', ['existing_bookings' => []]);
    prop($single, 'recurring_dates', [(object) ['date' => $date]]);
    $slot = (object) ['mbs_id' => 11, 'date' => $ymd, 'period_id' => 2, 'room_id' => 1,
        'weekday' => (int) $date->format('N'), 'room' => (object) ['capacity' => 5],
        'remaining_capacity' => 5, 'recurring_dates' => [(object) ['date' => $date]]];
    $mb = (object) ['mb_id' => 4, 'session_id' => 1, 'week_id' => 3, 'slots' => [$slot]];
    $multi = make_agent(app\components\bookings\agent\MultiAgent::class, $ci);
    prop($multi, 'session', $session); prop($multi, 'multibooking', $mb); prop($multi, 'view_data', ['multibooking' => $mb]);
    $ci->input->data = ['mb_id' => 4, 'date' => $ymd, 'capacity' => 1, 'recurring_start' => $ymd, 'recurring_end' => $ymd,
        'slot_single' => [11 => ['create' => 1, 'capacity' => 1]],
        'slots' => [11 => ['create' => 1, 'capacity' => 1, 'recurring_start' => $ymd, 'recurring_end' => $ymd]],
        'dates' => [$ymd => ['action' => 'book']]];
    $_SESSION['mb_4_slots'] = [11 => ['capacity' => 1, 'recurring_start' => $ymd, 'recurring_end' => $ymd]];
    return [$ci, $single, $multi, $slot, $mb, $date];
}
function run_path($path, $ci, $single, $multi, $date) {
    if ($path === 'single') return invoke_private($single, 'create_single_booking');
    if ($path === 'single recurring') return invoke_private($single, 'create_single_recurring');
    if ($path === 'multi single') return invoke_private($multi, 'process_create_single');
    if ($path === 'multi defaults') {
        try { return invoke_private($multi, 'process_recurring_defaults'); }
        catch (RuntimeException $e) { if ($e->getMessage() === 'redirect') return true; throw $e; }
    }
    $ci->input->data['dates'] = [11 => [$date->format('Y-m-d') => ['action' => 'book']]];
    return invoke_private($multi, 'process_create_recurring');
}
$cases = [
    'inaccessible room' => function ($ci) { unset($ci->rooms_model->rooms[1]); },
    'room without group' => function ($ci) { $ci->rooms_model->rooms[1]->room_group_id = null; },
    'missing schedule' => function ($ci) { $ci->schedules_model->schedule = false; },
    'wrong schedule' => function ($ci) { $ci->periods_model->period->schedule_id = 99; },
    'nonbookable period' => function ($ci) { $ci->periods_model->period->bookable = 0; },
    'missing period' => function ($ci) { $ci->periods_model->period = false; },
    'disabled weekday' => function ($ci, $date) { $ci->periods_model->period->{'day_' . $date->format('N')} = 0; },
    'missing weekday' => function ($ci, $date) { unset($ci->periods_model->period->{'day_' . $date->format('N')}); },
    'missing week' => function ($ci) { $ci->dates_model->info->week_id = null; },
    'holiday' => function ($ci) { $ci->dates_model->info->holiday_id = 4; },
    'different session' => function ($ci) { $ci->dates_model->info->session_id = 2; },
];
foreach (['single', 'single recurring', 'multi single', 'multi defaults', 'multi final'] as $path) {
    [$ci, $single, $multi, $slot, $mb, $date] = eligibility_fixture();
    assert_case(run_path($path, $ci, $single, $multi, $date) === true, "$path accepts eligible selection");
    foreach ($cases as $name => $invalidate) {
        [$ci, $single, $multi, $slot, $mb, $date] = eligibility_fixture();
        $invalidate($ci, $date);
        assert_case(run_path($path, $ci, $single, $multi, $date) === false
            && !$ci->bookings_model->created && !$ci->bookings_repeat_model->created, "$path rejects $name before writes");
    }
}

// A previously previewed occurrence must be checked again, independently of its seed slot.
foreach (['single recurring', 'multi final'] as $path) {
    [$ci, $single, $multi, $slot, $mb, $date] = eligibility_fixture();
    $other = (clone $date)->modify('+7 days'); $otherYmd = $other->format('Y-m-d');
    $ci->dates_model->byDate[$otherYmd] = (object) ['session_id' => 1, 'week_id' => 3, 'holiday_id' => 5];
    $slot->recurring_dates[] = (object) ['date' => $other];
    prop($single, 'recurring_dates', $slot->recurring_dates);
    $ci->input->data['recurring_end'] = $otherYmd; $_SESSION['mb_4_slots'][11]['recurring_end'] = $otherYmd;
    $ci->input->data['dates'] = [$date->format('Y-m-d') => ['action' => 'book'], $otherYmd => ['action' => 'book']];
    if ($path === 'multi final') $ci->input->data['dates'] = [11 => $ci->input->data['dates']];
    $method = $path === 'multi final' ? 'process_create_recurring' : 'create_single_recurring';
    assert_case(invoke_private($path === 'multi final' ? $multi : $single, $method) === false
        && !$ci->bookings_repeat_model->created, "$path rejects ineligible later occurrence rather than silently skipping");
}

[$ci, $single, $multi, $slot, $mb, $date] = eligibility_fixture();
$slot2 = clone $slot; $slot2->mbs_id = 12; $slot2->room_id = 2; $mb->slots[] = $slot2;
$_SESSION['mb_4_slots'][12] = $_SESSION['mb_4_slots'][11];
$ci->input->data['dates'] = [11 => [$date->format('Y-m-d') => ['action' => 'book']], 12 => [$date->format('Y-m-d') => ['action' => 'book']]];
unset($ci->rooms_model->rooms[2]);
assert_case(invoke_private($multi, 'process_create_recurring') === false && $ci->db->rolledBack
    && !$ci->bookings_repeat_model->created && !$multi->is_success(),
    'multi final rejects a later inaccessible room before writing any series');

[$ci, $single, $multi, $slot, $mb, $date] = eligibility_fixture();
$past = (new DateTime('yesterday'))->setTime(0, 0); $slot->date = $past->format('Y-m-d');
$ci->users_model->constraints['range_max'] = 5;
assert_case(invoke_private($multi, 'process_create_single') === true, 'maximum-only notice does not impose a minimum of today');
$ci->bookings_model->created = []; $ci->users_model->constraints['range_min'] = 0;
assert_case(invoke_private($multi, 'process_create_single') === false && !$ci->bookings_model->created, 'explicit minimum remains enforced');

[$ci, $single, $multi, $slot, $mb, $date] = eligibility_fixture();
$ci->input->data['dates'] = [11 => [$date->format('Y-m-d') => ['action' => 'book'], (clone $date)->modify('+7 days')->format('Y-m-d') => ['action' => 'book']]];
assert_case(invoke_private($multi, 'process_create_recurring') === false && !$ci->bookings_repeat_model->created,
    'multi final rejects a mix of valid and unknown dates');
$ci->input->data['dates'] = [11 => [$date->format('Y-m-d') => ['action' => 'bogus']]];
assert_case(invoke_private($multi, 'process_create_recurring') === false && !$ci->bookings_repeat_model->created,
    'multi final rejects invalid actions');
$ci->input->data['dates'] = [99 => [$date->format('Y-m-d') => ['action' => 'book']]];
assert_case(invoke_private($multi, 'process_create_recurring') === false && !$ci->bookings_repeat_model->created,
    'multi final rejects unknown slots');

// The details page must not disable past slots solely because a maximum exists.
class CI_Model { public $load, $dates_model, $users_model, $db; }
function nest_object_keys($row) { return $row; }
require __DIR__ . '/../crbs-core/application/models/Multi_booking_model.php';
class EligibilityModelLoader { function model($names) {} }
class EligibilityModelDates { function get_recurring_dates(...$args) { return []; } }
class EligibilityModelQuery {
    public $row;
    function __call($name, $args) { return $this; }
    function result() { return [clone $this->row]; }
}
$model = (new ReflectionClass(Multi_booking_model::class))->newInstanceWithoutConstructor();
$model->load = new EligibilityModelLoader(); $model->dates_model = new EligibilityModelDates();
$model->users_model = new FakeUsers(); $model->db = new EligibilityModelQuery();
$model->db->row = (object) ['date' => $past->format('Y-m-d'), 'period_id' => 2, 'room_id' => 1, 'weekday' => (int) $past->format('N')];
$model->users_model->constraints['range_max'] = 5;
$model_mb = (object) ['mb_id' => 4, 'user_id' => 7, 'session_id' => 1, 'week_id' => 3];
$model_slots = $model->get_slots($model_mb);
assert_case(reset($model_slots)->capabilities['single.create'], 'multi details allow past slot with maximum-only notice');
$model->users_model->constraints['range_min'] = 0;
$model_slots = $model->get_slots($model_mb);
assert_case(!reset($model_slots)->capabilities['single.create'], 'multi details still enforce explicit minimum notice');

class EligibilitySessions { public $session; function get($id) { return $this->session; } }
class EligibilityDrafts { public array $created = []; function create($data) { $this->created[] = $data; return 4; } }
[$ci, $single, $multi, $slot, $mb, $date] = eligibility_fixture();
$ci->sessions_model = new EligibilitySessions(); $ci->sessions_model->session = (object) [
    'session_id' => 1, 'date_start' => (clone $date)->modify('-30 days'), 'date_end' => (clone $date)->modify('+30 days')];
$ci->multi_booking_model = new EligibilityDrafts();
$ci->input->data = ['slots' => [json_encode(['date' => $slot->date, 'period_id' => 2, 'room_id' => 1]),
    json_encode(['date' => $slot->date, 'period_id' => 2, 'room_id' => 2])]];
unset($ci->rooms_model->rooms[2]);
try { $multi->process_selection(); assert_case(false, 'invalid draft selection throws'); }
catch (app\components\bookings\exceptions\AgentException $e) {
    assert_case(!$ci->multi_booking_model->created, 'invalid second grid selection is rejected before draft write');
}
$ci->rooms_model->rooms[2] = (object) ['room_id' => 2, 'room_group_id' => 3];
try { $multi->process_selection(); }
catch (RuntimeException $e) { if ($e->getMessage() !== 'redirect') throw $e; }
assert_case(count($ci->multi_booking_model->created) === 1, 'eligible grid selections create a draft');
echo "All creation eligibility tests passed.\n";
