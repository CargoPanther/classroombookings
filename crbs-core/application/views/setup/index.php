<?php

echo $this->session->flashdata('saved');

$order = [
	'users',
	'resources',
	'timetable',
	'setup',
];

echo "<div class='block-group setup-menu'>";

foreach ($order as $group) {
	if ( ! isset($setup_menu[$group])) continue;
	$items = $setup_menu[$group];
	echo "<div class='block b-25'>";
	$title = lang('setup.group.'.$group);
	echo "<h3 class='setup-menu-heading'>{$title}</h3>";
	echo "<ul>";
	if ($items !== null) {
		foreach ($items as $link) {
			echo "<li>";
			echo '<a href="'.$link['url'].'" class="setup-menu-link">';
			echo ui_icon($link['icon'], ['width' => '16', 'height' => '16']);
			echo html_escape($link['label']);
			echo '</a>';
			echo "</li>";
		}
	}
	echo "</ul>";
	echo "</div>";
}
echo "</div>";
