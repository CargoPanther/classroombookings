<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_users_microsoft_identity extends CI_Migration
{
	public function up()
	{
		if (!$this->db->field_exists('microsoft_id', 'users')) {
			$this->dbforge->add_column('users', [
				'microsoft_id' => [
					'type' => 'VARCHAR',
					'constraint' => 36,
					'null' => TRUE,
				],
			]);
		}

		$indexes = $this->db->query("SHOW INDEX FROM `users` WHERE `Key_name` = 'microsoft_id'");
		if ($indexes->num_rows() === 0) {
			$this->db->query("ALTER TABLE `users` ADD UNIQUE KEY `microsoft_id` (`microsoft_id`)");
		}
	}

	public function down()
	{
		$indexes = $this->db->query("SHOW INDEX FROM `users` WHERE `Key_name` = 'microsoft_id'");
		if ($indexes->num_rows() > 0) {
			$this->db->query("ALTER TABLE `users` DROP INDEX `microsoft_id`");
		}
		if ($this->db->field_exists('microsoft_id', 'users')) {
			$this->dbforge->drop_column('users', 'microsoft_id');
		}
	}
}
