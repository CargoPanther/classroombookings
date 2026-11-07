<?php
// Recurring previews and final submissions must use the same live eligibility rules.
require __DIR__ . '/booking_agent_submission_reliability_20261107.php';
function msgbox($class, $message, $close = true) { return $message; }

function preview_fixture() {
    $today = (new DateTime('today'))->setTime(0, 0);
    $past = (clone $today)->modify('-7 days');
    $future = (clone $today)->modify('+7 days');
    $rows = array_map(fn($date) => (object) ['date' => $date], [$past, $today, $future]);
    $ci = make_ci();
    $session = (object) ['session_id' => 1, 'is_current' => true,
        'date_start' => (clone $today)->modify('-30 days'), 'date_end' => (clone $today)->modify('+30 days')];
    $GLOBALS['permissions'] = [Permission::BK_RECUR_CREATE => [1 => true]];
    $single = make_agent(app\components\bookings\agent\SingleAgent::class, $ci);
    prop($single, 'room', (object) ['room_id' => 1, 'capacity' => 5]);
    prop($single, 'period', $ci->periods_model->period);
    prop($single, 'session', $session);
    prop($single, 'date_info', (object) ['week_id' => 3, 'weekday' => (int) $today->format('N')]);
    prop($single, 'booking_user', (object) ['user_id' => 7]);
    prop($single, 'department', (object) ['department_id' => 9]);
    prop($single, 'recurring_dates', $rows);
    $slot = (object) ['mbs_id' => 11, 'date' => $today->format('Y-m-d'), 'period_id' => 2,
        'room_id' => 1, 'weekday' => (int) $today->format('N'), 'room' => (object) ['capacity' => 5], 'recurring_dates' => $rows];
    $mb = (object) ['mb_id' => 4, 'session_id' => 1, 'week_id' => 3, 'slots' => [$slot]];
    $multi = make_agent(app\components\bookings\agent\MultiAgent::class, $ci);
    prop($multi, 'session', $session); prop($multi, 'multibooking', $mb);
    prop($multi, 'view_data', ['multibooking' => $mb, 'mb_id' => 4]);
    $ci->input->data = ['capacity' => 1, 'recurring_start' => 'session', 'recurring_end' => 'session'];
    $_SESSION['mb_4_slots'] = [11 => ['capacity' => 1, 'recurring_start' => $past->format('Y-m-d'), 'recurring_end' => $future->format('Y-m-d')]];
    return [$ci, $single, $multi, $slot, $session, $past, $today, $future];
}
function preview_single($single) {
    invoke_private($single, 'preview_single_recurring');
    return $single->view_data['slots'];
}
function preview_multi($ci, $multi, $slot) {
    $post = $ci->input->data; $ci->input->data = [];
    invoke_private($multi, 'handle_recurring_preview');
    $ci->input->data = $post;
    return $slot->instances;
}
function instance_for($instances, $date) {
    $key = app\components\bookings\Slot::generate_key($date->format('Y-m-d'), 2, 1);
    return $instances[$key];
}
function selected($date, $action) { return [$date->format('Y-m-d') => ['action' => $action]]; }

[$ci, $single, $multi, $slot, $session, $past, $today, $future] = preview_fixture();
$past_key = app\components\bookings\Slot::generate_key($past->format('Y-m-d'), 2, 1);
$ci->bookings_model->existing[$past_key] = [(object) ['booking_id' => 20, 'room_id' => 1, 'user_id' => 7, 'capacity' => 1]];
$ci->users_model->constraints['recur_max_instances'] = 2;
foreach (['single', 'multi'] as $path) {
    $instances = $path === 'single' ? preview_single($single) : preview_multi($ci, $multi, $slot);
    $past_row = instance_for($instances, $past);
    assert_case(!$past_row['date_eligible'] && !$past_row['can_book'] && $past_row['actions'] === ['do_not_book' => 'booking.do_not_book'], "$path past occurrence cannot be booked or replaced");
    assert_case(instance_for($instances, $today)['can_book'] && instance_for($instances, $future)['can_book'], "$path today and future remain selected by default");
    assert_case($path === 'single' ? $single->view_data['conflict_count'] === 0 : $slot->conflict_count === 0, "$path ineligible dates are not capacity conflicts");
}
assert_case($single->view_data['instances_msg'] === 'booking.notice.instances_to_create', 'single limit counts eligible instances only');
assert_case(!isset($slot->message), 'multi limit counts eligible instances only');

// Existing eligible conflicts retain the normal replacement workflow.
$future_key = app\components\bookings\Slot::generate_key($future->format('Y-m-d'), 2, 1);
$ci->bookings_model->existing[$future_key] = [(object) ['booking_id' => 21, 'room_id' => 1, 'user_id' => 7, 'capacity' => 1]];
foreach (['single' => preview_single($single), 'multi' => preview_multi($ci, $multi, $slot)] as $path => $instances) {
    $row = instance_for($instances, $future);
    assert_case($row['date_eligible'] && !$row['can_book'] && isset($row['actions']['replace']), "$path eligible conflict retains replacement option");
}
unset($ci->bookings_model->existing[$future_key]);

// A global permission allows the past in a current session, while a past session
// remains bookable for a non-global user, subject to all other eligibility checks.
foreach ([Permission::BK_SGL_CREATE, Permission::BK_RECUR_CREATE] as $permission) {
    $GLOBALS['permissions'][$permission][null] = true;
    assert_case(instance_for(preview_single($single), $past)['date_eligible'] && instance_for(preview_multi($ci, $multi, $slot), $past)['date_eligible'], "global $permission permits past dates in both previews");
    unset($GLOBALS['permissions'][$permission][null]);
}
$session->is_current = false;
assert_case(instance_for(preview_single($single), $past)['date_eligible'] && instance_for(preview_multi($ci, $multi, $slot), $past)['date_eligible'], 'past session retains eligibility');
$session->is_current = true;

// Non-date destination changes and revoked permissions must not expose actions.
$future_ymd = $future->format('Y-m-d');
$ci->dates_model->byDate[$future_ymd] = (object) ['session_id' => 1, 'week_id' => 3, 'holiday_id' => 9];
assert_case(!instance_for(preview_single($single), $future)['date_eligible'] && !instance_for(preview_multi($ci, $multi, $slot), $future)['date_eligible'], 'holiday is unavailable in both previews');
unset($ci->dates_model->byDate[$future_ymd]);
$day_field = 'day_' . $future->format('N');
$ci->periods_model->period->{$day_field} = 0;
assert_case(!instance_for(preview_single($single), $future)['date_eligible'] && !instance_for(preview_multi($ci, $multi, $slot), $future)['date_eligible'], 'disabled weekday is unavailable in both previews');
$ci->periods_model->period->{$day_field} = 1;
unset($ci->rooms_model->rooms[1]);
assert_case(!instance_for(preview_single($single), $future)['date_eligible'] && !instance_for(preview_multi($ci, $multi, $slot), $future)['date_eligible'], 'revoked resource access is unavailable in both previews');
$ci->rooms_model->rooms[1] = (object) ['room_id' => 1, 'room_group_id' => 3];
$GLOBALS['permissions'][Permission::BK_RECUR_CREATE][1] = false;
assert_case(!instance_for(preview_multi($ci, $multi, $slot), $future)['date_eligible'], 'revoked recurring permission makes multi preview unavailable');
$GLOBALS['permissions'][Permission::BK_RECUR_CREATE][1] = true;

// Simulate a change between preview and submission: reject the entire selection,
// including replacement, but allow a submitted do-not-book for the same date.
foreach (['single', 'multi'] as $path) {
    foreach (['book', 'replace'] as $action) {
        $dates = selected($past, $action) + selected($future, 'book');
        $ci->input->data['dates'] = $path === 'single' ? $dates : [11 => $dates];
        $result = invoke_private($path === 'single' ? $single : $multi, $path === 'single' ? 'create_single_recurring' : 'process_create_recurring');
        assert_case($result === false && !$ci->bookings_repeat_model->created, "$path rejects ineligible $action atomically");
    }
    $dates = selected($past, 'do_not_book') + selected($future, 'book');
    $ci->input->data['dates'] = $path === 'single' ? $dates : [11 => $dates];
    $result = invoke_private($path === 'single' ? $single : $multi, $path === 'single' ? 'create_single_recurring' : 'process_create_recurring');
    assert_case($result === true && count($ci->bookings_repeat_model->created) === 1 && $ci->bookings_repeat_model->created[0]['dates'][$past->format('Y-m-d')]['action'] === 'do_not_book', "$path creates eligible dates with unavailable date skipped");
    $ci->bookings_repeat_model->created = [];
}
// Eligibility can change after an initially eligible preview.
$GLOBALS['permissions'][Permission::BK_RECUR_CREATE][null] = true;
assert_case(instance_for(preview_single($single), $past)['date_eligible'] && instance_for(preview_multi($ci, $multi, $slot), $past)['date_eligible'], 'global permission initially allows past preview');
unset($GLOBALS['permissions'][Permission::BK_RECUR_CREATE][null]);
foreach (['single', 'multi'] as $path) {
    $dates = selected($past, 'book');
    $ci->input->data['dates'] = $path === 'single' ? $dates : [11 => $dates];
    assert_case(invoke_private($path === 'single' ? $single : $multi, $path === 'single' ? 'create_single_recurring' : 'process_create_recurring') === false && !$ci->bookings_repeat_model->created, "$path revalidates after permission change");
}
// Render both real templates with a stale submitted replacement for the past
// occurrence. Only the hidden do-not-book field may be emitted for that row.
require __DIR__ . '/../crbs-core/application/components/Calendar.php';
function html_escape($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function form_hidden($name, $value = '') { return is_array($name) ? '' : "hidden:$name=$value"; }
function set_value($name, $default = '', $escape = true) { return strpos($name, $GLOBALS['preview_past']) !== false ? 'replace' : $default; }
function form_checkbox($props) { return 'checkbox:' . $props['name'] . ':' . ($props['checked'] ? 'checked' : 'unchecked'); }
function form_dropdown($props) { return 'dropdown:' . $props['name']; }
function form_label($label, $id, $attributes = []) { return $label; }
function form_button($props) { return ''; }
function anchor(...$args) { return ''; }
function date_output_time($time) { return $time; }
function booking_user_viewable($booking) { return true; }
function booking_notes_viewable($booking) { return true; }
class PreviewTable {
    public $rows = [];
    function set_template($template) {}
    function set_heading($heading) {}
    function add_row(...$row) { $this->rows[] = $row; }
    function generate() { return ''; }
}
class PreviewRenderer {
    public $table;
    function __construct() { $this->table = new PreviewTable(); }
    function render($file, $vars) { extract($vars); ob_start(); include $file; ob_end_clean(); return $this->table->rows; }
}
$GLOBALS['preview_past'] = $past->format('Y-m-d');
$single_instances = preview_single($single);
$multi_instances = preview_multi($ci, $multi, $slot);
$slot->datetime = $today;
$renderer = new PreviewRenderer();
$single_rows = $renderer->render(__DIR__ . '/../crbs-core/application/views/bookings/create/single/recurring_preview.php', [
    'slots' => $single_instances, 'date_info' => (object) ['weekday' => (int) $today->format('N')],
    'room_capacity' => 5, 'booking_capacity' => 1, 'user' => (object) ['user_id' => 7],
    'room' => (object) ['room_id' => 1, 'name' => 'Room'],
    'period' => (object) ['period_id' => 2, 'name' => 'Period', 'time_start' => '09:00', 'time_end' => '10:00'],
    'week' => (object) ['name' => 'Week'], 'department' => null,
    'booking_user' => (object) ['username' => 'User'], 'return_uri' => '',
]);
$renderer = new PreviewRenderer();
$multi_rows = $renderer->render(__DIR__ . '/../crbs-core/application/views/bookings/create/multi/recur/preview/tab_detail.php', ['slot' => $slot]);
foreach (['single' => $single_rows, 'multi' => $multi_rows] as $path => $rows) {
    assert_case(strpos($rows[0][1], '=do_not_book') !== false && strpos($rows[0][1], 'booking.error.no_permission_room_date') !== false
        && strpos($rows[0][1], 'checkbox:') === false && strpos($rows[0][1], 'dropdown:') === false,
        "$path unavailable row renders only do-not-book and an explanation even with stale input");
    assert_case(strpos($rows[1][1], 'checkbox:') !== false && strpos($rows[1][1], ':checked') !== false,
        "$path eligible row remains checked by default");
}
echo "All recurring preview eligibility tests passed.\n";
