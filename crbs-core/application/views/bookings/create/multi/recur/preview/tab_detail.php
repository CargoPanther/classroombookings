<?php

use app\components\Calendar;

if (isset($slot->message)) {
	echo msgbox('exclamation large', $slot->message);
}

$requested_capacity = max(1, (int) ($slot->requested_capacity ?? 1));
$requested_unit = $requested_capacity === 1 ? lang('booking.capacity.person') : lang('booking.capacity.people');
echo msgbox('notice', sprintf('%s: %d %s', lang('booking.capacity.form_label'), $requested_capacity, $requested_unit), FALSE);

// Day name for the slot
$day_name = Calendar::get_day_name($slot->datetime->format('N'));
$lang_key = sprintf('cal_%s', strtolower((string) $day_name));
$day_name_lang = lang($lang_key);

// Generate table of bookings
//
$this->table->set_template([
	'table_open' => '<table class="zebra-table" style="line-height:1.3;margin-top:16px;margin-bottom:16px" width="100%" cellpadding="10" cellspacing="0" border="0">',
]);

$this->table->set_heading([
	['data' => lang('app.date'), 'width' => '33%'],
	['data' => lang('app.action'), 'width' => '33%'],
	['data' => lang('booking.existing_booking'), 'width' => '33%'],
]);


// Table rows for every slot.
//
foreach ($slot->instances as $key => $instance) {

	$date = date_output_long($instance['datetime']);

	$existing_html = sprintf(
		lang('booking.capacity.remaining'),
		(int) ($instance['booked_capacity'] ?? 0),
		(int) ($instance['room_capacity'] ?? 1),
		(int) ($instance['remaining_capacity'] ?? 0)
	);
	if (!empty($instance['bookings'])) {
		$existing_html .= '<ul class="recurring-slot-bookings">';
		foreach ($instance['bookings'] as $booking) {
			$user_label = booking_user_viewable($booking) && !empty($booking->user)
				? (!empty($booking->user->displayname) ? $booking->user->displayname : $booking->user->username)
				: lang('booking.capacity.reserved');
			$places = max(1, (int) ($booking->capacity ?? 1));
			$unit = $places === 1 ? lang('booking.capacity.person') : lang('booking.capacity.people');
			$existing_html .= '<li>' . html_escape($user_label) . ' — ' . sprintf(lang('booking.capacity.booked_by'), $places, $unit);
			if (!empty($booking->notes)) {
				$notes = booking_notes_viewable($booking) ? html_escape($booking->notes) : html_escape(lang('app.not_available'));
				$existing_html .= '<div>' . $notes . '</div>';
			}
			$existing_html .= '</li>';
		}
		$existing_html .= '</ul>';
	}

	$date_ymd = $instance['datetime']->format('Y-m-d');
	$field_name = sprintf('dates[%d][%s][action]', $slot->mbs_id, $date_ymd);

	// Default value
	$hidden_html = form_hidden($field_name, 'do_not_book');

	if (empty($instance['can_book'])) {
		$booking = $instance['booking'] ?? null;
		$hidden = $booking
			? form_hidden(sprintf('dates[%d][%s][replace_booking_id]', $slot->mbs_id, $date_ymd), $booking->booking_id)
			: '';

		$options_value = set_value($field_name);

		$actions_list = form_dropdown([
			'name' => $field_name,
			'options' => $instance['actions'],
			'selected' => $options_value ?: 'do_not_book',
		]);

		$actions_html = $hidden . $actions_list;

	} else {

		$field_id = sprintf('slot_%d_date_%s', $slot->mbs_id, $date_ymd);
		$field_value = set_value($field_name, 'book', FALSE);

		$input = form_checkbox([
			'name' => $field_name,
			'id' => $field_id,
			'value' => 'book',
			'style' => 'vertical-align:middle',
			'checked' => ($field_value == 'book'),
		]);

		$input_label = form_label(lang('booking.create_booking'), $field_id, ['class' => 'ni', 'style' => 'display:inline-block']);

		$actions_html = $input . $input_label . '<div class="hint">' . sprintf(lang('booking.capacity.remaining_short'), (int) $instance['remaining_capacity']) . '</div>';
	}

	$this->table->add_row($date, $hidden_html . $actions_html, $existing_html);
}

echo $this->table->generate();
