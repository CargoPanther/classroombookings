<?php

namespace app\permissions;

use Permission;

defined('BASEPATH') OR exit('No direct script access allowed');

class RoomPermissions
{
	private $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->model('auth_model');
	}

	public function room(?object $user, int $room_id): array
	{
		if (empty($user->user_id)) {
			return [];
		}

		if ($this->CI->auth_model->user_has_permission($user->user_id, Permission::ROOM_VIEW)) {
			return ['view'];
		}

		$permissions = $this->CI->auth_model->user_room_permissions($user->user_id, $room_id);
		return in_array(Permission::ROOM_VIEW, array_column($permissions, 'permission'), true)
			? ['view']
			: [];
	}
}
