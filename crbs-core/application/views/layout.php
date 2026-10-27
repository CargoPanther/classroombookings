<?php

//

$global_menu = $this->menu_model->global();
$setup_navigation = '';
$current_uri = trim($this->uri->uri_string(), '/');
$current_uri = preg_replace('#^index\\.php/?#', '', $current_uri);
$setup_roots = ['departments', 'export', 'holidays', 'periods', 'roles', 'room_schedules', 'schedules', 'sessions', 'settings', 'setup', 'users', 'weeks'];
$is_setup_page = false;
foreach ($setup_roots as $root) {
	if ($current_uri === $root || str_starts_with($current_uri, $root . '/')) {
		$is_setup_page = true;
		break;
	}
}
if ($is_setup_page && has_setup_permission()) {
	$setup_navigation = $this->load->view('partials/setup_navigation', [
		'setup_menu' => $this->menu_model->setup_menu(),
		'current_path' => $current_uri,
	], TRUE);
}

?>

<!DOCTYPE html>
<html>

<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="author" content="Craig A Rodway">
<meta name="robots" content="noindex,nofollow">
<title><?= html_escape($title) ?> | classroombookings</title>
<?php if (config_item('csrf_protection')): ?>
<meta name="csrf-token-name" content="<?= html_escape($this->security->get_csrf_token_name()) ?>">
<meta name="csrf-token" content="<?= html_escape($this->security->get_csrf_hash()) ?>">
<?php endif; ?>
<script>
(function() {
	var theme = null;
	try {
		theme = localStorage.getItem('crbs-theme');
	} catch (error) {}
	if (theme !== 'light' && theme !== 'dark' && theme !== 'dark-neutral') {
		theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark-neutral' : 'light';
	}
	document.documentElement.setAttribute('data-theme', theme === 'light' ? 'light' : 'dark');
	document.documentElement.setAttribute('data-theme-variant', theme === 'dark-neutral' ? 'neutral' : 'standard');
})();
</script>
<?php
if (CRBS_MANAGED && setting('logo')) {
	echo "<link rel='preconnect' href='https://crbsimg.b-cdn.net'>\n";
}
$conf_suffix = '';
if (CRBS_MANAGED && config_item('asset_cdn_host')) {
	echo "<link rel='preconnect' href='https://cdn.classroombookings.net'>\n";
	$conf_suffix = '-cloud';
}
foreach ($css as $css_conf) {
	$url = asset_url($css_conf['path'], true);
	$fmt = "<link rel='stylesheet' type='text/css' media='%s' href='%s'>\n";
	echo sprintf($fmt, $css_conf['media'], $url);
}
foreach ($hs as $hs_config) {
	$src = asset_url($hs_config['path'], $hs_config['version']);
	$attrs = [];
	$attrs['type'] = 'text/hyperscript';
	if (isset($hs_config['defer']) && $hs_config['defer'] === true) {
		$attrs['defer'] = '';
	}
	echo script_src($src, $attrs);
}
foreach ($js as $js_conf) {
	$src = asset_url($js_conf['path'], $js_conf['version']);
	$attrs = [];
	if (isset($js_conf['defer']) && $js_conf['defer'] === true) {
		$attrs['defer'] = '';
	}
	echo script_src($src, $attrs);
}
?>
<link rel="apple-touch-icon" sizes="180x180" href="<?= asset_url('assets/brand/apple-touch-icon.png', true) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= asset_url('assets/brand/favicon-32x32.png', true) ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= asset_url('assets/brand/favicon-16x16.png', true) ?>">
<link rel="manifest" href="<?= asset_url(sprintf('assets/brand/site%s.webmanifest', $conf_suffix), true) ?>">
<link rel="mask-icon" href="<?= asset_url('assets/brand/safari-pinned-tab.svg', true) ?>" color="#ff6400">
<link rel="shortcut icon" href="<?= asset_url('assets/brand/favicon.ico', true) ?>">
<meta name="msapplication-TileColor" content="#ff6400">
<meta name="msapplication-config" content="<?= asset_url(sprintf('assets/brand/browserconfig%s.xml', $conf_suffix), true) ?>">
<meta name="theme-color" content="#ff6400">
<script type="text/javascript">
function ready(fn) {
	if (document.readyState !== "loading") {
		setTimeout(fn);
	} else {
		document.addEventListener("DOMContentLoaded", fn);
	}
}

var h = document.getElementsByTagName("html")[0];
(h ? h.classList.add('js') : h.className += ' ' + 'js');
var BASE_URL = "<?= base_url() ?>";
</script>

</head>
<body>

	<?php
	if (setting('maintenance_mode') == 1) {
		$message = setting('maintenance_mode_message');
		if (empty($message)) {
			$message = lang('app.maintenance_message');
		}
		echo "<div class='maintenance-wrapper'>";
		echo "<div class='outer'>";
		echo html_escape($message);
		echo "</div>";
		echo "</div>";
	}
	?>
	<header class="header">
		<div class="outer">

			<div class="block-group">

				<div class="block b-40 header-title">
					<div class="title">
						<?php
						$logo = img([
							'src' => asset_url('assets/images/crbs-logo-square.svg'),
							'width' => 24,
							'height' => 24,
							'alt' => 'classroombookings logo',
						]);
						$name = '';
						$output = '';
						$attrs = '';
						if (config_item('is_installed')) {
							$name = setting('name');
						}
						if (!empty($name)) {
							$name = html_escape($name);
						} else {
							$attrs = "title='classroombookings' style='font-weight:normal;color:#0081C2;letter-spacing:-1px'";
							$name = "classroom";
							$name .= "<span style='color:#ff6400;font-weight:bold'>bookings</span>";
						}
						echo anchor('/', $logo . $name, $attrs);
						?>
					</div>
				</div>

				<div class="block b-60 header-meta">
					<?php
					if ( ! empty($global_menu)) {
						echo "<div class='iconbar'>";
						foreach ($global_menu as $idx => $item) {
							$icon = ui_icon($item['icon']);
							$label = $icon . html_escape($item['label']);
							if (trim((string) parse_url($item['url'], PHP_URL_PATH), '/') === 'index.php/logout' || trim((string) $item['url'], '/') === 'logout') {
								echo form_open('logout', ['class' => 'header-logout-form']);
								echo form_button(['type' => 'submit', 'class' => 'header-logout-button', 'content' => $label]);
								echo form_close();
							} else {
								echo anchor($item['url'], $label);
							}
						}
						echo "</div>";
					}
					?>
				</div>

			</div>
		</div>
	</header>

	<div class="outer" up-main>
		<?= $setup_navigation ?>

		<?php if (isset($midsection)): ?>
			<div class="mid-section" align="center">
				<h1 style="font-weight:normal"><?php echo $midsection ?></h1>
			</div>
		<?php endif; ?>

		<div class="content_area">
			<?php
			if (isset($showtitle) && !empty($showtitle)) {
				echo '<h2>'.html_escape($showtitle).'</h2>';
			}
			echo $body;
			?>
		</div>

		<div class="footer">
			<div id="footer">
				<div class="footer-meta">
					<span>
						<a href="https://www.classroombookings.com/" target="_blank" rel="noopener">classroombookings</a>
						<?= strtolower(lang('app.version')) ?> <?= VERSION ?>.
						&copy; <?= date('Y') ?> Craig A Rodway.
					</span>
					<span><?php echo sprintf('%s: %s %s', lang('app.load_time'), $this->benchmark->elapsed_time(), lang('app.seconds')); ?></span>
					<div class="theme-controls">
						<!-- Keep the cycle toggle available in source if needed later.
						<button type="button" id="theme-toggle" class="theme-toggle" aria-pressed="false" aria-label="Switch to dark mode" title="Switch theme">
							<svg class="theme-toggle-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
								<path d="M9 18h6m-5 3h4m-5.5-6.5a7 7 0 1 1 7 0c-.8.6-1.3 1.4-1.5 2.5h-5c-.2-1.1-.7-1.9-1.5-2.5Z" />
								<path d="M12 2V1m7.8 3.2.7-.7M22 12h1M4.2 4.2l-.7-.7M2 12H1" />
							</svg>
						</button>
						-->
						<label class="theme-select-label" for="theme-select">Theme</label>
						<select id="theme-select" class="theme-select" aria-label="Choose theme">
							<option value="light">Light</option>
							<option value="dark">Dark green</option>
							<option value="dark-neutral">Dark neutral</option>
						</select>
					</div>
				</div>
			</div>
		</div>
	</div>

</body>
</html>
