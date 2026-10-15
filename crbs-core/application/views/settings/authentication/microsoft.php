<?php

if (is_demo_mode()) {
	echo msgbox('notice large', lang('auth.microsoft.demo_notice'));
}

echo form_open(current_url(), array('id' => 'microsoft_settings', 'class' => 'cssform', 'microsoft-settings' => ''));
?>

<?php
echo validation_errors();
if (! empty($save_error)) {
	echo $save_error;
}
?>

<div class="hint">
	<h3><?= lang('auth.microsoft.setup.title') ?></h3>
	<ol>
		<li><?= lang('auth.microsoft.setup.step.1') ?></li>
		<li><?= lang('auth.microsoft.setup.step.2') ?></li>
		<li><?= lang('auth.microsoft.setup.step.3') ?></li>
		<li><?= lang('auth.microsoft.setup.step.4') ?></li>
		<li><?= lang('auth.microsoft.setup.step.5') ?></li>
		<li><?= lang('auth.microsoft.setup.step.6') ?></li>
	</ol>
</div>

<fieldset>

	<legend accesskey="M" tabindex="<?php echo tab_index() ?>"><?= lang('auth.microsoft.title') ?></legend>

	<?php
	$field = 'auth_microsoft_enabled';
	$value = set_value($field, element($field, $settings, '0'), FALSE);
	?>
	<p>
		<label for="<?= $field ?>"><?= lang('auth.microsoft.field.enable') ?></label>
		<?php
		echo form_hidden($field, '0');
		$input_options = [
			'name' => $field,
			'id' => $field,
			'value' => '1',
			'tabindex' => tab_index(),
		];
		if (is_demo_mode()) {
			$input_options['checked'] = FALSE;
			$input_options['disabled'] = 'disabled';
		} else {
			$input_options['checked'] = ($value == '1');
		}
		echo form_checkbox($input_options);
		?>
	</p>
	<?php echo form_error($field) ?>

	<?php
	$field = 'auth_microsoft_auto_login';
	$value = set_value($field, element($field, $settings, '0'), FALSE);
	?>
	<p>
		<label for="<?= $field ?>"><?= lang('auth.microsoft.field.auto_login') ?></label>
		<?php
		echo form_hidden($field, '0');
		echo form_checkbox(array(
			'name' => $field,
			'id' => $field,
			'value' => '1',
			'tabindex' => tab_index(),
			'checked' => ($value == '1')
		));
		?>
		<p class="hint">
			<?= lang('auth.microsoft.field.auto_login_hint') ?>
			<strong><?= html_escape(site_url('login/local')) ?></strong>
		</p>
	</p>
	<?php echo form_error($field) ?>

</fieldset>

<fieldset>

	<legend accesskey="C" tabindex="<?php echo tab_index() ?>"><?= lang('auth.microsoft.configuration') ?></legend>

	<?php
	$field = 'auth_microsoft_client_id';
	$value = set_value($field, element($field, $settings), FALSE);
	?>
	<p>
		<label for="<?= $field ?>"><?= lang('auth.microsoft.field.client_id') ?></label>
		<?php
		echo form_input(array(
			'name' => $field,
			'id' => $field,
			'size' => '80',
			'maxlength' => '255',
			'tabindex' => tab_index(),
			'value' => $value,
		));
		?>
	</p>
	<?php echo form_error($field) ?>

	<?php
	$field = 'auth_microsoft_client_secret';
	$value = set_value($field, element($field, $settings), FALSE);
	?>
	<p>
		<label for="<?= $field ?>"><?= lang('auth.microsoft.field.client_secret') ?></label>
		<?php
		echo form_password(array(
			'name' => $field,
			'id' => $field,
			'size' => '80',
			'maxlength' => '255',
			'tabindex' => tab_index(),
			'value' => $value,
		));
		?>
	</p>
	<?php echo form_error($field) ?>

	<?php
	$field = 'auth_microsoft_redirect_uri';
	$value = set_value($field, element($field, $settings, site_url('login/microsoft')), FALSE);
	$callback_uri = site_url('login/microsoft');
	?>
	<p>
		<label for="<?= $field ?>"><?= lang('auth.microsoft.field.redirect_uri') ?></label>
		<?php
		echo form_input(array(
			'name' => $field,
			'id' => $field,
			'size' => '80',
			'maxlength' => '1024',
			'tabindex' => tab_index(),
			'value' => $value,
		));
		?>
		<p class="hint"><?= lang('auth.microsoft.field.redirect_uri_hint') ?></p>
		<p class="hint"><strong><?= lang('auth.microsoft.redirect_uri.current') ?></strong> <?= html_escape($callback_uri) ?></p>
	</p>
	<?php echo form_error($field) ?>

	<?php
	$field = 'auth_microsoft_authority';
	$value = set_value($field, element($field, $settings, ''), FALSE);
	?>
	<p>
		<label for="<?= $field ?>"><?= lang('auth.microsoft.field.tenant_id') ?></label>
		<?php
		echo form_input(array(
			'name' => $field,
			'id' => $field,
			'size' => '80',
			'maxlength' => '255',
			'tabindex' => tab_index(),
			'value' => $value,
		));
		?>
		<p class="hint"><?= lang('auth.microsoft.field.tenant_id_hint') ?></p>
	</p>
	<?php echo form_error($field) ?>

	<?php
	$field = 'auth_microsoft_scopes';
	$value = set_value($field, element($field, $settings, 'User.Read'), FALSE);
	?>
	<p>
		<label for="<?= $field ?>"><?= lang('auth.microsoft.field.scopes') ?></label>
		<?php
		echo form_input(array(
			'name' => $field,
			'id' => $field,
			'size' => '80',
			'maxlength' => '255',
			'tabindex' => tab_index(),
			'value' => $value,
		));
		?>
		<p class="hint"><?= lang('auth.microsoft.field.scopes_hint') ?></p>
	</p>
	<?php echo form_error($field) ?>

</fieldset>

<fieldset>

	<legend accesskey="U" tabindex="<?php echo tab_index() ?>"><?= lang('auth.microsoft.user_provisioning') ?></legend>

	<?php
	$field = 'auth_microsoft_create_users';
	$value = set_value($field, element($field, $settings, '0'), FALSE);
	?>
	<p>
		<label for="<?= $field ?>"><?= lang('auth.microsoft.field.create_users') ?></label>
		<?php
		echo form_hidden($field, '0');
		echo form_checkbox(array(
			'name' => $field,
			'id' => $field,
			'value' => '1',
			'tabindex' => tab_index(),
			'checked' => ($value == '1')
		));
		?>
		<p class="hint"><?= lang('auth.microsoft.field.create_users_hint') ?></p>
	</p>
	<?php echo form_error($field) ?>

	<?php
	$field = 'auth_microsoft_default_role_id';
	$value = set_value($field, element($field, $settings), FALSE);
	?>
	<p>
		<label for="<?= $field ?>"><?= lang('auth.microsoft.field.default_role_id') ?></label>
		<?php
		echo form_input(array(
			'name' => $field,
			'id' => $field,
			'type' => 'number',
			'size' => '10',
			'maxlength' => '10',
			'tabindex' => tab_index(),
			'value' => $value,
			'style' => 'max-width:100px',
		));
		?>
		<p class="hint"><?= lang('auth.microsoft.field.default_role_id_hint') ?></p>
	</p>
	<?php echo form_error($field) ?>

	<?php
	$field = 'auth_microsoft_default_department_id';
	$value = set_value($field, element($field, $settings), FALSE);
	?>
	<p>
		<label for="<?= $field ?>"><?= lang('auth.microsoft.field.default_department_id') ?></label>
		<?php
		echo form_input(array(
			'name' => $field,
			'id' => $field,
			'type' => 'number',
			'size' => '10',
			'maxlength' => '10',
			'tabindex' => tab_index(),
			'value' => $value,
			'style' => 'max-width:100px',
		));
		?>
		<p class="hint"><?= lang('auth.microsoft.field.default_department_id_hint') ?></p>
	</p>
	<?php echo form_error($field) ?>

</fieldset>

<?php
	$this->load->view('partials/submit', array(
		'submit' => array(lang('auth.save'), tab_index()),
	));

echo form_close();
