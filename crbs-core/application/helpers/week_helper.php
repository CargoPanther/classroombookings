<?php

/**
 * Generate a coloured dot for the given week
 *
 */
function week_dot($week, $size = 'md')
{
	$col = $week->bgcol;
	$col = str_replace('#', '', $col);
	$col = '#' . $col;
	$col = html_escape($col);

	$week_class = 'week-' . (int) $week->week_id . '-dot';
	$out = "<span class='dot dot-week dot-size-{$size} {$week_class}' style='background-color:{$col}'></span>";
	return $out;
}


/**
 * Generate CSS for a timetable week.
 *
 * @param $week DB row object for week entry.
 * @return string Raw CSS to place inside <style> tag.
 *
 */
function week_calendar_css($week)
{
	$CI =& get_instance();

	// $CI->benchmark->mark('week_calendar_css_start');

	$template = $CI->load->view('css/session-calendar.css', [], TRUE);
	$CI->load->library('parser');
	$CI->load->helper('colour');

	$fgcol = html_escape($week->fgcol);
	$bgcol = html_escape($week->bgcol);
	$dark_surface = colour_hex2rgb('#18221c');
	$neutral_surface = colour_hex2rgb('#17191d');
	$dark_bg = colour_rgb2hex(colour_mix(colour_hex2rgb($bgcol), $dark_surface, 0.22));
	$dark_range = colour_rgb2hex(colour_mix(colour_hex2rgb($bgcol), $dark_surface, 0.12));
	$dark_fg = '#e5eee8';
	$neutral_bg = colour_rgb2hex(colour_mix(colour_hex2rgb($bgcol), $neutral_surface, 0.22));
	$neutral_range = colour_rgb2hex(colour_mix(colour_hex2rgb($bgcol), $neutral_surface, 0.12));
	$neutral_fg = '#e5e7eb';

	$vars = [
		'week_id' => (int) $week->week_id,
		'fg_col' => $fgcol,
		'bg_col' => $bgcol,
		'range_bg' => colour_tint($bgcol, .1),
		'range_fg' => colour_shade($bgcol, .60),
		'dark_bg' => $dark_bg,
		'dark_range' => $dark_range,
		'dark_fg' => $dark_fg,
		'neutral_bg' => $neutral_bg,
		'neutral_range' => $neutral_range,
		'neutral_fg' => $neutral_fg,
	];

	$out = $CI->parser->parse_string($template, $vars, TRUE) . "\n";

	// $CI->benchmark->mark('week_calendar_css_end');

	return $out;
}

