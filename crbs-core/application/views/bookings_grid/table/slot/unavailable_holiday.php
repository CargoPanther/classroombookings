<td class="<?= $class ?>">
	<a href="#"
		class="bookings-grid-button"
		up-layer="new popup"
		up-align="top"
		up-size="medium"
		up-content="<p><?= html_escape($slot->label) ?></p><button up-dismiss><?= lang('app.ok') ?></button>"
	>
		<?php
		echo ui_icon('school_manage_holidays.png', ['title' => lang('holiday.holiday')]);
		?>
	</a>
</td>
