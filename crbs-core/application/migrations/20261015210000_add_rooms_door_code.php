<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_rooms_door_code extends CI_Migration
{

	public function up()
	{
		$this->dbforge->add_column('rooms', [
			'door_code' => [
				'type' => 'VARCHAR',
				'constraint' => 50,
				'null' => TRUE,
				'default' => NULL,
			],
		]);
	}


	public function down()
	{
	}

}
