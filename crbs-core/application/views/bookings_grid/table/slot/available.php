<?php

/* @var $slot app\components\bookings\Slot */

$params = [
	'date' => $slot->date->date,
	'period_id' => $slot->period->period_id,
	'room_id' => $slot->room->room_id,
	'params' => http_build_query($context->get_query_params()),
];

$uri = 'bookings/create/single?' . http_build_query($params);
$url = site_url($uri);

$input_name = sprintf('slots[]');
$input_id = sprintf('slot_chk_%s', $slot->key);
$input_value = json_encode([
	'date' => $slot->date->date,
	'period_id' => $slot->period->period_id,
	'room_id' => $slot->room->room_id,
]);
$capacity_summary = '';
if (!empty($booked_capacity)) {
	$capacity_summary = sprintf(lang('booking.capacity.summary'), $booked_capacity, $room_capacity);
}
$shared_booking_classes = [];
foreach ($bookings ?? [] as $booking) {
	$shared_type = !empty($booking->repeat_id) ? 'recurring' : 'single';
	$shared_booking_classes[] = 'booking-status-shared-' . $shared_type;
}
$class = implode(' ', array_unique(array_filter(array_merge(
	explode(' ', $class),
	$shared_booking_classes
))));

?>

<td class='<?= html_escape($class) ?>'>

	<label
		style="display: none"
		class="bookings-grid-button multi-select-content"
		data-multi="true"
		for="<?= $input_id ?>"
	>
		<?php
		echo form_checkbox([
			'form' => 'form_create_multi',
			'name' => $input_name,
			'id' => $input_id,
			'value' => $input_value,
		]);
		?>
	</label>

	<button
		class="bookings-grid-button multi-select-content"
		data-single="<?= isset($allow_single) && $allow_single ? 1 : 0 ?>"
		data-recurring="<?= isset($allow_recur) && $allow_recur ? 1 : 0 ?>"
		data-multi="false"
		aria-label="<?= html_escape($capacity_summary ? $capacity_summary . '. ' . lang('booking.capacity.click') : lang('booking.create_booking')) ?>"
		up-href="<?= $url ?>"
		up-target=".bookings-create"
		up-layer="new"
		up-size="large"
		up-cache="false"
		up-preload
	><?php if ($capacity_summary): ?>
		<span class="bookings-grid-capacity"><?= html_escape($capacity_summary) ?></span>
		<small class="bookings-grid-capacity-hint"><?= lang('booking.capacity.click') ?></small>
	<?php else: ?>&nbsp;<?php endif; ?>
	</button>

</td>
