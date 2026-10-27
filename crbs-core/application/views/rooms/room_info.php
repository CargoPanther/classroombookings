<div class='room-info' data-style="min-width: 320px">

	<?php
	$photo_html = '';
	$fields_html = '';

	$this->table->set_template([
		'table_open' => '<table class="zebra-table" width="100%" cellpadding="6" cellspacing="0" border="0">',
	]);

	foreach ($room_info as $row) {
		$this->table->add_row(html_escape($row['label']), $row['value']);
	}
	if ( ! empty($room->capacity)) {
		$this->table->add_row(html_escape(lang('room.field.capacity')), (int) $room->capacity);
	}

	$fields_html = $this->table->generate();

	if ($photo_url) {
		$img = img($photo_url);
		$photo_html = "<div class='room-photo'>{$img}</div>";
	}

	echo '<section class="booking-detail-section booking-room-details"><h3>' . html_escape(lang('room.room_details')) . '</h3>';
	echo '<h4>' . html_escape($room->name) . '</h4>';

	if ( ! empty($room_info)) {
		echo $fields_html;
	} else {
		echo "<p><em>No details available.</em></p>";
	}

	echo $photo_html;
	echo '</section>';
	?>

</div>
