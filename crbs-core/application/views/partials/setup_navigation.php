<?php

$path = trim((string) $current_path, '/');
$path = preg_replace('#^index\.php/?#', '', $path);
$group_order = ['users', 'resources', 'timetable', 'setup'];

?>
<nav class="setup-top-nav" aria-label="Setup">
	<?php foreach ($group_order as $group): ?>
		<?php if (empty($setup_menu[$group])) continue; ?>
		<div class="setup-nav-group">
			<span class="setup-nav-group-title"><?= html_escape(lang('setup.group.' . $group)) ?></span>
			<div class="setup-nav-links">
				<?php foreach ($setup_menu[$group] as $item): ?>
					<?php
					$item_path = trim((string) parse_url($item['url'], PHP_URL_PATH), '/');
					$item_path = preg_replace('#^index\.php/?#', '', $item_path);
					$is_active = $path === $item_path || str_starts_with($path, $item_path . '/');
					if ($group === 'resources' && $item_path === 'setup/rooms/groups' && (
						str_starts_with($path, 'setup/rooms/acl/')
						|| str_starts_with($path, 'setup/rooms/rooms/')
					)) {
						$is_active = $item_path === 'setup/rooms/groups';
					}
					if ($group === 'timetable' && $item_path === 'sessions' && (
						str_starts_with($path, 'room_schedules/')
						|| str_starts_with($path, 'holidays/')
					)) {
						$is_active = TRUE;
					}
					if ($group === 'timetable' && $item_path === 'schedules' && str_starts_with($path, 'periods/')) {
						$is_active = TRUE;
					}
					$attributes = [
						'class' => 'setup-nav-link' . ($is_active ? ' is-active' : ''),
					];
					if ($is_active) $attributes['aria-current'] = 'page';
					?>
					<?= anchor($item['url'], ui_icon($item['icon']) . '<span>' . html_escape($item['label']) . '</span>', $attributes) ?>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>
</nav>
