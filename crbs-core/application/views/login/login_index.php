<?php
echo form_open(current_url(), array('id' => 'login', 'class' => 'cssform login-form', 'data-remember-username' => ''), array('page' => $this->uri->uri_string()));
?>

<div class="login-card">
	<h1 class="login-title"><?= html_escape(sprintf(lang('auth.login_to'), $org_name)) ?></h1>

	<?php if (!empty($message)) : ?>
		<div class="login-notice"><?= nl2br((string) $message) ?></div>
	<?php endif; ?>
	<?= $error ?? '' ?>

	<?php if (!empty($microsoft_enabled)) : ?>
		<a href="<?= site_url('login/microsoft') ?>" class="login-microsoft-link"><?= lang('auth.microsoft.loginbutton') ?></a>
		<div class="login-divider"><span><?= lang('auth.or') ?></span></div>
	<?php endif; ?>

	<p class="login-field">
		<label for="username"><?= lang('user.field.username') ?></label>
		<?php
		echo form_input([
			'name' => 'username',
			'id' => 'username',
			'maxlength' => '255',
			'autocomplete' => 'username',
			'tabindex' => tab_index(),
			'value' => set_value('username', '', FALSE),
		]);
		?>
	</p>

	<p class="login-field">
		<label for="password"><?= lang('user.field.password') ?></label>
		<span class="login-password">
			<?php
			echo form_password([
				'name' => 'password',
				'id' => 'password',
				'autocomplete' => 'current-password',
				'tabindex' => tab_index(),
			]);
			?>
			<button type="button" class="login-password-toggle" data-password-toggle="password" aria-pressed="false" aria-label="<?= html_escape(lang('auth.password.show')) ?>" title="<?= html_escape(lang('auth.password.show')) ?>"><?= ui_icon('eye.png', ['width' => 18, 'height' => 18]) ?></button>
		</span>
	</p>

	<label class="login-remember" for="remember_username">
		<input type="checkbox" id="remember_username" data-remember-checkbox checked tabindex="<?= tab_index() ?>">
		<?= lang('auth.remember_username') ?>
	</label>

	<button type="submit" class="login-submit" tabindex="<?= tab_index() ?>"><?= lang('auth.log_in') ?></button>
</div>

<?php echo form_close(); ?>
