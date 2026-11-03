<?php

$prefs = grid_layout_prefs();
$layout = isset($prefs['displaytype'], $prefs['d_columns']) ? $prefs['displaytype'] . '.' . $prefs['d_columns'] : '';
$highlight = $prefs['grid_highlight'] ?? '';

$layout_options = [
	'' => lang('booking.layout.default'),
	'day.periods' => lang('booking.layout.day.periods'),
	'day.rooms' => lang('booking.layout.day.rooms'),
	'room.periods' => lang('booking.layout.room.periods'),
	'room.days' => lang('booking.layout.room.days'),
];

$highlight_options = [
	'' => lang('booking.layout.default'),
	'1' => lang('booking.layout.highlight.on'),
	'0' => lang('booking.layout.highlight.off'),
];

echo form_open('bookings/layout', ['class' => 'grid-options'], ['params' => $params]);

$fields = [
	[
		'id' => 'grid_layout',
		'label' => lang('booking.layout.label'),
		'help' => lang('booking.layout.tip'),
		'input' => form_dropdown(['name' => 'layout', 'id' => 'grid_layout', 'options' => $layout_options, 'selected' => $layout, 'data-autosubmit' => '']),
	],
	[
		'id' => 'grid_highlight_pref',
		'label' => lang('booking.layout.highlight'),
		'help' => lang('booking.layout.highlight.tip'),
		'input' => form_dropdown(['name' => 'highlight', 'id' => 'grid_highlight_pref', 'options' => $highlight_options, 'selected' => (string) $highlight, 'data-autosubmit' => '']),
	],
	[
		'id' => 'grid_multi_select',
		'label' => lang('booking.layout.multi'),
		'help' => lang('booking.layout.multi.tip'),
		'input' => form_dropdown(['id' => 'grid_multi_select', 'options' => ['0' => lang('booking.layout.highlight.off'), '1' => lang('booking.layout.highlight.on')], 'selected' => '0', 'data-multi-select' => '']),
	],
];
?>
<header class="booking-modal-header">
	<div class="booking-modal-icon"><?= ui_icon('sliders.png', ['width' => 28, 'height' => 28]) ?></div>
	<div class="booking-modal-heading">
		<h2><?= lang('booking.layout.title') ?></h2>
	</div>
	<button type="button" class="booking-modal-close" up-dismiss aria-label="<?= html_escape(lang('booking.modal.close')) ?>">&times;</button>
</header>
<div class="booking-modal-body">
	<section class="booking-modal-card">
		<?php foreach ($fields as $field): ?>
		<div class="grid-options-field">
			<label for="<?= $field['id'] ?>"><?= html_escape($field['label']) ?></label>
			<?= $field['input'] ?>
			<p class="hint"><?= html_escape($field['help']) ?></p>
		</div>
		<?php endforeach; ?>
	</section>
</div>
<noscript><button type="submit"><?= lang('app.ok') ?></button></noscript>
<?= form_close() ?>
