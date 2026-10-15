<!-- single_recurring_preview.php -->
<?php

use app\components\Calendar;

// Weekday name that the selected date falls on
$day_name = Calendar::get_day_name($date_info->weekday);

$conflict_count = $conflict_count ?? 0;


// Generate table of bookings
//
$this->table->set_template([
	'table_open' => '<table class="zebra-table" style="line-height:1.3;margin-top:16px;margin-bottom:16px" width="100%" cellpadding="10" cellspacing="0" border="0">',
]);
$this->table->set_heading([
	['data' => lang('app.date'), 'width' => '35%'],
	['data' => lang('app.action'), 'width' => '35%'],
	['data' => lang('booking.existing_booking'), 'width' => '30%'],
]);


// Table rows for every slot.
//
foreach ($slots as $key => $slot) {

	$date = date_output_long($slot['datetime']);

	$existing_html = sprintf(
		lang('booking.capacity.remaining'),
		(int) $slot['booked_capacity'],
		(int) $room_capacity,
		(int) $slot['remaining_capacity']
	);
	if (!empty($slot['bookings'])) {
		$existing_html .= '<ul class="recurring-slot-bookings">';
		foreach ($slot['bookings'] as $booking) {
			$is_owner = (int) ($booking->user_id ?? $booking->created_by ?? 0) === (int) $user->user_id;
			$is_recurring_booking = !empty($booking->repeat_id);
			$can_view_user = $is_owner || has_permission(
				$is_recurring_booking ? Permission::BK_RECUR_VIEW_OTHER_USERS : Permission::BK_SGL_VIEW_OTHER_USERS,
				$room->room_id
			);
			$can_view_notes = $is_owner || has_permission(
				$is_recurring_booking ? Permission::BK_RECUR_VIEW_OTHER_NOTES : Permission::BK_SGL_VIEW_OTHER_NOTES,
				$room->room_id
			);
			$user_label = lang('booking.capacity.reserved');
			if ($can_view_user && !empty($booking->user)) {
				$user_label = !empty($booking->user->displayname) ? $booking->user->displayname : $booking->user->username;
			}
			$existing_html .= '<li>' . html_escape($user_label) . ' — ' . sprintf(
				lang('booking.capacity.booked_by'),
				max(1, (int) ($booking->capacity ?? 1)),
				max(1, (int) ($booking->capacity ?? 1)) === 1 ? lang('booking.capacity.person') : lang('booking.capacity.people')
			);
			if (!empty($booking->notes)) {
				$notes = $can_view_notes ? html_escape($booking->notes) : html_escape(lang('app.not_available'));
				$existing_html .= '<div>' . $notes . '</div>';
			}
			$existing_html .= '</li>';
		}
		$existing_html .= '</ul>';
	}

	$date_ymd = $slot['datetime']->format('Y-m-d');
	$field_name = sprintf('dates[%s][action]', $date_ymd);

	// Default value
	$hidden_html = form_hidden($field_name, 'do_not_book');

	if (empty($slot['can_book'])) {
		$replace_booking_id = $slot['bookings'][0]->booking_id ?? '';
		$hidden = $replace_booking_id
			? form_hidden(sprintf('dates[%s][replace_booking_id]', $date_ymd), $replace_booking_id)
			: '';
		$options_value = set_value($field_name);

		$actions_list = form_dropdown([
			'name' => $field_name,
			'options' => $slot['actions'],
			'selected' => $options_value ?: 'do_not_book',
		]);

		$actions_html = $hidden . $actions_list;

	} else {

		$field_id = sprintf('date_%s', $date_ymd);
		$field_value = set_value($field_name, 'book', FALSE);

		$input = form_checkbox([
			'name' => $field_name,
			'id' => $field_id,
			'value' => 'book',
			'style' => 'vertical-align:-12.5%',
			'checked' => ($field_value == 'book'),
		]);

		$input_label = form_label($input . lang('booking.create_booking'), $field_id, ['class' => 'ni']);

		$actions_html = $input_label . '<div class="hint">' . sprintf(lang('booking.capacity.remaining_short'), (int) $slot['remaining_capacity']) . '</div>';
	}

	$this->table->add_row($date, $hidden_html . $actions_html, $existing_html);
}

// Info
//

$info = [];

$lang_key = sprintf('cal_%s', strtolower((string) $day_name));
$day_name_lang = lang($lang_key);
$recurring_html = sprintf(lang('booking.recurring.repeat_description'), $day_name_lang, html_escape($week->name));
$info['booking.recurs'] = $recurring_html;

$period_html = html_escape($period->name);
$start = date_output_time($period->time_start);
$end = date_output_time($period->time_end);
$period_html .= sprintf(' <span style="font-size:90%%;color:#aaa;background:transparent">(%s - %s)</span>', $start, $end);
$info['period.period'] = $period_html;

$room_html = html_escape($room->name);
$info['room.room'] = $room_html;
$info['booking.capacity.form_label'] = sprintf(
	'%d %s',
	(int) $booking_capacity,
	(int) $booking_capacity === 1 ? lang('booking.capacity.person') : lang('booking.capacity.people')
);

if ($department) {
	$department_html = html_escape($department->name);
	$info['department.department'] = $department_html;
}

$user_html = !empty($booking_user->displayname)
	? $booking_user->displayname
	: $booking_user->username;
$user_html = html_escape($user_html);
$info['booking.booked_by'] = $user_html;

$notes_html = set_value('notes');
if (!empty($notes_html)) {
	$info['booking.notes'] = $notes_html;
}

$info_fmt = '<div><dt>%s:</dt><dd>%s</dd></div>';
$info_html = '';
foreach ($info as $key => $value) {
	$label = lang($key);
	$info_html .= sprintf($info_fmt, $label, $value);
}

echo "<fieldset style='padding-top:0;padding-bottom:0'><dl class='info'>{$info_html}</dl></fieldset>";

if (isset($instances_msg)) {
	echo $instances_msg;
}

echo msgbox('info', lang('booking.capacity.recurring.skip_notice'), FALSE);

if ($conflict_count > 0) {
	$str = ($conflict_count === 1)
		? lang('booking.conflict.one')
		: sprintf(lang('booking.conflict.multiple'), $conflict_count);
	echo msgbox('exclamation', $str, FALSE);
}


// Form
//
$attrs = [
	'id' => 'bookings_create_single',
	'class' => '',
	'up-accept-location' => 'bookings/*',
	'up-target' => '.bookings-create',
	'up-layer' => 'any',
];

$hidden = [
	'room_id' => $room->room_id,
	'period_id' => $period->period_id,
	'department_id' => set_value('department_id'),
	'user_id' => set_value('user_id'),
	'notes' => set_value('notes'),
	'capacity' => $booking_capacity,
	'date' => set_value('date'),
	'recurring_start' => set_value('recurring_start'),
	'recurring_end' => set_value('recurring_end'),
];

echo form_hidden($hidden);

echo "<fieldset style='border:0; padding:0;'>";
echo $this->table->generate();
echo "</fieldset>";


// Actions
//
$submit = form_button([
	'type' => 'submit',
	'name' => 'action',
	'value' => 'create_recurring',
	'content' => '&check; ' . lang('booking.add.recurring.action'),
]);

$cancel = anchor($return_uri, lang('app.action.cancel'), ['up-dismiss' => '']);

echo "<div class='' style='border-top:0px;'>{$submit} &nbsp; {$cancel}</div>";