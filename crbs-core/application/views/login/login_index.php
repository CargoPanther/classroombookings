<?php
echo form_open(current_url(), array('id' => 'login', 'class' => 'cssform login-form'), array('page' => $this->uri->uri_string()));
?>

<div class="login-card">
	<?php if (!empty($message)) : ?>
		<div class="login-notice"><?= nl2br((string) $message) ?></div>
	<?php endif; ?>
	<?= $error ?? '' ?>
	<?= validation_errors() ?>

	<fieldset class="login-fieldset">
		<legend accesskey="L" tabindex="<?= tab_index() ?>"><?= lang('auth.log_in') ?></legend>

		<p class="login-field">
			<label for="username" class="required"><?= lang('user.field.username') ?></label>
			<?php
			$value = set_value('username', '', FALSE);
			echo form_input([
				'name' => 'username',
				'id' => 'username',
				'size' => '20',
				'maxlength' => '255',
				'tabindex' => tab_index(),
				'value' => $value,
			]);
			?>
		</p>

		<p class="login-field">
			<label for="password" class="required"><?= lang('user.field.password') ?></label>
			<?php
			echo form_password([
				'name' => 'password',
				'id' => 'password',
				'size' => '20',
				'tabindex' => tab_index(),
			]);
			?>
		</p>

		<?php $this->load->view('partials/submit', ['submit' => [lang('auth.log_in'), tab_index()]]); ?>
	</fieldset>

	<?php if (!empty($microsoft_enabled)) : ?>
		<fieldset class="login-fieldset login-fieldset--microsoft">
			<legend accesskey="M" tabindex="<?= tab_index() ?>"><?= lang('auth.externallogintitle') ?></legend>
			<p class="login-field">
				<a href="<?= site_url('login/microsoft') ?>" class="button login-microsoft-link"><?= lang('auth.microsoft.loginbutton') ?></a>
			</p>
		</fieldset>
	<?php endif; ?>
</div>

<?php echo form_close(); ?>
