<?php

$hidden = [
	'room_id' => $room->room_id,
	'period_id' => $period->period_id,
	'date' => $date_info->date,
];

echo form_hidden($hidden);

$none = sprintf('(%s)', lang('app.none'));

$admin_add_collapsed = !empty($admin_add_for_other);
if ($admin_add_collapsed) {
	echo '<details class="booking-admin-add"' . ($this->input->post('action') ? ' open' : '') . '><summary class="button">' . html_escape(lang('booking.add.for_other_user')) . '</summary>';
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
	if (!empty($department)) {
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
$capacity_default = ($booking_type === 'recurring')
	? ($recurring_default_capacity ?? 1)
	: $default_booking_capacity;
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
if ($can_set_user) {
	$field = 'user_id';
	$label = form_label(lang('booking.booked_by'), $field);
	$options = results_to_assoc($all_users, 'user_id', fn($user) => !empty($user->displayname)
			? $user->displayname
			: $user->username, $none);
	$value = set_value($field, !empty($admin_add_for_other) ? '' : $user->user_id, FALSE);
	$input = form_dropdown([
		'name' => $field,
		'id' => $field,
		'options' => html_escape($options),
		'selected' => $value,
	]);
	echo "<p>{$label}{$input}</p>";
}


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
	'placeholder' => lang('booking.modal.notes_placeholder'),
	'value' => $value,
]);
echo sprintf("<p>%s%s</p>%s", $label, $input, form_error($field));

echo "</fieldset>";

// Actions
//
$submit = form_button([
	'type' => 'submit',
	'name' => 'action',
	'value' => 'create',
	'content' => lang('booking.modal.create_action'),
]);

$cancel = anchor($return_uri, lang('app.action.cancel'), ['up-dismiss' => '']);

echo "<div class='submit' style='border-top:0px;'>{$submit} &nbsp; {$cancel}</div>";

if ($admin_add_collapsed) {
	echo '</details>';
}
