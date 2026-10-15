<?php

defined('BASEPATH') OR exit('No direct script access allowed');


function field($validation, $database = NULL, $last = ''){
	$value = $validation ?? $database ?? $last;
	return $value;
}


function buttonlist($items = array()) {
	$out = '';
	$links = [];

	foreach ($items as $item) {
		if (is_null($item)) continue;
		$is_active = isset($item['active']) && $item['active'] == true;
		$url = $item['url'];
		$title = $item['title'];
		$attrs = $item['attrs'] ?? null;
		if (is_array($attrs)) {
			$attrs = _stringify_attributes($attrs);
		}
		$class = ($is_active) ? 'is-active' : '';
		$link = anchor($url, $title, "class='{$class}' {$attrs}");
		$link = str_replace(site_url('#'), '#', $link);
		$links[] = $link;
	}

	$items_html = implode("", $links);

	$out = "<nav class='buttonlist'>{$items_html}</nav>";
	return $out;
}


function ui_icon($name, array $attributes = [])
{
	$name = strtolower(basename((string) $name));
	$aliases = [
		'alert' => 'alert', 'info' => 'info',
		'calendar.png' => 'calendar', 'calendar_view_day.png' => 'calendar', 'calendar_view_month.png' => 'calendar', 'cal_day.png' => 'calendar',
		'arrow_refresh.png' => 'refresh', 'table.png' => 'list',
		'date_error.png' => 'calendar', 'date_next.png' => 'calendar', 'date_previous.png' => 'calendar',
		'school_manage_rooms.png' => 'room', 'school_manage_details.png' => 'info', 'info.png' => 'info',
		'user.png' => 'user', 'user_administrator.png' => 'user', 'user_teacher.png' => 'user', 'user_edit.png' => 'user', 'user_go.png' => 'user', 'school_manage_users.png' => 'user',
		'logout.png' => 'logout', 'school_manage_settings.png' => 'settings',
		'add.png' => 'plus', 'edit.png' => 'edit', 'delete.png' => 'trash', 'enabled.png' => 'check', 'accept.png' => 'check', 'tick.png' => 'check', 'check-checked-green_delete.png' => 'check',
		'no.png' => 'close', 'cancel.png' => 'close', 'error.png' => 'alert', 'stop.png' => 'alert',
		'lock.png' => 'lock', 'key.png' => 'lock', 'server_key.png' => 'lock', 'vcard_key.png' => 'user',
		'folder.png' => 'folder', 'eye.png' => 'eye',
		'arrow_left.png' => 'arrow-left', 'arrow_turn_left.png' => 'arrow-left', 'arrow_undo.png' => 'arrow-left', 'resultset_first.png' => 'arrow-left', 'resultset_previous.png' => 'arrow-left', 'rewind_blue.png' => 'arrow-left', 'rewind_green.png' => 'arrow-left',
		'arrow_right.png' => 'arrow-right', 'resultset_last.png' => 'arrow-right', 'resultset_next.png' => 'arrow-right', 'forward_blue.png' => 'arrow-right', 'forward_green.png' => 'arrow-right',
		'school_manage_departments.png' => 'group', 'school_manage_times.png' => 'clock', 'clock.png' => 'clock',
		'disk.png' => 'save', 'arrow_ns.png' => 'grip', 'picture.png' => 'image', 'photo.png' => 'image', 'world.png' => 'globe',
		'room_fields.png' => 'list', 'school_manage_weeks.png' => 'calendar', 'school_manage_holidays.png' => 'sun', 'school_manage_timetable.png' => 'calendar',
		'comment.png' => 'message', 'report.png' => 'document', 'pdf.png' => 'document', 'page_copy.png' => 'document', 'user_import.png' => 'upload', 'email_go.png' => 'mail',
		'chart.png' => 'chart', 'chart_bar.png' => 'chart', 'chart_curve.png' => 'chart', 'chart_line.png' => 'chart', 'chart_pie.png' => 'chart',
		'cake.png' => 'star', 'bug.png' => 'alert', 'shield.png' => 'lock', 'link_controlpanel.png' => 'settings', 'link_help.png' => 'info',
	];

	$type = $aliases[$name] ?? 'document';
	$shapes = [
		'calendar' => '<rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M7.5 3.5v3M16.5 3.5v3M3.5 9.5h17M8 13h.01M12 13h.01M16 13h.01M8 17h.01M12 17h.01"/>',
		'room' => '<path d="M3 21h18M5 21V4h10v17M15 9h4v12M8 8h3M8 12h3M8 16h3M17 13h1M17 17h1"/>',
		'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
		'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c.6-3.3 3.5-5.5 7.5-5.5s6.9 2.2 7.5 5.5"/>',
		'logout' => '<path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5M14 8l4 4-4 4M8 12h10"/>',
		'settings' => '<path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6 7 7M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
		'plus' => '<path d="M12 5v14M5 12h14"/>',
		'edit' => '<path d="m4 16.5-.8 4.3 4.3-.8L19 8.5 15.5 5 4 16.5Z"/><path d="m13.8 6.7 3.5 3.5"/>',
		'trash' => '<path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/>',
		'check' => '<path d="m5 12.5 4.5 4.5L19 7"/>',
		'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
		'alert' => '<path d="M12 3 2.8 19h18.4L12 3Z"/><path d="M12 9v4M12 16h.01"/>',
		'lock' => '<rect x="4.5" y="10" width="15" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>',
		'folder' => '<path d="M3 6a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/>',
		'eye' => '<path d="M2.5 12s3.2-6 9.5-6 9.5 6 9.5 6-3.2 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/>',
		'arrow-left' => '<path d="M19 12H5M12 5l-7 7 7 7"/>',
		'arrow-right' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
		'refresh' => '<path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9a7 7 0 0 1 11.8-2L20 12M4 12l2.6 5a7 7 0 0 0 11.8-2"/>',
		'group' => '<circle cx="9" cy="8" r="3"/><path d="M3.5 20c.4-3 2.4-5 5.5-5s5.1 2 5.5 5M16 5.5a3 3 0 0 1 0 5.8M17 15c2.2.6 3.3 2.2 3.5 5"/>',
		'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
		'save' => '<path d="M4 3h13l3 3v15H4V3Z"/><path d="M8 3v6h8V3M8 21v-8h8v8"/>',
		'grip' => '<circle cx="8" cy="6" r=".8"/><circle cx="16" cy="6" r=".8"/><circle cx="8" cy="12" r=".8"/><circle cx="16" cy="12" r=".8"/><circle cx="8" cy="18" r=".8"/><circle cx="16" cy="18" r=".8"/>',
		'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m4 17 5-5 3 3 3-4 5 6"/>',
		'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>',
		'list' => '<path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/>',
		'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		'message' => '<path d="M4 5h16v12H9l-5 4V5Z"/><path d="M8 9h8M8 13h5"/>',
		'document' => '<path d="M6 3h8l4 4v14H6V3Z"/><path d="M14 3v5h5M9 12h6M9 16h6"/>',
		'upload' => '<path d="M12 16V4M7 9l5-5 5 5M4 15v5h16v-5"/>',
		'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
		'chart' => '<path d="M4 20V4M4 20h17M8 16v-5M13 16V7M18 16v-8"/>',
		'star' => '<path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z"/>',
	];

	$attributes = array_merge([
		'class' => 'ui-icon',
		'viewBox' => '0 0 24 24',
		'width' => '18',
		'height' => '18',
		'fill' => 'none',
		'stroke' => 'currentColor',
		'stroke-width' => '1.8',
		'stroke-linecap' => 'round',
		'stroke-linejoin' => 'round',
		'aria-hidden' => 'true',
		'focusable' => 'false',
	], $attributes);
	$aria_label = $attributes['aria-label'] ?? $attributes['title'] ?? '';
	if ($aria_label !== '') {
		unset($attributes['aria-hidden']);
		$attributes['role'] = 'img';
		$attributes['aria-label'] = $aria_label;
	}

	return '<svg' . _stringify_attributes($attributes) . '>' . ($shapes[$type] ?? $shapes['document']) . '</svg>';
}



function iconbar($items = array(), $active = false) {

	$html = "<div class='iconbar'>";
	$i = 1;
	$max = count($items);

	foreach ($items as $item) {

		if (is_null($item)) {
			$max--;
			continue;
		}

		$attrs = '';

		if (isset($item['link'])) {
			extract($item);
		} else {
			[$link, $name, $icon] = $item;
		}

		$escape = true;
		if (isset($item['escape']) && $item['escape'] === false) {
			$escape = false;
		}

		$name = $escape ? html_escape($name) : $name;

		if (is_array($attrs)) {
			$attrs = _stringify_attributes($attrs);
		}

		if (isset($title)) {
			$title = html_escape($title);
			$attrs .= " title='$title'";
		}

		$class = ($link == $active)
			? 'active'
			: '';

		$img = ui_icon($icon);

		$count = '';
		if (isset($item['count'])) {
			$count_val = html_escape($item['count']);
			$count = "<span class='count'>({$count_val})</span>";
		}

		$meta = '';
		if (isset($item['meta'])) {
			$meta = "<span class='count'>{$item['meta']}</span>";
		}

		$label = anchor($link, "{$img} {$name}{$count}{$meta}", "class='{$class}' {$attrs}");
		$label = str_replace(site_url('#'), '#', $label);

		$html .= $label;

		$i++;
	}

	$html .= "</div>";

	return $html;
}


function tab_index($reset = false)
{
	static $_tab_index;

	if (empty($_tab_index) || $reset === true) {
		$_tab_index = 0;
	} else {
		$_tab_index++;
	}

	return $_tab_index;
}




function msgbox($type = 'error', $content = '', $escape = TRUE)
{
	if ($escape)
	{
		$content = nl2br(html_escape($content));
	}

	$type_classes = preg_split('/\s+/', strtolower((string) $type));
	$icon = in_array('error', $type_classes, TRUE) || in_array('exclamation', $type_classes, TRUE)
		? 'alert'
		: 'info';
	$icon = ui_icon($icon, ['class' => 'ui-icon msgbox-icon', 'width' => '16', 'height' => '16']);
	$html = "<div class='msgbox {$type}'>{$icon}<span class='msgbox-content'>{$content}</span></div>";
	return $html;
}


function sort_link($base_uri, $param, $label)
{
	$CI =& get_instance();
	$get_data = $CI->input->get();
	$get_sort = $CI->input->get('sort') ?? '';
	$get_sort_field = ltrim($get_sort, '-');

	$get_data['sort'] = $param;

	if ($get_sort == $param) {
		$get_data['sort'] = "-{$param}";
	}

	$suffix = '';
	if ($get_sort == $param) {
		$suffix = '<span class="sort-arr">&#11205;</span>';
	} elseif ($get_sort == "-{$param}") {
		$suffix = '<span class="sort-arr">&#11206;</span>';
	}

	$query = http_build_query($get_data);
	$uri = $base_uri . '?' . $query;

	return anchor(site_url($uri), $label . $suffix, ['class' => 'sort-link']);
}


/**
 * script_src
 *
 * Render a <script> tag
 *
 * @access	public
 * @param	type	name
 * @return	type
 */
if (! function_exists('script_src')) {
	function script_src($src = '', $attributes = [])
	{
		$out = '<script';
		$attributes['src'] = $src;
		$out .= _stringify_attributes($attributes);
		$out .= "></script>\n";
		return $out;
	}
}


function date_picker_img($input_name)
{
	$hs = <<<EOS
on click call displayDatePicker('{$input_name}', false)
EOS;

	$img = ui_icon('cal_day.png', [
		'style' => 'cursor:pointer',
		'title' => lang('app.choose_date'),
		'script' => $hs,
	]);

	return $img;
}



function render_list_builder(array $params = [])
{
	$CI =& get_instance();
	$available_options = [];
	$selected_options = [];
	$value = $params['value'] ?? [];

	foreach (($params['options'] ?? []) as $id => $label) {
		// if (array_key_exists($id, $))
		if (in_array($id, $params['value'])) {
			$selected_options[$id] = $label;
		} else {
			$available_options[$id] = $label;
		}
	}

	$params['available_options'] = $available_options;
	$params['selected_options'] = $selected_options;

	return $CI->load->view('partials/list_builder', $params, true);
}

/*
function icon($name, $attributes = array())
{
	$CI =& get_instance();
	return $CI->feather->get($name, $attributes, FALSE);
}
*/
