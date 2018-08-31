<?php
/**
 * 更新商品销量
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/1/21
 * Time: 上午12:53
 */
namespace tasks;

class update_sales_volume_task extends task
{
    protected $task_name= "更新商品销量";

    public function run()
    {
        try {
            $source = $this->get_source_data_set();
            if (!empty($source)) {
                foreach ($source as $row) {
                    if (!$this->is_selled_amount_equal($row)) {
                        $this->update_row($row);
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
        return $this->dbh->query("select a.goods_id,a.selled_amount as old_selled_amount,count(b.order_id) as new_selled_amount from verydows_goods as a  left join verydows_order_goods  as b
        on a.goods_id = b.goods_id group by a.goods_id");
    }

    private function update_row($row)
    {
        $sql = "update verydows_goods set selled_amount = {$row['new_selled_amount']} where goods_id = {$row['goods_id']}";
        $affected_num = $this->dbh->exec($sql);
        if ($affected_num) {
            echo "更新 {$row['goods_id']} 的销量为 " . $row['new_selled_amount'] . "\n";
        }
    }

    private function is_selled_amount_equal($row)
    {
        if ($row['old_selled_amount'] != $row['new_selled_amount']) {
            return false;
        } else {
            return true;
        }
    }

}