<?php
use app\components\Calendar;

$hidden = [
	'room_id' => $room->room_id,
	'period_id' => $period->period_id,
	'date' => $date_info->date,
];

echo form_hidden($hidden);

$none = sprintf('(%s)', lang('app.none'));

// Weekday name that the selected date falls on
$day_name = Calendar::get_day_name($date_info->weekday);
$lang_key = sprintf('cal_%s', strtolower((string) $day_name));
$day_name_lang = lang($lang_key);

// List of availbale dates to choose from for recurring dates
//
$recurring_date_options = [];
if ($recurring_dates !== false) {
	foreach ($recurring_dates as $date) {
		$title = date_output_long($date->date);
		$title = trim(str_replace($day_name, '', $title));
		$title = trim(str_replace($day_name_lang, '', $title));
		$title = ltrim($title, '\s,');
		if ($date->date->format('Y-m-d') == $date_info->date) $title = '* ' . $title;
		$recurring_date_options[ $date->date->format('Y-m-d') ] = $title;
	}
}

echo "<fieldset style='border:0'>";

// Department
//
$field = 'department_id';
$label = form_label(lang('department.department'), $field);
$show_department = FALSE;
if ($can_set_department) {
	$show_department = TRUE;
	$options = results_to_assoc($all_departments, 'department_id', 'name', $none);
	$value = set_value($field, $department ? $department->department_id : '', FALSE);
	$input = form_dropdown([
		'name' => $field,
		'id' => $field,
		'options' => html_escape($options),
		'selected' => $value,
	]);
} else {
	if ($department) {
		$show_department = TRUE;
		$input = html_escape($department->name);
	}
}
echo ($show_department)
	? "<p>{$label}{$input}</p>"
	: '';

// Capacity
$field = 'capacity';
$label = form_label(lang('booking.capacity.form_label'), $field);
$capacity_default = $default_booking_capacity;
$value = set_value($field, $capacity_default, FALSE);
$input = form_input([
	'name' => $field,
	'id' => $field,
	'type' => 'number',
	'min' => 1,
	'max' => max(1, $remaining_capacity),
	'step' => 1,
	'required' => TRUE,
	'value' => $value,
]);
echo "<p>{$label}{$input}</p>";
echo form_error($field);

$requested = max(1, (int) $value);
$hint_template = lang('booking.modal.capacity_hint');
$over = $requested > $remaining_capacity;
$hint_text = $over
	? lang('booking.capacity.invalid')
	: strtr($hint_template, ['{count}' => $requested, '{available}' => $remaining_capacity]);
printf(
	'<div class="booking-capacity-preview" data-capacity-preview data-input="%s" data-available="%d" data-hint="%s" data-over="%s">',
	html_escape($field),
	$remaining_capacity,
	html_escape($hint_template),
	html_escape(lang('booking.capacity.invalid'))
);
echo '<p class="booking-capacity-hint' . ($over ? ' is-over' : '') . '" data-capacity-hint>' . html_escape($hint_text) . '</p>';
echo '</div>';

// Who
//
$field = 'user_id';
$label = form_label(lang('booking.booked_by'), $field);
if ($can_set_user) {
	$options = results_to_assoc($all_users, 'user_id', fn($user) => !empty($user->displayname)
			? $user->displayname
			: $user->username, $none);
	$value = set_value($field, $user->user_id, FALSE);
	$input = form_dropdown([
		'name' => $field,
		'id' => $field,
		'options' => html_escape($options),
		'selected' => $value,
	]);
} else {
	$input = !empty($user->displayname)
		? $user->displayname
		: $user->username;
	$input = html_escape($input);
}
echo "<p>{$label}{$input}</p>";


// Notes
//
$field = 'notes';
$value = set_value($field, '', FALSE);
$label = form_label(lang('booking.notes') . ' <span class="booking-optional">(' . lang('booking.modal.optional') . ')</span>', 'notes');
$input = form_textarea([
	'autofocus' => 'true',
	'name' => $field,
	'id' => $field,
	'rows' => '3',
	'cols' => '50',
	'tabindex' => tab_index(),
	'value' => $value,
]);
echo sprintf("<p>%s%s</p>%s", $label, $input, form_error($field));

// Recurring?
//

$field = 'recurring';
$value = set_value($field, '1', FALSE);


// Recurring fields
//
$recurring_fields = [];

// Info
//
$field = 'recurring_info';
$label = form_label(lang('booking.recurs'), 'recurring_info');
$input = sprintf(lang('booking.recurring.repeat_description'), $day_name_lang, html_escape($week->name));
$recurring_fields[] = "<p>{$label}{$input}</p>";

// Starting from
//
$field = 'recurring_start';
$label = form_label(lang('booking.recurring.starting_from'), 'recurring_start');
$value = set_value($field, $date_info->date, FALSE);
$options = [
	'session' => sprintf('(%s)', lang('booking.recurring.start_of_session')),
	lang('booking.recurring.specific_date') => $recurring_date_options,
];
$input = form_dropdown([
	'name' => 'recurring_start',
	'options' => $options,
	'selected' => $value,
]);
$recurring_fields[] = "<p>{$label}{$input}</p>";

// Until
//
$field = 'recurring_end';
$label = form_label(lang('booking.recurring.until'), 'recurring_end');
$value = set_value($field, 'session', FALSE);
$options = [
	'session' => sprintf('(%s)', lang('booking.recurring.end_of_session')),
	lang('booking.recurring.specific_date') => $recurring_date_options,
];
$input = form_dropdown([
	'name' => 'recurring_end',
	'options' => $options,
	'selected' => $value,
]);
$recurring_fields[] = "<p>{$label}{$input}</p>";

echo "<div class='recurring-content'>" . implode("\n", $recurring_fields) . "</div>";

//

echo "</fieldset>";

// Actions
//

$submit = form_button([
	'type' => 'submit',
	'name' => 'action',
	'value' => 'preview_recurring',
	'content' => lang('booking.recurring.preview') . ' &rarr;',
]);

$cancel = anchor($return_uri, lang('app.action.cancel'), ['up-dismiss' => '']);

echo "<div class='submit' style='border-top:0px;'>{$submit} &nbsp; {$cancel}</div>";
