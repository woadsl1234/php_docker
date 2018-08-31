<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/3/9
 * Time: 下午1:42
 */

namespace app\model;

class goods_instead_sell_model extends model
{
    public $table_name = 'goods_instead_sell';

    const status_map = [
        self::status_wait_process => "待上架",
        self::status_pass => "已上架",
        self::status_selled => "已售出",
        self::status_finish => "已结算"
    ];

    const status_wait_process = 0;
    const status_pass = 1;
    const status_selled = 2;
    const status_finish = 3;

    public function get_all_of_single_user($user_id)
    {
        return $this->find_all(['user_id' => $user_id]);
    }

    public function putway($instead_sell_id, $batches)
    {
        $goods_model = new goods_model();
        foreach ($batches as $batch)
        {
            $goods_model->incr(['goods_id' => $batch['goods_id']], 'instead_sell_stock_qty', $batch['qty']);
            $batch['instead_sell_id'] = $instead_sell_id;
            $this->create($batch);
        }
    }

    public function user_goods_list($user_id)
    {
        $sql = "select s.*,g.goods_name from $this->table_name as s 
                left join {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}instead_sell_apply as a on s.instead_sell_id = a.id
                left join {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}goods as g on g.goods_id = s.goods_id
                where a.user_id = :user_id";
        return $this->query($sql,array(':user_id' => $user_id));
    }

    public function search_proper_goods($goods_id, $qty)
    {
        $sql = "select * from $this->table_name where goods_id = :goods_id order by qty desc";
        $instead_sell_goods_list = $this->query($sql, [':goods_id' => $goods_id]);
        $res = [];
        foreach ($instead_sell_goods_list as $row) {
            if ($qty == 0) {
                break;
            }
            if ($row['qty'] >= $qty) {
                $res[] = ['goods_instead_id' => $row['id'], 'qty' => $qty];
                break;
            } else {
                $res[] = ['goods_instead_id' => $row['id'], 'qty' => $row['qty']];
                $qty -= $row['qty'];
            }
        }

        return $res;
    }
}