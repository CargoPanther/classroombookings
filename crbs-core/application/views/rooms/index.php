<?php

echo $this->session->flashdata('saved');

$room_count = isset($rooms) && is_array($rooms) ? count($rooms) : 0;
if ($room_count === 0) {
	echo msgbox('info', lang('room.directory.no_items'));
	return;
}

$this->load->library('table');
$this->table->set_template([
	'table_open' => '<table class="border-table" width="100%" cellpadding="4" cellspacing="2" border="0">',
]);

$this->table->set_heading([
	lang('room.field.name'),
	lang('room_group.group'),
	lang('room.field.location'),
	lang('room.field.user_id'),
	lang('app.actions'),
]);

foreach ($rooms as $room) {
	$name = html_escape($room->name);
	$info_url = site_url('rooms/info/' . $room->room_id);
	$name_html = anchor($info_url, $name, [
		'up-layer' => 'new drawer',
		'up-position' => 'left',
		'up-target' => '.room-info',
		'up-preload' => '',
	]);

	$group_html = !empty($room->group->name) ? html_escape($room->group->name) : '';
	$location_html = html_escape($room->location);

	$owner_html = '';
	if (!empty($room->user_id)) {
		$owner = $room->owner->displayname ?? '';
		$owner_html = html_escape($owner ?: ($room->owner->username ?? ''));
	}

	$actions_html = iconbar([
		[
			'link' => $info_url,
			'name' => '',
			'title' => lang('app.action.view'),
			'icon' => 'info.png',
			'attrs' => [
				'up-layer' => 'new drawer',
				'up-position' => 'left',
				'up-target' => '.room-info',
				'up-preload' => '',
			],
		],
		[
			'link' => 'bookings/create/single?room_id=' . $room->room_id,
			'name' => '',
			'title' => lang('booking.book'),
			'icon' => 'school_manage_bookings.png',
		],
	]);

	$this->table->add_row([
		$name_html,
		$group_html,
		$location_html,
		$owner_html,
		$actions_html,
	]);
}

echo $this->table->generate();
