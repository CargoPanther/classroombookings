<?php
// $view is populated by the BookingAgent class which handles all processing for creating bookings of various types.
?>

<div class="bookings-create">
	<?php if (!empty($title_html)): ?>
	<div class="booking-slot-heading" style="margin: 0 32px 16px 0"><?= $title_html ?></div>
	<?php else: ?>
	<h4 style="margin: 0 32px 16px 0"><?= $title ?></h4>
	<?php endif; ?>
	<?= $view ?>
</div>
