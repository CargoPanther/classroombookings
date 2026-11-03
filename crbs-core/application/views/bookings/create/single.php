<?php

// Generate iconbar menu for choosing single/recurring booking type
//

$params = $this->input->get();
if ($this->input->post('original_params')) {
	$p = $this->input->post('original_params');
	parse_str((string) $p, $vars);
	$params = $vars;
}
$params['step'] = 'details';

$types = [];
if (empty($has_current_user_booking) && empty($single_create_unavailable)) {
	if ($can_book_single) {
		$types['single'] = lang('booking.modal.type_single');
	}
	if ($can_book_recur) {
		$types['recurring'] = lang('booking.modal.type_recurring');
	}
}

// Slot is only viewable: show the notice instead of a create form
$notice_only = $subview === 'bookings/create/single/single_form'
	&& (!empty($has_current_user_booking) || !empty($single_create_unavailable))
	&& empty($admin_add_for_other);

?>
<div class="booking-modal-body">

	<div class="booking-modal-col">
		<?php
		if (!empty($existing_bookings)) {
			$this->load->view('bookings/create/single/existing');
		}
		$this->load->view('bookings/create/single/room_details');
		?>
	</div>

	<div class="booking-modal-col">
		<section class="booking-modal-card booking-modal-create">
			<?php if (!$notice_only): ?>
			<h3><?= lang('booking.modal.create_title') ?></h3>
			<p class="booking-modal-summary"><?= lang('booking.modal.create_summary') ?></p>
			<?php endif; ?>

			<?php if (count($types) > 1): ?>
			<div class="booking-type-choice" role="radiogroup" aria-label="<?= html_escape(lang('booking.modal.type')) ?>">
				<div class="booking-type-label"><?= lang('booking.modal.type') ?></div>
				<?php foreach ($types as $type => $type_label): ?>
				<a
					href="<?= html_escape(current_url() . '?' . http_build_query(array_merge($params, ['booking_type' => $type]))) ?>"
					class="booking-type-option<?= $booking_type === $type ? ' is-active' : '' ?>"
					role="radio"
					aria-checked="<?= $booking_type === $type ? 'true' : 'false' ?>"
					up-target=".bookings-create"
				><span class="booking-type-radio"></span><?= html_escape($type_label) ?></a>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<?php
			if ($notice_only) {
				echo msgbox('notice large', $booking_slot_notice);
			} else {

				if ($message) {
					echo msgbox('error large', $message);
				}

				if ($permit_booking) {

					// Enclose subview in form
					//

					$attrs = [
						'id' => 'bookings_create_single',
						'class' => 'cssform booking-create-single',
						'up-accept-location' => 'bookings',
						'up-layer' => 'any',
						'up-target' => '.bookings-create',
					];

					echo form_open(current_url(), $attrs, ['original_params' => http_build_query($this->input->get())]);
					$this->load->view($subview);
					echo form_close();

				} else {

					$this->load->view($subview);

				}
			}
			?>
		</section>
	</div>

</div>
