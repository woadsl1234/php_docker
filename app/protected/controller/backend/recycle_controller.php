<?php

use app\model\recycle_model;

class recycle_controller extends general_controller
{
	public function action_list()
	{
		$recycle_model = new recycle_model();
		echo json_encode($recycle_model->get_list());
	}

	public function action_add()
	{
		if (request('step') == 'submit') {
			$recycle_model = new recycle_model();
			$verifier = $recycle_model->verifier($_POST);
            if(TRUE === $verifier) {
            	$recycle_model->create($_POST);
            	$this->prompt('success', '添加成功', url($this->MOD.'/goods', 'recycle'));
            } else {
            	$this->prompt('error', $verifier);
            }
		} else {
			$this->compiler('recycle/add.html');
		}
	}

	public function action_edit()
	{
		if (request('id')) {
			$recycle_model = new recycle_model();
			if (request('step') == 'submit') {
				$verifier = $recycle_model->verifier($_POST);
	            if(TRUE === $verifier) {
	            	$recycle_model->update(array('id' => request('id')), $_POST);
	            	$this->prompt('success', '更新成功');
	            } else {
	            	$this->prompt('error', $verifier);
	            }
			}

			$this->recycle_goods = $recycle_model->get(request('id'));
			$this->compiler('recycle/edit.html');
		} else {
			$this->prompt('error', "请选择商品！");
		}
	}

	public function action_delete()
	{
		$recycle_model = new recycle_model();
		$recycle_model->delete(array('id' => request('id')));
		$this->prompt('success', '删除成功', url($this->MOD.'/goods', 'recycle'));
	}
}