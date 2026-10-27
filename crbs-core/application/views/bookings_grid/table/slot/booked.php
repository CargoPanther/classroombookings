<?php

$template = "{user}{notes}{actions}";

// Fully booked shared slots use the shared-booking colours
if (count($bookings ?? []) > 1) {
	$shared_classes = [];
	foreach ($bookings as $shared_booking) {
		$shared_classes[] = 'booking-status-shared-' . (!empty($shared_booking->repeat_id) ? 'recurring' : 'single');
	}
	$class = trim(str_replace(['booking-status-booked-single', 'booking-status-booked-recurring'], '', $class))
		. ' ' . implode(' ', array_unique($shared_classes));
}

$vars = [
	'{user}' => '',
	'{notes}' => '',
	'{actions}' => '',
];

$actions = [];

if (count($bookings ?? []) > 1) {
	$vars['{user}'] = '<div class="booking-cell-user">' . html_escape(sprintf(lang('booking.capacity.multiple'), count($bookings))) . '</div>'
		. '<div class="booking-cell-notes">' . html_escape(sprintf(lang('booking.capacity.summary'), $booked_capacity, $room_capacity)) . '</div>';
} elseif ($show_user && ! empty($booking->user)) {

	$user_label = !empty($booking->user->displayname)
		? $booking->user->displayname
		: $booking->user->username;
	if (!empty($user_label)) {
		$vars['{user}'] = '<div class="booking-cell-user">' . html_escape($user_label) . '</div>';
	}
}

// Notes
//
if (count($bookings ?? []) <= 1 && $show_notes && !empty($booking->notes)) {
	$notes = html_escape($booking->notes);
	$tooltip = '';
	if (strlen($notes) > 15) {
		$tooltip = 'up-tooltip="' . $notes . '"';
	}
	$vars['{notes}'] .= '<div class="booking-cell-notes" ' . $tooltip . '>'.character_limiter($notes, 15).'</div>';
	$vars['{notes}'] .= '<div class="booking-cell-notes-full">'.nl2br($notes).'</div>';
}

if ( ! empty($actions)) {
	$vars['{actions}'] = '';	//'<div class="booking-cell-actions">' . implode(" ", $actions) . '</div>';
}

// Process template for items
$body = strtr($template, $vars);
// Remove tags that don't have content
$body = str_replace(array_keys($vars), '', $body);

if (empty(trim($body))) {
	$body = '&mdash;';
}


// URL params to pass to /view/ so it can return to source page
$params = ['params' => http_build_query($context->get_query_params()) ];
$uri = sprintf('bookings/view/%d?%s', $booking->booking_id, http_build_query($params));
$url = site_url($uri);

// All booked slots open the shared slot layout; fall back to the view page without a slot
$use_shared_layout = !empty($slot);
if ($use_shared_layout) {
	$url = site_url('bookings/create/single?' . http_build_query([
		'date' => $slot->date->date,
		'period_id' => $slot->period->period_id,
		'room_id' => $slot->room->room_id,
		'params' => $params['params'],
	]));
}
// For checkbox
//
$input_name = sprintf('bookings[]');
$input_id = sprintf('booking_%d', $booking->booking_id);
$input_value = $booking->booking_id;

// Deletable
//
$is_deletable = count($bookings ?? []) <= 1 && booking_cancelable($booking);

?>

<td class='<?= $class ?>'>

	<?php if ($is_deletable): ?>
	<?php
	echo form_checkbox([
		'form' => 'form_cancel_multi',
		'name' => $input_name,
		'id' => $input_id,
		'value' => $input_value,
		'class' => 'bookings-grid-booked-check multi-select-content',
		'data-multi' => 'true',
		'style' => 'display:none; position: absolute; bottom: 2px; right: 2px;',
	]);
	?>
	<label
		style="display: none"
		class="bookings-grid-button multi-select-content"
		data-multi="true"
		for="<?= $input_id ?>"
	>
		<?php
		echo $body;
		?>
	</label>
	<?php endif; ?>

	<?php

	$cls = 'bookings-grid-button';

	if ($is_deletable) {
		$cls .= ' multi-select-content';
	}

	$link_attrs = [
		'class' => $cls,
		'up-target' => $use_shared_layout ? '.bookings-create' : '.bookings-view',
		'up-layer' => "new modal",
		'up-size' => "large",
		'up-history' => "false",
		'up-preload' => '',
	];
	if ($use_shared_layout) {
		$link_attrs['up-cache'] = 'false';
	}

	if ($is_deletable) {
		$link_attrs['data-multi'] = "false";
	}

	echo anchor($url, $body, $link_attrs);

	?>

</td>
