<?php

use app\model\goods_model;

class cart_controller extends general_controller
{
    public function action_list()
    {
        $carts = request('carts', null);
        if (!$carts) {
            echo json_encode(array('status' => 'nodata'));
            return;
        }
        $carts = json_decode($carts, TRUE);
        $goods_model = new goods_model();
        if ($cart = $goods_model->get_cart_items($carts)) {
            $res = array('status' => 'success', 'cart' => $cart);
        } else {
            $res = array('status' => 'error');
        }
        echo json_encode($res);
    }
}