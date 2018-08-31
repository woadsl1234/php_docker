<?php

namespace app\model;

use area;

class user_consignee_model extends model
{
    public $table_name = 'user_consignee';
    
    public $rules = array
    (
        'receiver' => array
        (
            'is_required' => array(TRUE, '收件人不能为空'),
            'max_length' => array(20, '收件人不能超过20个字符'),
        ),
        'province' => array
        (
            'is_required' => array(TRUE, '省份不能为空'),
        ),
        'city' => array
        (
            'is_required' => array(TRUE, '城市不能为空'),
        ),
        'borough' => array
        (
            'is_required' => array(TRUE, '地区不能为空'),
        ),
        'address' => array
        (
            'is_required' => array(TRUE, '详细地址不能为空'),
        ),
        'room' => array
        (
            'is_required' => array(TRUE, '门牌号不能为空'),
        ),
        'mobile' => array
        (
            'is_required' => array(TRUE, '手机号码不能为空'),
            'is_moblie_no' => array(TRUE, '手机号码格式不正确'),
        ),
    );
    
    public $addrules = array
    (
        'user_id' => array('addrule_exceeds_limit' => '您的收件人地址数量已达到最大数量限制'),
    );
    
    //自定义验证器：检查收件人数量是否超过限制
    public function addrule_exceeds_limit($val)
    {
        return $this->find_count(array('user_id' => $val)) < $GLOBALS['cfg']['user_consignee_limits'];
    }
    
    /**
     * 获取用户收件人地址列表
     */
    public function get_user_consignee_list($user_id)
    {
        if($consignee_list = $this->find_all(array('user_id' => $user_id), 'is_default DESC, id DESC'))
        {
            $area = new area();
            foreach($consignee_list as $v)
            {
                $v['json'] = json_encode($v);
                $v['area'] = $area->get_area_name($v['province'], $v['city'], $v['borough']);
                $res[] = $v;
            }
            return $res;
        }
        return null;
    }

}