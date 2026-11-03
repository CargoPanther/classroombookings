<section class="booking-modal-card booking-modal-room">
	<h3><?= html_escape(lang('room.room_details')) ?></h3>
	<div class="booking-modal-room-body">
		<?php if (!empty($room_photo_url)): ?>
		<div class="room-photo"><?= img($room_photo_url, false, ['class' => 'room-photo-image', 'alt' => html_escape(sprintf(lang('room.photo.alt'), $room->name))]) ?></div>
		<?php endif; ?>
		<dl class="booking-detail-list">
			<?php foreach ($room_details as $detail): ?>
			<div><dt><?= html_escape($detail['label']) ?></dt><dd><?= $detail['value'] ?></dd></div>
			<?php endforeach; ?>
			<?php if (!empty($room->capacity)): ?>
			<div><dt><?= html_escape(lang('room.field.capacity')) ?></dt><dd><?= (int) $room->capacity ?></dd></div>
			<?php endif; ?>
		</dl>
	</div>
</section>
