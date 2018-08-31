<?php

namespace app\model;

use app\model\goods_model;
use app\model\order_goods_optional_model;
use app\model\order_model;

class order_goods_model extends model
{
    public $table_name = 'order_goods';

    const STATUS_NOT_REFUND = 1;//无退款
    const STATUS_REFUNDING = 2;//退款中
    const STATUS_REFUNDED = 3;//已退款

    public static $refund_status_map = [
        self::STATUS_NOT_REFUND => '无退款',
        self::STATUS_REFUNDING => '退款中',
        self::STATUS_REFUNDED => '已退款'
    ];

    /**
     * 添加订单商品记录
     */
    public function add_records($order_id, $goods_list)
    {
        $goods_model = new goods_model();
        $opts_model = new order_goods_optional_model();
        $goods_name_list = array();
        foreach ($goods_list as $v) {
            $goods_name_list[] = $v['goods_name'];
            $data = array
            (
                'order_id' => $order_id,
                'goods_id' => $v['goods_id'],
                'goods_name' => ($v['pre_sell'] ? "[预定]" : "") . $v['goods_name'],
                'goods_image' => $v['goods_image'],
                'goods_price' => $v['now_price'],
                'goods_qty' => $v['qty']
            );

            $shop_stock_qty = $v['stock_qty'] - $v['instead_sell_stock_qty'];
            $left = $v['qty'] - $shop_stock_qty;//还需要找几本书
            $goods_instead_sell_model = new goods_instead_sell_model();
            //总库存充足，但是自营库存不足
            if ($v['stock_qty'] >= $v['qty'] && $shop_stock_qty < $v['qty']) {
                $instead_goods_list = $goods_instead_sell_model->search_proper_goods($v['goods_id'], $left);
                $data['instead_goods_list'] = json_encode($instead_goods_list);
            }

            if ($id = $this->create($data)) {
                //自营库存充足
                if ($shop_stock_qty >= $v['qty']) {
                    $goods_model->decr(array('goods_id' => $v['goods_id']), 'stock_qty', $v['qty']);
                } elseif (isset($data['instead_goods_list'])) {
                    $instead_goods_list = json_decode($data['instead_goods_list'], true);
                    foreach ($instead_goods_list as $instead_sell_goods) {
                        $goods_instead_sell_model->decr(array('id' => $instead_sell_goods['goods_instead_id']), 'qty', $instead_sell_goods['qty']);
                    }
                    if ($shop_stock_qty > 0) {
                        $goods_model->decr(array('goods_id' => $v['goods_id']), 'stock_qty', $shop_stock_qty);
                    }
                    $goods_model->decr(array('goods_id' => $v['goods_id']), 'instead_sell_stock_qty', $left);
                }
                if (!empty($v['opts'])) {
                    foreach ($v['opts'] as $o) $opts_model->create(array('map_id' => $id, 'opt_id' => $o['id'], 'opt_type' => $o['type'], 'opt_text' => $o['opt_text']));
                }
            }
        }
        return implode(',', $goods_name_list);
    }

    public function shipping($order_id, $ids)
    {
        if (!$ids) {
            return;
        }
        $sql = "update {$this->table_name} set is_shipping = 1 where id in ($ids) and order_id = :order_id";
        return $this->query($sql, array(':order_id' => $order_id));
    }

    /**
     * 重置订单中商品库存
     */
    public function restocking($order_id, $method = 'incr')
    {
        if ($arr = $this->find_all(array('order_id' => $order_id), null, 'goods_id, goods_qty,instead_goods_list')) {
            $goods_model = new goods_model();
            $goods_instead_sell_model = new goods_instead_sell_model();
            foreach ($arr as $v) {
                $self_goods_qty = $v['goods_qty'];
                $instead_goods_qty = 0;

                if ($v['instead_goods_list']) {
                    $instead_goods_list = json_decode($v['instead_goods_list'], true);
                    if (!empty($instead_goods_list)) {
                        foreach ($instead_goods_list as $instead_goods) {
                            $goods_instead_sell_model->incr(['id' => $instead_goods['goods_instead_id']], 'qty', $instead_goods['qty']);
                            $instead_goods_qty += $instead_goods['qty'];
                            $self_goods_qty -= $instead_goods['qty'];
                        }
                    }
                }
                $self_goods_qty && $goods_model->$method(array('goods_id' => $v['goods_id']), 'stock_qty', $self_goods_qty);
                $instead_goods_qty && $goods_model->$method(array('goods_id' => $v['goods_id']), 'instead_sell_stock_qty', $instead_goods_qty);
            }
        }
    }

    /**
     * 获取订单商品列表
     */
    public function get_goods_list($order_id)
    {
        if ($list = $this->find_all(array('order_id' => $order_id))) {
            $opts_model = new order_goods_optional_model();
            foreach ($list as &$v) {
                $v['goods_opts'] = $opts_model->find_all(array('map_id' => $v['id']));
            }
        }
        return $list ? $list : [];
    }

    /**
     * 检查该购买商品是否允许评价
     */
    public function allowed_review($user_id, $order_id, $goods_id)
    {
        $sql = "SELECT a.*, b.user_id FROM {$this->table_name} AS a
                INNER JOIN {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}order AS b
                ON a.order_id = b.order_id
                WHERE a.order_id = :order_id AND a.goods_id = :goods_id AND a.is_reviewed = 0 AND b.user_id = :user_id
                LIMIT 1
               ";
        return $this->query($sql, array(':user_id' => $user_id, ':order_id' => $order_id, ':goods_id' => $goods_id)) ? TRUE : FALSE;
    }

    public function get_not_shipping_goods($start_date, $end_date)
    {
        $refund_status = self::STATUS_NOT_REFUND;
        $order_model = new order_model();
        $order_status = $order_model::ORDER_STATUS_WAIT_SHIPPING;

        $sql = "select *,sum(goods_qty) as amount from {$this->table_name} as g join {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}order as o on o.order_id = g.order_id
              where o.order_status = {$order_status} and is_shipping = 0 and refund_status = {$refund_status}  and created_at >= '$start_date' and created_at <= '$end_date' and goods_name like '[预定]%'
                group by goods_id order by amount DESC ";
        $order_goods_list = $this->query($sql);

        $goods_ids = [];
        $goods_list = [];
        foreach ($order_goods_list as $goods) {
            $goods_ids[] = $goods['goods_id'];
        }
        if (!empty($goods_ids)) {
            $sql = "select goods_name,goods_id,goods_sn,now_price,stock_qty from {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}goods where goods_id in (" . implode($goods_ids, ',') . ")";
            $goods_list = $this->query($sql);
        }

        foreach ($order_goods_list as $key => &$order_goods) {
            for ($i = 0; $i < count($goods_list); $i++) {
                if ($goods_list[$i]['goods_id'] == $order_goods['goods_id']) {
                    $order_goods = array_merge($order_goods, $goods_list[$i]);
                    break;
                }
            }
        }

        return $order_goods_list;
    }
}