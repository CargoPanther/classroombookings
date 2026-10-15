<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Logout extends MY_Controller
{


	public function __construct()
	{
		parent::__construct();
	}


	function index()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}

		$this->userauth->log_out();
		redirect('');
	}


}
