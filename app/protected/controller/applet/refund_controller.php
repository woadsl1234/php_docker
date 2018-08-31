<?php

use app\model\order_goods_model;
use app\model\order_model;
use app\model\refund_model;

/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/3/31
 * Time: 下午2:38
 */


class refund_controller extends general_controller
{
    public function action_show_apply()
    {
        $order_goods_id = request('order_goods_id');

        $data = [];
        $order_goods_model = new order_goods_model();
        $refund_model = new refund_model();
        $order_model = new order_model();
        $order_goods = $order_goods_model->find(['id' => $order_goods_id], null,
            'id,order_id,goods_id,goods_name,goods_image,goods_qty,goods_price');
        if (empty($order_goods)) {
            $this->r(false,'param error');
        }

        $order_id = $order_goods['order_id'];
        $sql = "select sum(goods_qty) as total_qty from verydows_order_goods where order_id = {$order_id}";
        $res = $order_goods_model->query($sql);
        $data['total_goods_qty'] = $res[0]['total_qty'];
        $data['status_map'] = $refund_model::STATUS_MAP;
        $data['reasons_map'] = $refund_model::REASONS_MAP;
        $order = $order_model->find(['order_id' => $order_id]);
        $data['discount_fee'] = $order['discount_amount'];
        $data['delivery_fee'] = $order['shipping_amount'];
        $data['order_goods'] = $order_goods;

        $this->r(true, 'ok', $data);
    }

    public function action_apply()
    {
        $user_id = $this->is_logined();
        $order_goods_id = request('order_goods_id');

        $refund_model = new refund_model();
        $order_refund = $refund_model->find(['order_goods_id' => $order_goods_id]);
        if (!empty($order_refund)) {
            $this->r(false, '请勿重复申请');
        }
        $apply = [
            'order_goods_id' => $order_goods_id,
            'receive_status' => request('receive_status'),
            'reason' => request('reason'),
            'refund_fee' => request('refund_fee'),
            'describe' => request('describe'),
            'form_id' => request('form_id')
        ];

        $this->is_apply_rightful($apply);
        $refund_model->create($apply);

        //更新订单商品状态
        $order_goods_model = new order_goods_model();
        $order_goods_model->update(['id' => $order_goods_id], ['refund_status' => $order_goods_model::STATUS_REFUNDING]);

        $this->r(true, 'ok');
    }

    public function action_detail()
    {
        $order_goods_id = request('order_goods_id');
        $order_goods_model = new order_goods_model();
        $order_goods = $order_goods_model->find(['id' => $order_goods_id], null,
            'order_id,goods_id,goods_name,goods_image,goods_qty,goods_price');
        $refund_model = new refund_model();
        $refund = $refund_model->find(['order_goods_id' => $order_goods_id]);
        if (empty($refund)) {
            $this->r(false,'无退款记录');
        }
        $refund['order_goods'] = $order_goods;
        $refund['receive_status'] = $refund_model::STATUS_MAP[$refund['receive_status']];
        $refund['reason'] = $refund_model::REASONS_MAP[$refund['reason']];
        $refund['process'] = $refund_model::PROCESS_MAP[$refund['process']];
        $this->r(true,'ok', $refund);
    }

    private function is_apply_rightful($apply)
    {
        $refund_model = new refund_model();
        if (null !== $refund_model::STATUS_MAP[$apply['receive_status']]
            && null !== $refund_model::REASONS_MAP[$apply['reason']]
            && $apply['refund_fee'] > 0
        ) {
            return true;
        } else {
            return false;
        }
    }
}