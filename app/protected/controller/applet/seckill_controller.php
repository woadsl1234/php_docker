<?php

use app\model\goods_model;
use app\model\order_consignee_model;
use app\model\order_full_cut_model;
use app\model\order_goods_model;
use app\model\order_model;
use app\model\seckill_model;
use app\model\shipping_method_model;
use app\model\user_consignee_model;

/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/5/3
 * Time: 下午3:39
 */

class seckill_controller extends general_controller
{
    public function action_detail()
    {
        $id = request('id');
        $seckill_model = new seckill_model();

        $this->r(true, 'ok', $seckill_model->get($id));
    }

    public function action_submit_order()
    {
        $user_id = $this->is_logined();
        $goods_model = new goods_model();
        $goods_model->start_transaction();

        try {
            //检查秒杀商品数据
            $cart = json_decode(stripslashes(request('CARTS', null, 'post')), TRUE);
            if (!$cart) throw new Exception('无法获取商品数据');
            $cart = $goods_model->get_cart_items($cart);
            if (!$cart) throw new Exception('商品数据不正确');
            $seckill_model = new seckill_model();
            $items = array_merge($cart['items'], $cart['shortAgeItems']);
            $seckill = $seckill_model->find(['goods_id' => $items[0]['goods_id']]);
            if (empty($seckill)) throw new Exception('秒杀活动不存在');
            if (time() < strtotime($seckill['start_at'])) throw new Exception('秒杀未开始');
            if (strtotime($seckill['end_at'] < time())) throw new Exception('秒杀已结束');
            if ($seckill['seckill_stock_qty'] == 0) throw new Exception('已经被抢光啦');
            $items[0]['now_price'] = $seckill['seckill_price'];
            $cart['items'] = $items;
            $goods = $cart['items'][0];

            //限购：价格一致，未完成交易
            if ($seckill['is_buy_limit'] && $seckill['buy_limit_amount'] > 0) {
                $start_date = strtotime($seckill['start_at']);
                $end_date = strtotime($seckill['end_at']);

                $sql = "select sum(goods_qty) as total_goods_qty from verydows_order_goods where order_id in (SELECT order_id from 
                        verydows_order where user_id = $user_id and order_status >= 1 
                        and created_date >= $start_date and created_date <=  $end_date) and goods_id = {$goods['goods_id']}";
                $res = $goods_model->query($sql);
                if ($res[0]['total_goods_qty'] >= $seckill['buy_limit_amount']) {
                    throw new Exception("每人只能限购 {$seckill['buy_limit_amount']} 件");
                }
            }

            //检查收件人信息
            $csn_id = (int)request('csn_id');
            $consignee_model = new user_consignee_model();
            if (!$consignee = $consignee_model->find(array('id' => $csn_id, 'user_id' => $user_id)))
                throw new Exception('无法获取收件人地址信息');

            //检查配送方式
            $shipping_id = (int)request('shipping_id');
            $shipping_map = vcache::instance()->shipping_method_model('indexed_list');
            if (!isset($shipping_map[$shipping_id])) throw new Exception('配送方式不存在');

            //检查运费
            $shipping_model = new shipping_method_model();
            $shipping_amount = $shipping_model->check_freight($user_id, $shipping_id, $consignee['province'], $cart);
            if (FALSE === $shipping_amount) throw new Exception('无法获取运费');

            //检查付款方式
            $payment_id = (int)request('payment_id');
            $payment_map = vcache::instance()->payment_method_model('indexed_list');
            if (!isset($payment_map[$payment_id])) {
                $payment_id = current($payment_map);
                $payment_id = $payment_id['id'];
            }

            $order_amount = $goods['now_price'] + $shipping_amount;
            //检查满减
            $full_cut = vcache::instance()->full_cut_model('get_underway_activity');
            $can_full_cut = false;
            $discount_fee = 0;
            if ($full_cut) {
                for ($i = count($full_cut['list']) - 1; $i >= 0; $i--) {
                    $can_full_cut = ($order_amount >= $full_cut['list'][$i]['order_fee']);
                    if ($can_full_cut) {
                        $order_amount -= $full_cut['list'][$i]['discount_fee'];
                        $discount_fee = $full_cut['list'][$i]['discount_fee'];
                        break;
                    }
                }
            }

            //创建订单
            $order_model = new order_model();
            $data = array
            (
                'order_id' => $order_model->create_order_id(),
                'user_id' => $user_id,
                'shipping_method' => $shipping_id,
                'payment_method' => $payment_id,
                'goods_amount' => $goods['now_price'],
                'shipping_amount' => $shipping_amount,
                'order_amount' => $order_amount,
                'memos' => trim(strip_tags(request('memos', ''))),
                'discount_amount' => $discount_fee,
                'created_date' => $_SERVER['REQUEST_TIME'],
                'order_status' => $order_model::ORDER_STATUS_WAIT_PAY
            );
            if (!$order_model->create($data)) {
                throw new Exception('订单创建失败，请稍后重试');
            }

            $order_goods_model = new order_goods_model();
            $order_goods = array
            (
                'order_id' => $data['order_id'],
                'goods_id' => $goods['goods_id'],
                'goods_name' => $goods['goods_name'],
                'goods_image' => $goods['goods_image'],
                'goods_price' => $goods['now_price'],
                'goods_qty' => $goods['qty']
            );
            $order_goods_model->create($order_goods);

            $res = $seckill_model->decr([
                'goods_id' => $seckill['goods_id'],
                'seckill_stock_qty' => $seckill['seckill_stock_qty']
            ], 'seckill_stock_qty');
            if (!$res) {
                throw new Exception('库存扣减失败，请重试');
            }

            $order_consignee_model = new order_consignee_model();
            $order_consignee_model->add_records($data['order_id'], $consignee);
            //记录满减记录
            if ($can_full_cut) {
                $order_full_cut_model = new order_full_cut_model();
                $data = [
                    'order_id' => $data['order_id'],
                    'full_out_id' => $full_cut['id'],
                    'discount_fee' => $discount_fee
                ];
                $order_full_cut_model->save($data);
            }
            $goods_model->commit();
            $this->r(true, 'ok', ['order_id' => (string)$data['order_id']]);
        } catch (Exception $e) {
            $goods_model->roll_back();
            $this->r(false, $e->getMessage());
        }
    }
}