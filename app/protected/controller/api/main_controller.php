<?php

/**
 * Created by PhpStorm.
 * User: chenshengkun
 * Date: 2017/7/19
 * Time: 下午6:20
 */
class main_controller extends general_controller
{
    public function action_index()
    {
        $vcache = vcache::instance();

        $res = $vcache->goods_model('find_goods', array($_GET), $GLOBALS['cfg']['data_cache_lifetime']);

        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }
}