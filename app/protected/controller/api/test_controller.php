<?php

use app\model\payment_method_model;

/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/3/8
 * Time: 下午8:24
 */

class test_controller extends general_controller
{
    public function action_transfer()
    {
        $pcode = 'alipay';
        $payment_model = new payment_method_model();
        $payment = $payment_model->find(array('pcode' => $pcode, 'enable' => 1), null, 'params');
        $plugin = plugin::instance('payment', $pcode, array($payment['params']));
        $plugin->transfer_to_account(17764592463, 0.1, '教材代售', "44444");
    }
}