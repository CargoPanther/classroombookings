<?php

$items = [];

if (has_permission(Permission::SETUP_SESSIONS)) {
	$items[] = [
		'url' => site_url('sessions'),
		'title' => lang('session.sessions'),
		'active' => ($active ?? '') === 'sessions',
	];
}

if (has_permission(Permission::SETUP_SCHEDULES)) {
	$items[] = [
		'url' => site_url('schedules'),
		'title' => lang('schedule.schedules'),
		'active' => ($active ?? '') === 'schedules',
	];
}

if (has_permission(Permission::SETUP_TIMETABLE_WEEKS)) {
	$items[] = [
		'url' => site_url('weeks'),
		'title' => lang('week.timetable_weeks'),
		'active' => ($active ?? '') === 'weeks',
	];
}

if (!empty($items)) {
	$links = [];
	foreach ($items as $item) {
		$class = $item['active'] ? 'is-active' : '';
		$links[] = anchor($item['url'], html_escape($item['title']), ['class' => $class]);
	}
	echo '<nav class="buttonlist dates-times-nav" aria-label="Dates and Times">' . implode('', $links) . '</nav>';
}
