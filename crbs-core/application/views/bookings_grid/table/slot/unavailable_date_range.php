<td class="<?= $class ?>">
	<a href="#"
		class="bookings-grid-button"
		up-layer="new popup"
		up-align="top"
		up-size="medium"
		up-content="<p><?= html_escape($slot->label) ?></p><button up-dismiss><?= lang('app.ok') ?></button>"
	>
		<?php
		$icon = 'error.png';
		switch ($extended) {
			case 'past': $icon = 'date_previous.png'; break;
			case 'future': $icon = 'date_error.png'; break;
		}
		echo ui_icon($icon, ['title' => $slot->label]);
		?>
	</a>
</td>
