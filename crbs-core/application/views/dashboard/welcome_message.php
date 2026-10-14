<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="dashboard-welcome-message" data-hide-url="<?= site_url('dashboard/hide_welcome_message') ?>">
	<?php
	$welcome_text = trim((string) setting('welcome_message_text'));
	if ($welcome_text !== '') {
		echo '<div class="dashboard-welcome-body notice-info">' . nl2br(html_escape($welcome_text)) . '</div>';
	} else {
	}
	?>
	<div class="dashboard-welcome-actions" <?php if ($welcome_text == '') {echo "style='display:none'";} ?>>
		<button type="button" class="dashboard-welcome-dismiss"><?= lang('app.ok') ?></button>
		<button type="button" class="dashboard-welcome-never never-show button-normal">Never show again</button>
	</div>
</div>
