<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/3/7
 * Time: 上午12:39
 */

namespace tasks;

class auto_open_reserve extends task
{
    protected $task_name= "缺货时自动开启可预定状态";

    public function run()
    {
        try {
            $source = $this->get_source_data_set();
            if (!empty($source)) {
                foreach ($source as $row) {
                    $this->update_row($row);
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
        return $this->dbh->query("select * from verydows_goods where auto_open_reserve = 1 and stock_qty = 0 and instead_sell_stock_qty = 0");
    }

    private function update_row($row)
    {
        $sql = "update verydows_goods set pre_sell = 1, now_price = {$row['reserve_now_price']}, stock_qty = {$row['reserve_stock_qty']} 
                  where goods_id = {$row['goods_id']}";
        $affected_num = $this->dbh->exec($sql);
        if ($affected_num) {
            echo "更新 {$row['goods_id']} 的价格为 " . $row['reserve_now_price'] . "，库存为" . $row['reserve_stock_qty'] . "\n";
        }
    }
}