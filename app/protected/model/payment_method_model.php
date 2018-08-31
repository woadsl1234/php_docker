<?php

namespace app\model;

class payment_method_model extends model
{
    public $table_name = 'payment_method';
    
    public $type_map = array('线上支付', '线下支付');
    
    public $rules = array
    (
        'instruction' => array('max_length' => array(240, '说明不能超过240个字符')),
        'seq' => array('is_seq' => array(TRUE, '排序必须为0-99之间的整数')),
    );

    public function payment_method_map()
    {
        //获取所有付款方式
        $result = $find_all = $this->find_all();
        $map = array();
        foreach ($result as $key => $v)
        {
            $map[$v['id']] = $v['name'];
        }
        return $map;
    }
    
    /**
     * 支付方式列表(以主键作为数据列表索引)
     */
    public function indexed_list()
    {
        if($find_all = $this->find_all(array(),'seq ASC')) return array_column($find_all, null, 'id');
        return $find_all;
    }

    /**
     * @param $pcode
     * @return array|mixed
     */
    public function get_pay_params($pcode)
    {
        $payment = $this->find(array('pcode' => $pcode, 'enable' => 1));
        return $payment ? json_decode($payment['params'], true) : [];
    }

}