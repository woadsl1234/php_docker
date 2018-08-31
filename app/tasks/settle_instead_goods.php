<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/3/11
 * Time: 上午10:38
 */

namespace tasks;

use plugin\payment\alipay;

class settle_instead_goods extends task
{
    protected $task_name= "自动结算已签收的代售商品";

    protected $alipay = null;

    public function run()
    {
        try {
            $source = $this->get_source_data_set();
            if (!empty($source)) {
                foreach ($source as $row) {
                    if ($this->is_exist_instead_goods($row)) {
                        $this->settle($row);
                        break;
                    }
                }
            }
        } catch (\PDOException $e) {
            echo $e->getMessage();
        } finally {
            $dbh = null;
        }
    }

    private function get_source_data_set()
    {
        $default_day = get_cfg_var("erhuo.runmode") === 'online' ? 'yesterday' : "today";
        echo "查询 $default_day 交易成功未结算的代售商品\n";
        switch(strtolower($default_day)) {
            case 'today':
                $min_date = strtotime(date("Y-m-d", time()));
                $max_date = strtotime(date("Y-m-d", strtotime("+1 day")));
                break;
            case 'yesterday':
            default:
                $min_date = strtotime(date("Y-m-d", strtotime("-1 day")));
                $max_date = strtotime(date("Y-m-d", time()));
        }
        return $this->dbh->query("select * from verydows_order_goods where 
            order_id in (select order_id from verydows_order where finish_date > $min_date and finish_date <= $max_date and order_status = 4)
        and is_shipping = 1 and refund_status = 1");
    }

    private function settle($row)
    {
        $instead_goods_list = json_decode($row['instead_goods_list'], true);
        $alipay = $this->get_alipay();
        foreach ($instead_goods_list as $goods)
        {
            var_dump($goods);
            $goods_instead_id = intval($goods['goods_instead_id']);
            $qty = (int)$goods['qty'];
            $sql = "select a.account from verydows_instead_sell_apply as a join verydows_goods_instead_sell as s on a.id = s.instead_sell_id
                    where s.id = $goods_instead_id limit 1";
            $query = $this->dbh->query($sql);
            $res = $query->fetch(\PDO::FETCH_ASSOC);
            $remark = "[{$row['goods_name']}]x{$qty} 代售成功";
            $out_biz_no = $row['order_id'] . $goods_instead_id;
            try {
                $total_fee = $row['goods_price'] * 0.5 * $qty;
                $alipay->transfer_to_account($res['account'], $total_fee, $remark, $out_biz_no);
                $sql = "update verydows_goods_instead_sell set selled = selled + $qty where id = $goods_instead_id";
                $this->dbh->exec($sql);
                echo "给 {$res['account']} 转账 $total_fee \n";
            } catch (\Exception $e) {
                echo $e->getMessage();
            }
        }
    }

    private function is_exist_instead_goods($goods)
    {
        return trim($goods['instead_goods_list']) != "";
    }

    private function get_pay_params()
    {
        $sql = "select params from verydows_payment_method where pcode = 'alipay'";
        $query = $this->dbh->query($sql);
        $res = $query->fetch(\PDO::FETCH_ASSOC);
        return $res['params'];
    }

    private function get_alipay()
    {
        if (!$this->alipay) {
            $pay_config = $this->get_pay_params();
            $this->alipay = new alipay($pay_config);
        }
        return $this->alipay;
    }
}