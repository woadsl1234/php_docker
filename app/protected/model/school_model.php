<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/1/10
 * Time: 上午1:36
 */

namespace app\model;

class school_model extends model
{
    public $table_name = 'school';

    public function get_school_list()
    {
        return $this->find_all(array('is_on' => 1));
    }

    public function get($id)
    {
        return $this->find(array('id' => $id, 'is_on' => 1));
    }
}