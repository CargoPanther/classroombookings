<?php

$slot_params = $this->input->get('params') ?? '';
if ($this->input->post('original_params')) {
	parse_str((string) $this->input->post('original_params'), $original_vars);
	$slot_params = $original_vars['params'] ?? $slot_params;
}

$button = fn($label, $href, array $attrs = [], $class = '') => '<a href="' . html_escape($href) . '" class="booking-row-btn ' . $class . '"' . _stringify_attributes($attrs) . '>' . html_escape($label) . '</a>';

$rows = [];
foreach ($existing_bookings as $existing_booking) {
	$is_recurring = !empty($existing_booking->repeat_id);
	$name = lang('booking.capacity.reserved');
	if (booking_user_viewable($existing_booking) && !empty($existing_booking->user)) {
		$name = !empty($existing_booking->user->displayname)
			? $existing_booking->user->displayname
			: $existing_booking->user->username;
	}

	$words = preg_split('/\s+/u', trim((string) $name));
	$initials = count($words) > 1
		? mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1)
		: mb_substr($words[0], 0, 2);
	$initials = mb_strtoupper($initials);

	$places = max(1, (int) ($existing_booking->capacity ?? 1));
	$places_label = $places === 1 ? lang('booking.capacity.person') : lang('booking.capacity.people');
	$type_label = $is_recurring ? lang('booking.modal.type_recurring') : lang('booking.modal.type_single');

	$actions = [];
	if ($is_recurring) {
		$actions[] = $button(lang('app.action.view_all'), site_url(sprintf('bookings/view_series/%d?%s', $existing_booking->booking_id, http_build_query(['params' => $slot_params, 'shared_slot' => 1]))), [
			'up-layer' => 'current',
			'up-target' => '.bookings-create',
			'up-history' => 'false',
		]);
	}
	if (booking_editable($existing_booking)) {
		if ($is_recurring) {
			$edit_choices = $this->load->view('bookings/edit_choice', [
				'booking' => $existing_booking,
				'current_user' => $this->userauth->user,
				'params' => $slot_params,
				'shared_slot' => true,
			], TRUE);
			$actions[] = $button(lang('app.action.edit'), '#', [
				'up-layer' => 'new popup',
				'up-align' => 'right',
				'up-size' => 'medium',
				'up-content' => html_escape($edit_choices),
				'up-history' => 'false',
			]);
		} else {
			$actions[] = $button(lang('app.action.edit'), site_url('bookings/edit/' . $existing_booking->booking_id . '?' . http_build_query(['params' => $slot_params, 'shared_slot' => 1])), [
				'up-layer' => 'current',
				'up-target' => '.bookings-create',
				'up-preload' => '',
			]);
		}
	}
	if (booking_cancelable($existing_booking)) {
		$cancel_choices = $this->load->view('bookings/cancel_choice', [
			'booking' => $existing_booking,
			'current_user' => $this->userauth->user,
			'params' => $slot_params,
		], TRUE);
		$actions[] = $button(lang('app.action.cancel'), '#', [
			'up-layer' => 'new popup',
			'up-align' => 'right',
			'up-size' => 'medium',
			'up-content' => html_escape($cancel_choices),
			'up-class' => 'booking-choices-cancel',
			'title' => lang('booking.action.cancel_booking'),
		], 'is-danger');
	}

	$notes_html = (booking_notes_viewable($existing_booking) && !empty($existing_booking->notes))
		? '<div class="booking-row-notes">' . nl2br(html_escape($existing_booking->notes)) . '</div>'
		: '';

	$rows[] = '<li class="booking-row">'
		. '<span class="booking-row-avatar" aria-hidden="true">' . html_escape($initials) . '</span>'
		. '<div class="booking-row-body"><strong>' . html_escape($name) . '</strong>'
		. '<div>' . sprintf('%d %s', $places, html_escape($places_label)) . '</div>'
		. '<div class="booking-row-type">' . html_escape($type_label) . '</div>'
		. $notes_html . '</div>'
		. '<div class="booking-row-actions">' . implode('', $actions) . '</div>'
		. '</li>';
}

?>
<section class="booking-modal-card booking-modal-existing">
	<h3><?= html_escape(sprintf(lang('booking.modal.existing_title'), count($existing_bookings))) ?></h3>
	<p class="booking-modal-summary"><?= lang('booking.modal.existing_summary') ?></p>
	<ul class="booking-row-list"><?= implode('', $rows) ?></ul>
</section>
