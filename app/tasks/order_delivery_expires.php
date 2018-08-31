<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/3/7
 * Time: 上午12:39
 */

namespace tasks;

class order_delivery_expires extends task
{
    protected $task_name= "超时自动签收";

    public function run()
    {
        try {
            $source = $this->get_source_data_set();
            if (!empty($source)) {
                foreach ($source as $row) {
                    if ($this->is_delivery_expire($row['order_id'])) {
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
        return $this->dbh->query("select * from verydows_order where order_status = 3");
    }

    private function update_row($row)
    {
        $finish_date = time();
        $sql = "update verydows_order set order_status = 4, finish_date = $finish_date where order_id = {$row['order_id']}";
        $affected_num = $this->dbh->exec($sql);
        if ($affected_num) {
            echo "订单 {$row['order_id']} 自动签收成功\n";
        }
    }

    /**
     * 订单号
     * @param $order_id
     * @return bool
     */
    private function is_delivery_expire($order_id)
    {
        $sql = "select dateline from verydows_order_shipping where order_id = {$order_id} order by dateline desc limit 1";
        $query = $this->dbh->query($sql);
        $shipping = $query->fetch(\PDO::FETCH_ASSOC);
        $countdown = $shipping['dateline'] + 3 * 86400 - $_SERVER['REQUEST_TIME'];
        return $countdown > 0 ? false : true;
    }
}