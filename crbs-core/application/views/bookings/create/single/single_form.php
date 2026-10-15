<?php

$hidden = [
	'room_id' => $room->room_id,
	'period_id' => $period->period_id,
	'date' => $date_info->date,
];

echo form_hidden($hidden);

$none = sprintf('(%s)', lang('app.none'));

if (!empty($existing_bookings)) {
	echo '<div class="booking-shared-layout"><div class="booking-shared-list">';
	foreach ($existing_bookings as $existing_booking) {
		$is_owner = (int) $existing_booking->user_id === (int) $this->userauth->user->user_id;
		$is_recurring = !empty($existing_booking->repeat_id);
		$can_view_user = $is_owner || has_permission($is_recurring ? Permission::BK_RECUR_VIEW_OTHER_USERS : Permission::BK_SGL_VIEW_OTHER_USERS, $room->room_id);
		$can_view_notes = $is_owner || has_permission($is_recurring ? Permission::BK_RECUR_VIEW_OTHER_NOTES : Permission::BK_SGL_VIEW_OTHER_NOTES, $room->room_id);
		$name = lang('booking.capacity.reserved');
		if ($can_view_user && !empty($existing_booking->user)) {
			$name = !empty($existing_booking->user->displayname)
				? $existing_booking->user->displayname
				: $existing_booking->user->username;
		}
		$places = max(1, (int) ($existing_booking->capacity ?? 1));
		$places_label = $places === 1 ? lang('booking.capacity.person') : lang('booking.capacity.people');

		$actions = [];
		if (booking_editable($existing_booking)) {
			$params = $this->input->get('params') ?? '';
			$edit_url = site_url('bookings/edit/' . $existing_booking->booking_id . '?' . http_build_query([
				'params' => $params,
				'shared_slot' => 1,
			]));
			if ($is_recurring) {
				$edit_choices = $this->load->view('bookings/edit_choice', [
					'booking' => $existing_booking,
					'current_user' => $this->userauth->user,
					'params' => $params,
					'shared_slot' => true,
				], TRUE);
				$actions[] = [
					'link' => '#',
					'name' => lang('app.action.update'),
					'icon' => 'edit.png',
					'attrs' => [
						'up-layer' => 'new popup',
						'up-align' => 'right',
						'up-size' => 'medium',
						'up-content' => html_escape($edit_choices),
						'up-history' => 'false',
					],
				];
			} else {
				$actions[] = [
					'link' => $edit_url,
					'name' => lang('app.action.update'),
					'icon' => 'edit.png',
					'attrs' => [
						'up-layer' => 'current',
						'up-target' => '.bookings-create',
						'up-preload' => '',
					],
				];
			}
		}
		if (booking_cancelable($existing_booking)) {
			$params = $this->input->get('params') ?? '';
			$cancel_choices = $this->load->view('bookings/cancel_choice', [
				'booking' => $existing_booking,
				'current_user' => $this->userauth->user,
				'params' => $params,
			], TRUE);
			$actions[] = [
				'link' => '#',
				'name' => lang('booking.action.cancel_booking'),
				'icon' => 'delete.png',
				'attrs' => [
					'up-layer' => 'new popup',
					'up-align' => 'right',
					'up-size' => 'medium',
					'up-content' => html_escape($cancel_choices),
					'up-class' => 'booking-choices-cancel',
				],
			];
		}
		$details = [
			[lang('app.date'), date_output_long($existing_booking->date)],
			[lang('booking.occurs'), $is_recurring ? lang('booking.type_recurring') : lang('booking.occurs.once')],
			[lang('booking.capacity'), sprintf('%d %s', $places, $places_label)],
			[lang('period.period'), html_escape(($existing_booking->period->name ?? '') . ' (' . date_output_time($existing_booking->period->time_start) . ' - ' . date_output_time($existing_booking->period->time_end) . ')')],
		];
		if ($can_view_user) $details[] = [lang('booking.booked_by'), html_escape($name)];
		if (!empty($existing_booking->department->name)) $details[] = [lang('department.department'), html_escape($existing_booking->department->name)];
		if (!empty($existing_booking->notes)) {
			$details[] = [lang('booking.notes'), $can_view_notes ? nl2br(html_escape($existing_booking->notes)) : '<em>' . lang('app.not_available') . '</em>'];
		}

		echo '<section class="booking-detail-section booking-shared-item"><h3>' . html_escape(lang('booking.booking')) . '</h3>';
		if ($actions) echo iconbar($actions);
		echo '<dl class="booking-detail-list">';
		foreach ($details as [$label, $value]) {
			echo '<div><dt>' . html_escape($label) . '</dt><dd>' . $value . '</dd></div>';
		}
		echo '</dl></section>';
	}

	echo '<section class="booking-detail-section booking-room-details"><h3>' . html_escape(lang('room.room_details')) . '</h3><h4>' . html_escape($room->name) . '</h4><dl class="booking-detail-list">';
	foreach ($room_details as $detail) {
		echo '<div><dt>' . html_escape($detail['label']) . '</dt><dd>' . $detail['value'] . '</dd></div>';
	}
	if (!empty($room->capacity)) {
		echo '<div><dt>' . html_escape(lang('room.field.capacity')) . '</dt><dd>' . (int) $room->capacity . '</dd></div>';
	}
	echo '</dl>';
	if (!empty($room_photo_url)) {
		$photo = img($room_photo_url, false, ['class' => 'room-photo-image', 'alt' => html_escape(sprintf(lang('room.photo.alt'), $room->name))]);
		echo '<div class="room-photo">' . $photo . '</div>';
	}
	echo '</section></div>';
	if (!empty($has_current_user_booking) || !empty($single_create_unavailable)) {
		return;
	}
	echo '<div class="booking-shared-form">';
}

echo "<fieldset style='border:0'>";

// Date
//
$field = 'date';
$label = form_label(lang('app.date'), 'date');
$input = sprintf('%s (%s)', date_output_long($datetime), html_escape($week->name));
echo "<p>{$label}{$input}</p>";


// Period
//
$field = 'period_id';
$label = form_label(lang('period.period'), $field);
$options = results_to_assoc($all_periods, 'period_id', function($period) {
	$start = date_output_time($period->time_start);
	$end = date_output_time($period->time_end);
	return sprintf('%s (%s - %s)', $period->name, $start, $end);
});
$value = set_value($field, $period->period_id, FALSE);
$input = form_dropdown([
	'name' => $field,
	'id' => $field,
	'options' => $options,
	'selected' => $value,
]);
echo "<p>{$label}{$input}</p>";


// Room
//
$field = 'room_id';
$label = form_label(lang('room.room'), $field);
$input = html_escape($room->name);
echo "<p>{$label}{$input}</p>";


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
echo "<p>{$label}{$input} " . sprintf(lang('booking.capacity.remaining'), $booked_capacity, $room_capacity, $remaining_capacity) . "</p>";
echo form_error($field);

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
$label = form_label(lang('booking.notes'), 'notes');
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

echo "</fieldset>";

// Actions
//
$submit = form_button([
	'type' => 'submit',
	'name' => 'action',
	'value' => 'create',
	'content' => '&check; ' . lang('booking.add.single.action'),
]);

$cancel = anchor($return_uri, lang('app.action.cancel'), ['up-dismiss' => '']);

echo "<div class='submit' style='border-top:0px;'>{$submit} &nbsp; {$cancel}</div>";

if (!empty($existing_bookings)) {
	echo '</div></div>';
}
