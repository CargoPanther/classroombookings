<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Login extends MY_Controller
{


	public function __construct()
	{
		parent::__construct();
	}


	function index()
	{
		if ($this->userauth->logged_in()) {
			redirect('dashboard');
		}

		if (setting('auth_microsoft_enabled', 'auth')
			&& setting('auth_microsoft_auto_login', 'auth')) {
			redirect('login/microsoft');
		}

		if ($this->input->post()) {
			$this->handle_submit();
		}

		return $this->render_login_page(TRUE);
	}


	/**
	 * Username/password-only fallback for deployments using automatic Microsoft sign-in.
	 */
	public function local()
	{
		if ($this->userauth->logged_in()) {
			redirect('dashboard');
		}

		if ($this->input->post()) {
			$this->handle_submit();
		}

		return $this->render_login_page(FALSE);
	}


	private function render_login_page($show_microsoft)
	{

		$this->data['title'] = lang('auth.log_in');

		$this->data['message'] = '';
		if (setting('login_message_enabled')) {
			$this->data['message'] = html_escape(setting('login_message_text'));
		}

		$this->data['microsoft_enabled'] = $show_microsoft && (bool) setting('auth_microsoft_enabled', 'auth');

		$columns = array(
			'c1' => array(
				'width' => '60%',
				'content' => $this->load->view('login/login_index', $this->data, TRUE),
			),
			'c2' => array(
				'width' => '40%',
				'content' => '',
			),
		);

		$image_url = image_url(setting('logo'));
		$logo_html = (!empty($image_url))
			? img($image_url, FALSE, ["style" => 'max-width:100%;max-height:300px;width:auto;display:block'])
			: '';

		$columns['c2']['content'] = $logo_html;

		$body = $this->load->view('columns', $columns, TRUE);

		$auth = (string) $this->session->flashdata('auth');

		$this->data['body'] = $auth . $body;

		return $this->render();
	}


	public function microsoft()
	{
		$this->load->library('auth_microsoft');

		if ( ! $this->auth_microsoft->enabled()) {
			show_error(lang('auth.microsoft.not_enabled'));
		}

		$error = $this->input->get('error');
		if (!empty($error)) {
			$error_description = $this->input->get('error_description');
			$message = lang('auth.microsoft.sign_in_failed');
			if (!empty($error_description)) {
				$message .= ' ' . html_escape($error_description);
			}
			if (!empty($_SESSION['auth_microsoft_scope_debug'])) {
				$message .= ' Requested scope: ' . html_escape($_SESSION['auth_microsoft_scope_debug']);
				unset($_SESSION['auth_microsoft_scope_debug']);
			}
			$this->session->set_flashdata('auth', msgbox('error', $message));
			redirect('login/local');
		}

		$code = $this->input->get('code');
		if (empty($code)) {
			$auth_url = $this->auth_microsoft->get_authorization_url();
			if ($auth_url === FALSE) {
				$errors = $this->auth_microsoft->get_error_messages();
				$message = lang('auth.microsoft.sign_in_failed');
				if (!empty($errors)) {
					$message .= ' ' . implode(' ', array_map('html_escape', $errors));
				}
				$this->session->set_flashdata('auth', msgbox('error', $message));
				redirect('login/local');
			}
			redirect($auth_url);
		}

		$state = $this->input->get('state');
		$user = $this->auth_microsoft->complete($code, $state);

		if (! $user) {
			$errors = $this->auth_microsoft->get_error_messages();
			$message = lang('auth.microsoft.sign_in_failed');
			if (!empty($errors)) {
				$message .= ' ' . implode(' ', array_map('html_escape', $errors));
			}
			$this->session->set_flashdata('auth', msgbox('error', $message));
			redirect('login/local');
		}

		$this->userauth->log_in_user($user, 'microsoft');

		$uri = '';
		if (isset($_SESSION['post_login_uri'])) {
			$uri = $_SESSION['post_login_uri'];
			unset($_SESSION['post_login_uri']);
		}

		redirect($uri);
	}


	private function handle_submit()
	{
		$this->load->library('form_validation');
		$this->form_validation->set_rules('username', 'lang:user.field.username', 'required|max_length[255]');
		$this->form_validation->set_rules('password', 'lang:user.field.password', 'required');

		// Run validation
		if ($this->form_validation->run() == FALSE) {
			$errors = $this->form_validation->error_array();
			$this->data['error'] = '<div class="action-notification-source" hidden>'
				. msgbox('error', implode(' ', $errors))
				. '</div>';
			return false;
		}

		$username = $this->input->post('username');
		$password = $this->input->post('password');

		if ($this->userauth->log_in($username, $password)) {
			// Success
			$uri = '';
			if (isset($_SESSION['post_login_uri'])) {
				$uri = $_SESSION['post_login_uri'];
				unset($_SESSION['post_login_uri']);
			}
			redirect($uri);
		} else {
			$this->data['error'] = '<div class="action-notification-source" hidden>'
				. msgbox('error', lang('auth.bad_credentials'))
				. '</div>';
			return false;
		}
	}


}
