<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/4/1
 * Time: 下午2:13
 */

namespace app\model;

class refund_model extends model
{
    public $table_name = 'order_refund';

    const STATUS_MAP = [
        "未收货",
        "已收货"
    ];

    const REASONS_MAP = [
        "不小心买错了",
        "不想要了",
        "质量有问题",
        "其他"
    ];

    const PROCESS_MAP = [
        "已撤销",
        "待处理",
        "被拒绝",
        "已完成"
    ];

    public static $process_canceled = 0; //已撤销
    public static $process_processing = 1; //待处理
    public static $process_reject = 2; //被拒绝
    public static $process_finish = 3; //已完成

    public function refund_list($process)
    {
        $sql = "select r.*,g.order_id,g.goods_qty,g.goods_name,g.is_shipping,c.mobile,c.receiver,c.address from {$this->table_name} as r
                join {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}order_goods as g on g.id = r.order_goods_id
                join {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}order_consignee as c on c.order_id = g.order_id
                where r.process = {$process}
                order by r.created_date desc";
        return $this->query($sql);
    }

    public function get_order($id)
    {
        $sql = "select o.payment_method,o.order_id,o.user_id from {$this->table_name} r 
                join {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}order_goods g on r.order_goods_id = g.id
                join {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}order o on g.order_id = o.order_id
                where r.id = :id";

        $res = $this->query($sql, [':id' => $id]);
        return $res[0];
    }
}