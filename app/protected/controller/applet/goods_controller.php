<?php

use app\model\goods_cate_model;

/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/5
 * Time: 下午3:39
 */

class goods_controller extends general_controller
{
    public function action_cates()
    {
        $cates = vcache::instance()->goods_cate_model("find_all", array(['parent_id' => 0], 'seq asc', 'cate_id,cate_name'));
        $this->r(true, 'ok', $cates);
    }
}