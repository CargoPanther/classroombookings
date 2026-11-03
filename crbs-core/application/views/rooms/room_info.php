<div class='room-info' data-style="min-width: 320px">

	<?php
	$photo_html = '';
	$fields_html = '';

	if ($photo_url) {
		$photo_html = '<div class="room-photo">' . img($photo_url, false, ['class' => 'room-photo-image', 'alt' => html_escape(sprintf(lang('room.photo.alt'), $room->name))]) . '</div>';
	}

	$capacity_row = !empty($room->capacity)
		? '<div><dt>' . html_escape(lang('room.field.capacity')) . '</dt><dd>' . (int) $room->capacity . '</dd></div>'
		: '';
	?>

	<header class="booking-modal-header">
		<div class="booking-modal-icon"><?= ui_icon('school_manage_rooms.png', ['width' => 28, 'height' => 28]) ?></div>
		<div class="booking-modal-heading">
			<h2><?= html_escape($room->name) ?></h2>
		</div>
		<button type="button" class="booking-modal-close" up-dismiss aria-label="<?= html_escape(lang('booking.modal.close')) ?>">&times;</button>
	</header>

	<div class="booking-modal-body">
		<section class="booking-modal-card booking-modal-room">
			<h3><?= html_escape(lang('room.room_details')) ?></h3>
			<?php if (!empty($room_info) || $capacity_row !== '' || $photo_html !== ''): ?>
			<div class="booking-modal-room-body">
				<?= $photo_html ?>
				<dl class="booking-detail-list">
					<?php foreach ($room_info as $row): ?>
					<div><dt><?= html_escape($row['label']) ?></dt><dd><?= $row['value'] ?></dd></div>
					<?php endforeach; ?>
					<?= $capacity_row ?>
				</dl>
			</div>
			<?php else: ?>
			<p class="booking-modal-summary"><em>No details available.</em></p>
			<?php endif; ?>
		</section>
	</div>

</div>
