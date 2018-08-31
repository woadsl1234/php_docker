<?php

namespace app\model;

class recycle_model extends model
{
	public $table_name = 'recycle_goods';

	public $rules = array
    (
        'price' => array
        (
            'is_required' => array(TRUE, '回收价不能为空'),
        ),
        'goods_id' => array
        (
            'is_required' => array(TRUE, '请选择商品'),
        ),
        'total' => array
        (
            'is_required' => array(TRUE, '计划数不能为空'),
        )
    );

    public function get_list()
    {
    	$sql = "select r.*,g.goods_sn,g.goods_name,g.goods_image from {$this->table_name} as r join {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}goods as g on r.goods_id = g.goods_id";
    	return $this->query($sql);
    } 

    public function get($id)
    {
    	$sql = "select r.*,g.goods_sn,g.goods_name,g.original_price,g.now_price from {$this->table_name} as r join {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}goods as g on r.goods_id = g.goods_id where r.id = :id";
    	$res = $this->query($sql, array(':id' => $id));
    	return $res[0];
    }
}