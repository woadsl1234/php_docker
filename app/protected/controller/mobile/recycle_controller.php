<?php

use app\model\recycle_model;

class recycle_controller extends general_controller
{
	public function action_index()
	{
		$recycle_model = new recycle_model();
		$this->goods_list = $recycle_model->get_list();
		$this->compiler('recycle_index.html');
	}
}