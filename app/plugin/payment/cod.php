<?php
/**
 * 货到付款
 * COD Payment
 * @author Cigery
 */
namespace plugin\payment;

use app\model\order_model;
use app\exception\order_not_found_exception;
use app\model\user_model;
use plugin\push\phone;

class cod extends abstract_payment
{
    protected $pcode = 'cod';
    protected $pay_success_message = '支付成功，您可在订单详情中查看订单状态';

    public function create_pay_url($args)
    {
        return $url = url('mobile/pay', 'return', array('pcode' => 'cod', 'order_id' => $args['order_id']));
    }

    public function custom_pay()
    {
        return true;
    }
}