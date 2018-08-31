<?php

use app\model\goods_model;
use app\model\order_model;
use app\model\shipping_method_model;
use app\model\user_consignee_model;

/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/28
 * Time: 上午3:25
 */


class order_controller extends general_controller
{
    public function action_freight()
    {
        $user_id = $this->is_logined();
        //购物车信息
        $cart = json_decode(request('carts', null), TRUE);
        if (!$cart) die(json_encode(array('status' => 'error', 'msg' => '无法获取购物车数据')));

        $goods_model = new goods_model();
        if (!$cart = $goods_model->get_cart_items(($cart)))
            die(json_encode(array('status' => 'error', 'msg' => '购物车商品数据不正确')));
        //收件人信息
        $csn_id = (int)request('csn_id', 0);
        $consignee_model = new user_consignee_model();
        if (!$consignee = $consignee_model->find(array('id' => $csn_id, 'user_id' => $user_id))) {
            die(json_encode(array('status' => 'error', 'msg' => '收件人地址不存在')));
        }
        //计算运费
        $shipping_id = (int)request('shipping_id', 0);
        $shipping_model = new shipping_method_model();
        $amount = $shipping_model->check_freight($user_id, $shipping_id, $consignee['province'], $cart);
        if (FALSE === $amount) die(json_encode(array('status' => 'error', 'msg' => '计算运费失败')));

        echo json_encode(array('status' => 'success', 'amount' => sprintf('%.2f', $amount)));
    }

    public function action_amount()
    {
        $user_id = $this->is_logined();
        $order_model = new order_model();
        $order = $order_model->find(array('order_id' => request('order_id'), 'user_id' => $user_id), null, 'order_amount');
        echo json_encode(['status' => 'success', 'order_amount' => $order['order_amount']]);
    }

}