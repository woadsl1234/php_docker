<?php

/**
 * Created by PhpStorm.
 * User: chenshengkun
 * Date: 2017/11/8
 * Time: 下午10:20
 */

namespace app\model;

class order_full_cut_model extends model
{
    public $table_name = 'order_full_cut';

    public function save($data)
    {
        $order_id = $data['order_id'];
        $full_out_id = $data['full_out_id'];
        $discount_fee = $data['discount_fee'];
        $created_at = date("Y-m-d H:i:s");

        $sql = "replace into $this->table_name (order_id, full_cut_id,
                discount_fee, created_at) select $order_id, $full_out_id, $discount_fee, '$created_at'";

        return $this->query($sql);
    }

    public function getByOrderId($orderId)
    {
        $res = $this->find(array('order_id' => $orderId));
        return $res ? $res : [];
    }
}