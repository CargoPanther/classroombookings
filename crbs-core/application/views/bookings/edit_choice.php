<?php

if ($booking->user_id && $current_user->user_id != $booking->user_id) {
	echo msgbox('exclamation', lang('booking.warning.not_own'));
	echo "<br>";
}

$cls = '';

$heading = '<strong>' . lang('booking.edit.recurring.title') . '</strong><br><br>';

$cls = 'is-repeat';


$buttons = [];
$edit_target = !empty($shared_slot) ? '.bookings-create' : '.bookings-edit';
$edit_source = !empty($shared_slot) ? ['shared_slot' => 1] : [];

$uri = sprintf('bookings/edit/%d?%s', $booking->booking_id, http_build_query(array_merge(['params' => $params, 'edit' => '1'], $edit_source)));
$buttons[] = form_button([
	'type' => 'button',
	'content' => lang('booking.selection.this_only'),
	'up-href' => site_url($uri),
	'up-target' => $edit_target,
	'up-layer' => 'parent',
	'up-mode' => 'modal',
]);

$uri = sprintf('bookings/edit/%d?%s', $booking->booking_id, http_build_query(array_merge(['params' => $params, 'edit' => 'future'], $edit_source)));
$buttons[] = form_button([
	'type' => 'button',
	'content' => lang('booking.selection.future'),
	'up-href' => site_url($uri),
	'up-target' => $edit_target,
	'up-layer' => 'parent',
]);

$uri = sprintf('bookings/edit/%d?%s', $booking->booking_id, http_build_query(array_merge(['params' => $params, 'edit' => 'all'], $edit_source)));
$buttons[] = form_button([
	'type' => 'button',
	'content' => lang('booking.selection.all'),
	'up-href' => site_url($uri),
	'up-target' => $edit_target,
	'up-layer' => 'parent',
]);

$cancel = "<br><br><a href='#' up-dismiss>" . lang('app.action.cancel') . "</a>";

$content = implode("\n", $buttons) . $cancel;

echo "<div class='booking-choices'>";
echo $heading;
echo "<div class='submit' style='border-top:0px;'>{$content}</div>";
echo "</div>";
