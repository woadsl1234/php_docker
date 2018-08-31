<?php

use app\model\goods_model;
use app\model\order_consignee_model;
use app\model\order_full_cut_model;
use app\model\order_goods_model;
use app\model\order_model;
use app\model\order_shipping_model;
use app\model\receipt_model;
use app\model\shipping_method_model;
use app\model\user_consignee_model;
use app\model\user_model;
use plugin\push\printer;

class order_controller extends general_controller
{
    public function action_freight()
    {
        $user_id = $this->is_logined();
        //购物车信息
        $cart = json_decode(stripslashes(request('CARTS', null, 'cookie')), TRUE);
        if (!$cart) die(json_encode(array('status' => 'error', 'msg' => '无法获取购物车数据')));
        $goods_model = new goods_model();
        if (!$cart = $goods_model->get_cart_items($cart)) die(json_encode(array('status' => 'error', 'msg' => '购物车商品数据不正确')));
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

    public function action_delivered()
    {
        $user_id = $this->is_logined();
        $order_id = bigintstr(request('id'));
        $order_model = new order_model();
        if ($order = $order_model->find(array('order_id' => $order_id, 'user_id' => $user_id, 'order_status' => 3))) {
            $order_model->update(array('order_id' => $order_id), array('order_status' => 4, 'finish_date' => time()));
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error']);
        }
    }

    public function action_submit()
    {
        $user_id = $this->is_logined();
        if (request("origin", "") == "wx_applet") {
            $storage_type = "post";
        } else {
            $storage_type = "cookie";
        }
        //检查购物车信息
        $cart = json_decode(stripslashes(request('CARTS', null, $storage_type)), TRUE);
        if (!$cart) die(json_encode(array('status' => 'error', 'msg' => '无法获取购物车数据'), JSON_UNESCAPED_UNICODE));
        $goods_model = new goods_model();
        $goods_model->start_transaction();
        if (!$cart = $goods_model->get_cart_items($cart)) die(json_encode(array('status' => 'error', 'msg' => '购物车商品数据不正确')));
        if (!empty($cart['shortAgeItems'])) {
            die(json_encode(array('status' => 'error', 'msg' => '有商品库存不足，请返回购物车修改或移除')));
        }
        //检查收件人信息
        $csn_id = (int)request('csn_id', 0);
        $consignee_model = new user_consignee_model();
        if (!$consignee = $consignee_model->find(array('id' => $csn_id, 'user_id' => $user_id))) die(json_encode(array('status' => 'error', 'msg' => '无法获取收件人地址信息')));
        //检查配送方式
        $shipping_id = (int)request('shipping_id', 0);
        $shipping_map = vcache::instance()->shipping_method_model('indexed_list');
        if (!isset($shipping_map[$shipping_id])) die(json_encode(array('status' => 'error', 'msg' => '配送方式不存在')));
        //检查运费
        $shipping_model = new shipping_method_model();
        $shipping_amount = $shipping_model->check_freight($user_id, $shipping_id, $consignee['province'], $cart);
        if (FALSE === $shipping_amount) die(json_encode(array('status' => 'error', 'msg' => '无法获取运费')));
        //检查付款方式
        $payment_id = (int)request('payment_id', 0);
        $payment_map = vcache::instance()->payment_method_model('indexed_list');
        if (!isset($payment_map[$payment_id])) {
            $payment_id = current($payment_map);
            $payment_id = $payment_id['id'];
        }
        $order_amount = $cart['amount'] + $shipping_amount;
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
            'goods_amount' => $cart['amount'],
            'shipping_amount' => $shipping_amount,
            'order_amount' => $order_amount,
            'memos' => trim(strip_tags(request('memos', ''))),
            'discount_amount' => $discount_fee,
            'created_date' => $_SERVER['REQUEST_TIME'],
            'order_status' => 1
        );

        if ($order_model->create($data)) {
            $order_goods_model = new order_goods_model();
            $data['goods_name_list'] = $order_goods_model->add_records($data['order_id'], $cart['items']);//返回商品名称列表
            $data['consignee'] = $consignee;

            $order_consignee_model = new order_consignee_model();
            $order_consignee_model->add_records($data['order_id'], $consignee);
            setcookie('CARTS', null, $_SERVER['REQUEST_TIME'] - 3600, '/');

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
            $res = array('status' => 'success', 'order_id' => (string)$data['order_id']);
        } else {
            $goods_model->roll_back();
            $res = array('status' => 'error', 'msg' => '创建订单失败, 请稍后重试');
        }
        echo json_encode($res);
    }

    public function action_payment()
    {
        $user_id = $this->is_logined();
        $order_id = bigintstr(request('order_id'));
        $order_model = new order_model();
        if ($order = $order_model->find(array('order_id' => $order_id, 'order_status' => 1))) {
            $payment_id = (int)request('payment_id');
            $payment_map = vcache::instance()->payment_method_model('indexed_list');
            if (isset($payment_map[$payment_id])) {
                $order_model->update(array('order_id' => $order_id), array('payment_method' => $payment_id));
                $order['payment_method'] = $payment_id;
                $plugin = plugin::instance('payment', $payment_map[$payment_id]['pcode'], array($payment_map[$payment_id]['params']));
                echo $plugin->create_pay_url($order);
            }
        }
    }

    public function action_pending()
    {
        $user_id = $this->is_logined();
        $sql = "SELECT COUNT(1) AS count FROM {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}order WHERE user_id = {$user_id}";
        switch (request('pending')) {
            case 'pay':
                $sql .= " AND order_status = 1";
                break;

            case 'ship':
                $sql .= " AND order_status = 2";
                break;

            case 'sign':
                $sql .= " AND order_status = 3";
                break;

            case 'review':
                $sql .= " AND order_status = 4 AND order_id in (SELECT order_id FROM {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}order_goods WHERE is_reviewed = 0)";
                break;
        }
        $order_model = new order_model();
        $res = $order_model->query($sql);
        echo json_encode(array('status' => 'success', 'count' => $res[0]['count']));
    }

    public function action_list()
    {
        $user_id = $this->is_logined();
        $sql = "SELECT COUNT(1) AS count FROM {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}order";
        $where = "WHERE user_id = {$user_id}";
        switch (request('pending')) {
            case 'pay':
                $where .= " AND order_status = 1 AND payment_method <> 2";
                break;

            case 'ship':
                $where .= " AND (order_status = 2 OR (order_status = 1 AND payment_method = 2))";
                break;

            case 'sign':
                $where .= " AND order_status = 3";
                break;

            case 'review':
                $where .= " AND order_status = 4 AND order_id in (SELECT order_id FROM {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}order_goods WHERE is_reviewed = 0)";
                break;
        }

        $res = array('status' => 'nodata');
        $order_model = new order_model();
        $total = $order_model->query("{$sql} {$where}");
        if ($total[0]['count'] > 0) {
            $limit = $order_model->set_limit(array(request('page', 1), request('pernum', 10)), $total[0]['count']);
            $sql = "SELECT * FROM {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}order {$where} ORDER BY id DESC {$limit}";
            if ($list = $order_model->query($sql)) {
                $order_goods_model = new order_goods_model();
                foreach ($list as &$v) {
                    $progress = $order_model->get_user_order_progress($v['order_status'], $v['payment_method']);
                    $v['progress'] = array_pop($progress);
                    $v['goods_list'] = $order_goods_model->get_goods_list($v['order_id']);
                    $v['created_date'] = date('Y-m-d H:i:s', $v['created_date']);
                }
                $res = array('status' => 'success', 'list' => $list, 'paging' => $order_model->page);
            }
        }
        echo json_encode($res);
    }

    public function action_cancel()
    {
        $user_id = $this->is_logined();
        $order_id = bigintstr(request('id'));
        $order_model = new order_model();
        if ($order = $order_model->find(array('order_id' => $order_id, 'user_id' => $user_id))) {
            if ($order['order_status'] == 1) {
                $order_model->update(array('order_id' => $order_id), array('order_status' => 0));
                $order_goods_model = new order_goods_model();
                $order_goods_model->restocking($order_id);
                echo json_encode(array("status" => 'success'));
            } else {
                echo json_encode(array("status" => 'error', "msg" => "参数非法"));
            }
        } else {
            echo json_encode(array("status" => 'error', 'msg' => "该订单不存在"));
        }
    }

    public function action_detail()
    {
        $user_id = $this->is_logined();
        $order_id = bigintstr(request('id'));
        $order_model = new order_model();
        if ($order = $order_model->find(array('order_id' => $order_id, 'user_id' => $user_id))) {
            $data = array();
            $vcache = vcache::instance();
            $payment_map = $vcache->payment_method_model('indexed_list');
            $shipping_map = $vcache->shipping_method_model('indexed_list');
            $order['payment_method_name'] = isset($payment_map[$order['payment_method']]) ? $payment_map[$order['payment_method']]['name'] : "未支付";
            $order['shipping_method_name'] = $shipping_map[$order['shipping_method']]['name'];
            $order['shipping_method_desc'] = $shipping_map[$order['shipping_method']]['instruction'];

            $condition = array('order_id' => $order_id);
            $consignee_model = new order_consignee_model();
            $data['consignee'] = $consignee_model->find($condition);

            $order_goods_model = new order_goods_model();
            $data['goods_list'] = $order_goods_model->get_goods_list($order_id);

            $goods_model = new goods_model();
            $good_id_list = implode(",", array_column($data['goods_list'], "goods_id"));
            $data['recommend_goods_list'] = $goods_model->get_related($good_id_list, 6);

            $data['progress'] = $order_model->get_user_order_progress($order['order_status'], $order['payment_method']);
            $data['status_map'] = $order_model->status_map;

            $order_full_cut_model = new order_full_cut_model();
            $data['order_full_cut'] = $order_full_cut_model->getByOrderId($order_id);

            if ($order['order_status'] == 1 && $order['payment_method'] != 2) {
                if (!$this->countdown = $order_model->is_overdue($order_id, $order['created_date'])) $order['order_status'] = 0;
            } elseif ($order['order_status'] == 3) {
                $shipping_model = new order_shipping_model();
                if ($shipping = $shipping_model->find($condition, 'dateline DESC')) {
                    $data['countdown'] = intval($shipping['dateline'] + $GLOBALS['cfg']['order_delivery_expires'] * 86400 - $_SERVER['REQUEST_TIME']);
                    if (!$data['countdown']) $order_model->update($condition, array('order_status' => 4));
                    $data['shipping'] = $shipping;
                    $carrier_map = $vcache->shipping_carrier_model('indexed_list');
                    $data['carrier'] = $carrier_map[$shipping['carrier_id']];
                }
            }
            $order['created_date'] = date("Y-m-d H:i:s", $order['created_date']);
            $order['payment_date'] = date("Y-m-d H:i:s", $order['payment_date']);
            $order['payment_method_name'] = $order['order_status'] >= 1 ? $order['payment_method_name'] : "无";
            $data['order'] = $order;
            echo json_encode(array('status' => 'success', 'data' => $data), JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(array("status" => 'error', 'msg' => "该订单不存在"));
        }
    }

    public function action_print()
    {
        $condition = array('order_id' => request('order_id'));
        $order_model = new order_model();
        if ($order = $order_model->find($condition)) {
            $vcache = vcache::instance();
            $payment_map = $vcache->payment_method_model('indexed_list');
            $order['payment_method_name'] = $payment_map[$order['payment_method']]['name'];
            $shipping_map = $vcache->shipping_method_model('indexed_list');
            $order['shipping_method_name'] = $shipping_map[$order['shipping_method']]['name'];
            $order_full_cut_model = new order_full_cut_model();
            $order_full_cut = $order_full_cut_model->getByOrderId($condition['order_id']);
            if ($order_full_cut) {
                $order['full_cut'] = $order_full_cut['discount_fee'];
            }
            $order['history_count'] = $order_model->get_valid_order_count($order['user_id']);

            $consignee_model = new order_consignee_model();
            $consignee = $consignee_model->find($condition);
            //用户信息
            $user_model = new user_model();
            $user = $user_model->find(array('user_id' => $order['user_id']));
            //商品列表
            $order_goods_model = new order_goods_model();
            $goods_list = $order_goods_model->get_goods_list($condition['order_id']);

            $data = [
                'order' => $order,
                'goods_list' => $goods_list,
                'user' => $user,
                'consignee' => $consignee
            ];

            $printer = new printer();

            $receipt_model = new receipt_model();
            $printer->print_content($receipt_model->order($data));
        }
    }
}