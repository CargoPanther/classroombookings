<?php

echo $this->session->flashdata('saved');

$room_count = isset($rooms) && is_array($rooms) ? count($rooms) : 0;
if ($room_count === 0) {
	echo msgbox('info', lang('room.directory.no_items'));
	return;
}

$this->load->library('table');
$this->table->set_template([
	'table_open' => '<table class="border-table" style="width:100%;table-layout:fixed" cellpadding="4" cellspacing="2" border="0">',
	'heading_row_start' => '<tr class="heading">',
]);

$this->table->set_heading([
	['data' => lang('room.field.name'), 'width' => '30%'],
	['data' => lang('room_group.group'), 'width' => '20%'],
	['data' => lang('room.field.location'), 'width' => '25%'],
	['data' => lang('room.field.user_id'), 'width' => '25%'],
]);

foreach ($rooms as $room) {
	$name = html_escape($room->name);
	$info_icon = img(asset_url('assets/images/ui/info.png'), FALSE, [
		'alt' => '',
		'aria-hidden' => 'true',
		'style' => 'margin-right:5px;vertical-align:middle',
	]);
	$info_url = site_url('rooms/info/' . $room->room_id);
	$link_attributes = [
		'class' => 'room-directory-link',
		'style' => 'display:block;min-height:1.5em;color:inherit;text-decoration:none;cursor:pointer',
		'title' => lang('app.action.view'),
		'up-layer' => 'new drawer',
		'up-position' => 'left',
		'up-target' => '.room-info',
		'up-preload' => '',
	];

	$group_html = !empty($room->group->name) ? html_escape($room->group->name) : ' ';
	$location_html = html_escape($room->location) ?: ' ';

	$owner_html = '';
	if (!empty($room->user_id)) {
		$owner = $room->owner->displayname ?? '';
		$owner_html = html_escape($owner ?: ($room->owner->username ?? ''));
	}
	$owner_html = $owner_html ?: ' ';

	$this->table->add_row([
		anchor($info_url, $info_icon . $name, $link_attributes),
		anchor($info_url, $group_html, $link_attributes),
		anchor($info_url, $location_html, $link_attributes),
		anchor($info_url, $owner_html, $link_attributes),
	]);
}

echo $this->table->generate();
