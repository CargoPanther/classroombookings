<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use League\OAuth2\Client\Provider\GenericProvider;

class Auth_microsoft
{

	protected $CI;
	protected $provider;
	protected $errors = [];
	protected $enabled = false;
	protected $client_id = '';
	protected $client_secret = '';
	protected $redirect_uri = '';
	protected $authority = '';
	protected $scopes = 'User.Read';
	protected $create_users = false;
	protected $default_role_id = null;
	protected $default_department_id = null;

	public function __construct($config = [])
	{
		$this->CI =& get_instance();
		$this->CI->load->model('users_model');

		if ( ! empty($config)) {
			$this->init($config);
		} else {
			$this->init_from_settings();
		}
	}

	public function enabled()
	{
		return boolval($this->enabled ?? FALSE);
	}

	public function init($config)
	{
		foreach ($config as $key => $value) {
			if (property_exists($this, $key)) {
				$this->$key = $value;
			}
		}

		return $this;
	}

	public function init_from_settings()
	{
		$settings = $this->CI->settings_model->get_all('auth');

		$this->enabled = boolval(element('auth_microsoft_enabled', $settings, 0));
		$this->client_id = element('auth_microsoft_client_id', $settings, '');
		$this->client_secret = element('auth_microsoft_client_secret', $settings, '');
		$this->redirect_uri = element('auth_microsoft_redirect_uri', $settings, site_url('login/microsoft'));
		$this->authority = element('auth_microsoft_authority', $settings, '');
		$this->scopes = element('auth_microsoft_scopes', $settings, 'User.Read');
		$this->create_users = boolval(element('auth_microsoft_create_users', $settings, 0));
		$this->default_role_id = element('auth_microsoft_default_role_id', $settings, null);
		$this->default_department_id = element('auth_microsoft_default_department_id', $settings, null);

		return $this;
	}

	protected function get_provider()
	{
		if ($this->provider instanceof GenericProvider) {
			return $this->provider;
		}

		$endpoints = $this->discover_endpoints();
		if ($endpoints === FALSE) {
			throw new Exception('Microsoft OpenID configuration discovery failed.');
		}

		$this->provider = new GenericProvider([
			'clientId' => $this->client_id,
			'clientSecret' => $this->client_secret,
			'redirectUri' => $this->redirect_uri,
			'urlAuthorize' => $endpoints['authorization_endpoint'],
			'urlAccessToken' => $endpoints['token_endpoint'],
			'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',
			'scopes' => explode(' ', $this->scopes),
		]);

		return $this->provider;
	}

	protected function discover_endpoints()
	{
		$authority = trim($this->authority);
		if (!preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i', $authority)) {
			$this->errors[] = 'invalid_tenant';
			return FALSE;
		}
		$authority = strtolower($authority);
		$discovery_url = sprintf('https://login.microsoftonline.com/%s/v2.0/.well-known/openid-configuration', rawurlencode($authority));

		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'timeout' => 10,
				'header' => "Accept: application/json\r\n",
			],
		]);

		$response = @file_get_contents($discovery_url, false, $context);
		if ($response === FALSE) {
			$this->errors[] = 'discovery_failed';
			return FALSE;
		}

		$payload = json_decode($response, true);
		if (!is_array($payload)) {
			$this->errors[] = 'discovery_invalid_response';
			return FALSE;
		}

		$required = ['authorization_endpoint', 'token_endpoint'];
		foreach ($required as $key) {
			if (empty($payload[$key])) {
				$this->errors[] = 'discovery_missing_' . $key;
				return FALSE;
			}
		}

		$expected_issuer = 'https://login.microsoftonline.com/' . $authority . '/v2.0';
		$issuer = strtolower((string) ($payload['issuer'] ?? ''));
		if ($issuer !== $expected_issuer && $issuer !== 'https://login.microsoftonline.com/{tenantid}/v2.0') {
			$this->errors[] = 'tenant_mismatch';
			return FALSE;
		}
		foreach (['authorization_endpoint', 'token_endpoint'] as $endpoint) {
			$url = parse_url($payload[$endpoint]);
			$allowed_hosts = ['login.microsoftonline.com'];
			if (($url['scheme'] ?? '') !== 'https' || !in_array(strtolower($url['host'] ?? ''), $allowed_hosts, TRUE)) {
				$this->errors[] = 'tenant_mismatch';
				return FALSE;
			}
			$path = strtolower($url['path'] ?? '');
			if (str_starts_with($path, '/{tenant}/')) {
				$payload[$endpoint] = str_replace('{tenant}', $authority, $payload[$endpoint]);
			} elseif (!str_starts_with($path, '/' . $authority . '/')) {
				$this->errors[] = 'tenant_mismatch';
				return FALSE;
			}
		}

		return $payload;
	}

	public function get_authorization_url()
	{
		try {
			$provider = $this->get_provider();
			$authUrl = $provider->getAuthorizationUrl(['scope' => explode(' ', $this->scopes)]);
			$this->set_state($provider->getState());
			$_SESSION['auth_microsoft_scope_debug'] = $this->scopes;
			return $authUrl;
		} catch (Exception $e) {
			$this->errors[] = $e->getMessage();
			return FALSE;
		}
	}

	public function complete($code, $state)
	{
		if (empty($code)) {
			$this->errors[] = 'missing_auth_code';
			return FALSE;
		}

		$expectedState = $this->get_state();
		$this->set_state(null);

		if (empty($state) || $expectedState === null || $expectedState !== $state) {
			$this->errors[] = 'invalid_state';
			return FALSE;
		}

		try {
			$provider = $this->get_provider();
			$accessToken = $provider->getAccessToken('authorization_code', [
				'code' => $code,
			]);

			$owner = $provider->getResourceOwner($accessToken);
			$attributes = $owner->toArray();

			return $this->find_or_create_user($attributes);
		} catch (Exception $e) {
			$this->errors[] = $e->getMessage();
			return FALSE;
		}
	}

	public function get_errors()
	{
		return $this->errors;
	}

	public function get_error_messages()
	{
		$out = [];
		foreach ($this->errors as $error) {
			if (is_string($error) && preg_match('/^[a-z0-9_.]+$/', $error)) {
				$translation = lang('auth.microsoft.error.' . $error);
				$out[] = ($translation !== null && $translation !== FALSE && $translation !== "auth.microsoft.error.$error") ? $translation : $error;
			} else {
				$out[] = $error;
			}
		}

		return $out;
	}

	protected function set_state($state)
	{
		if (is_null($state)) {
			unset($_SESSION['oauth2state']);
			return;
		}

		$_SESSION['oauth2state'] = $state;
	}

	protected function get_state()
	{
		return $_SESSION['oauth2state'] ?? null;
	}

	protected function find_or_create_user(array $claims)
	{
		$microsoft_id = trim((string) element('id', $claims));
		if ($microsoft_id === '') {
			$this->errors[] = 'missing_identity';
			return FALSE;
		}

		$email = element('mail', $claims) ?: element('email', $claims) ?: element('preferred_username', $claims) ?: element('userPrincipalName', $claims);
		$upn = element('upn', $claims) ?: element('userPrincipalName', $claims);
		$givenName = element('given_name', $claims) ?: element('givenName', $claims);
		$familyName = element('family_name', $claims) ?: element('surname', $claims);
		$name = element('name', $claims) ?: element('displayName', $claims);

		if (empty($email) && empty($upn)) {
			$this->errors[] = 'missing_email';
			return FALSE;
		}

		$username = $email ?: $upn;
		$user = $this->CI->users_model->get_by_microsoft_id($microsoft_id);
		if (!$user) {
			$user = $this->CI->users_model->get_by_email($email, FALSE);
			if (!$user) {
				$user = $this->CI->users_model->get_by_username($username, FALSE);
			}
		}

		$user_data = [
			'username' => $username,
			'email' => $email,
			'firstname' => $givenName,
			'lastname' => $familyName,
			'displayname' => $name ?: trim($givenName . ' ' . $familyName),
		];

		if ($user) {
			if ($user->enabled == 0) {
				$this->errors[] = 'user_not_enabled';
				return FALSE;
			}
			if (empty($user->microsoft_id)) {
				$this->errors[] = 'identity_not_linked';
				return FALSE;
			}
			if (!hash_equals(strtolower($user->microsoft_id), strtolower($microsoft_id))) {
				$this->errors[] = 'identity_mismatch';
				return FALSE;
			}

			$user_data = [
				'firstname' => $givenName,
				'lastname' => $familyName,
				'displayname' => $name ?: trim($givenName . ' ' . $familyName),
			];
			$this->CI->users_model->update($user->user_id, $user_data);
			return $this->CI->users_model->get_by_id($user->user_id);
		}

		if (! $this->create_users) {
			$this->errors[] = 'user_not_found';
			return FALSE;
		}

		$user_id = $this->CI->users_model->insert(array_merge($user_data, [
			'microsoft_id' => $microsoft_id,
			'created' => date('Y-m-d H:i:s'),
			'enabled' => 1,
			'role_id' => $this->default_role_id,
			'department_id' => $this->default_department_id,
		]));

		if (! $user_id) {
			$this->errors[] = 'user_create_failed';
			return FALSE;
		}

		return $this->CI->users_model->get_by_id($user_id);
	}
}
