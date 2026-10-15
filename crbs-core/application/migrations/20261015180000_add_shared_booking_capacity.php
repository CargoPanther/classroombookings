<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_shared_booking_capacity extends CI_Migration
{

	public function up()
	{
		$capacity = [
			'type' => 'INT',
			'constraint' => 10,
			'unsigned' => TRUE,
			'null' => FALSE,
			'default' => 1,
		];

		$this->dbforge->add_column('rooms', ['capacity' => $capacity]);
		$this->dbforge->add_column('bookings', ['capacity' => $capacity]);
		$this->dbforge->add_column('bookings_repeat', ['capacity' => $capacity]);
	}


	public function down()
	{
	}

}
