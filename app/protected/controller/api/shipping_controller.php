<?php

/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/28
 * Time: 上午12:06
 */

class shipping_controller extends \general_controller
{
    public function action_methods()
    {
        $methods = vcache::instance()->shipping_method_model('indexed_list');
        $list = [];
        foreach ($methods as $method) {
            if (!$method['enable']) {
                continue;
            }
            $list[] = [
                'id' => $method['id'],
                'name' => $method['name'],
                'instruction' => $method['instruction']
            ];
        }
        echo json_encode(['status' => 'success', 'data' => $list]);
    }
}