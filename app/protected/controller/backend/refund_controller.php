<?php

use app\model\goods_model;
use app\model\order_goods_model;
use app\model\order_model;
use app\model\payment_method_model;
use app\model\refund_model;
use plugin\push\kafka;
use app\model\user_model;
use plugin\push\phone;

/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/4/1
 * Time: 下午3:11
 */

class refund_controller extends general_controller
{
    public function action_list()
    {
        if (request('step') == 'api') {
            $refund_model = new refund_model();
            $process = request('process');
            $refunds = $refund_model->refund_list($process);
            if (!empty($refunds)) {
                foreach ($refunds as &$refund) {
                    $refund['is_shipping_text'] = $refund['is_shipping'] == 1 ? '✔️' : '✘';
                    $refund['receive_status_text'] = $refund['receive_status'] == 1 ? '✔️' : '✘';
                    $refund['reason'] = $refund_model::REASONS_MAP[$refund['reason']];
                    $refund['process_text'] = $refund_model::PROCESS_MAP[$refund['process']];
                }
            }
            echo json_encode($refunds);
        } else {
            $this->compiler('refund/list.html');
        }
    }

    /**
     * 同意退款
     */
    public function action_agree()
    {
        $refund_id = request('refund_id');
        $refund_model = new refund_model();
        $order_goods_model = new order_goods_model();
        $goods_model = new goods_model();
        $order_model = new order_model();
        try {
            $refund = $refund_model->find(['id' => $refund_id]);
            if ($refund['process'] != $refund_model::$process_processing) {
                throw new Exception('状态异常，无法退款');
            }
            $order = $refund_model->get_order($refund_id);
            $payment_method_model = new payment_method_model();
            if ($payment = $payment_method_model->find(array('id' => $order['payment_method'], 'enable' => 1), null, 'pcode,params')) {
                $plugin_name = "\\plugin\\payment\\" . $payment['pcode'];
                $payment = new $plugin_name($payment['params']);
                $refund_model->start_transaction();
                $res = $payment->refund($order['order_id'], $refund_id, $refund['refund_fee'], $refund_model::REASONS_MAP[$refund['reason']]);
                if (!$res) {
                    throw new Exception('退款失败');
                }
                $refund_model->update(['id' => $refund_id], ['process' => $refund_model::$process_finish]);//更新退款状态
                $order_goods_model->update(['id' => $refund['order_goods_id']], ['refund_status' => $order_goods_model::STATUS_REFUNDED]);//更新订单商品状态
                $order_goods = $order_goods_model->find(['id' => $refund['order_goods_id']]);
                $goods_model->incr(['goods_id' => $order_goods['goods_id']], 'stock_qty', $order_goods['goods_qty']);//恢复库存
                //如果全部商品都已退款，则取消交易
                $order_goods_list = $order_goods_model->find_all(['order_id' => $order_goods['order_id']]);
                $this->is_all_goods_refunded($order_goods_list) && $order_model->cancel($order_goods['order_id']);
                !$this->exist_goods_unshipping($order_goods_list) && $order_model->shipping($order_goods['order_id']);
                $order_model->incr(['order_id' => $order['order_id']], 'refund_amount', $refund['refund_fee']);
                $refund_model->commit();

                // 发送退款短信
                $user_model = new user_model();
                $user = $user_model->find(['user_id' => $order['user_id']]);
                kafka::produce(kafka::REFUND_PASS, [
                    'message' =>  [
                        'phoneNumber' => $user['mobile'],
                        'tempId' => phone::SCENE_REFUND_PASS,
                        'params' => [
                            $refund['created_date'],
                            $refund['refund_fee'],
                        ]
                    ]
                ]);

                echo json_encode(['status' => 'success', 'msg' => '退款成功']);
            }
        } catch (Exception $e) {
            $refund_model->roll_back();
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 拒绝退款
     */
    public function action_reject()
    {
        $refund_model = new refund_model();
        $refund_id = request('refund_id');
        $refund = $refund_model->find(['id' => $refund_id]);
        if ($refund['process'] != $refund_model::$process_processing) {
            throw new Exception('状态异常，无法拒绝');
        }
        $refund_model->update([
            'id' => $refund_id
        ], ['process' => $refund_model::$process_reject]);

        echo json_encode(['status' => 'success', 'msg' => '操作成功']);
    }

    /**
     * 全部商品都退款了
     */
    private function is_all_goods_refunded($order_goods_list)
    { 
        $order_goods_model = new order_goods_model();
        $is_all_refund = true;
        foreach ($order_goods_list as $item) {
            if ($item['refund_status'] != $order_goods_model::STATUS_REFUNDED) {
                $is_all_refund = false;
                break;
            }
        }
        return $is_all_refund;
    }

    /**
     * 是否存在未发货且未退货的商品
     */
    private function exist_goods_unshipping($order_goods_list)
    {
        $order_goods_model = new order_goods_model();
        $exist = false;
        foreach ($order_goods_list as $item) {
            if (($item['is_shipping'] ==  0) && 
                ($item['refund_status'] != $order_goods_model::STATUS_REFUNDED)) {
                $exist = true;
                break;
            }
        }
        
        return $exist;
    }
}