<?php

$percent = $room_capacity > 0 ? min(100, (int) round($booked_capacity / $room_capacity * 100)) : 0;
$state = $remaining_capacity < 1 ? 'is-full' : ($remaining_capacity * 4 <= $room_capacity ? 'is-low' : 'is-ok');
$remaining_label = $remaining_capacity < 1
	? lang('booking.modal.fully_booked')
	: sprintf(lang('booking.modal.remaining'), $remaining_capacity);

?>
<header class="booking-modal-header">
	<div class="booking-modal-icon"><?= ui_icon('school_manage_rooms.png', ['width' => 28, 'height' => 28]) ?></div>
	<div class="booking-modal-heading">
		<h2><?= html_escape($room_name) ?></h2>
		<p>
			<span><?= html_escape($date_label) ?></span>
			<span><?= week_dot($week, 'sm') ?> <?= html_escape($week->name) ?></span>
			<span><?= html_escape($period_label) ?></span>
		</p>
	</div>
	<div class="booking-modal-availability <?= $state ?>">
		<div class="booking-modal-availability-label"><?= lang('booking.modal.availability') ?></div>
		<div class="booking-modal-meter" role="img" aria-label="<?= html_escape(sprintf(lang('booking.modal.booked'), $booked_capacity, $room_capacity)) ?>">
			<span style="width: <?= $percent ?>%"></span>
		</div>
		<div class="booking-modal-availability-booked"><?= html_escape(sprintf(lang('booking.modal.booked'), $booked_capacity, $room_capacity)) ?></div>
		<div class="booking-modal-availability-remaining"><?= html_escape($remaining_label) ?></div>
	</div>
	<button type="button" class="booking-modal-close" up-dismiss aria-label="<?= html_escape(lang('booking.modal.close')) ?>">&times;</button>
</header>
