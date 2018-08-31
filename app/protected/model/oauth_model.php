<?php

namespace app\model;

class oauth_model extends model
{
    public $table_name = 'oauth';
    
    /**
     * 获取启用的授权登录连接列表
     */
    public function get_enable_list($mod = '')
    {
        if($list = $this->find_all(array('enable' => 1))) 
        {
            $state = md5(uniqid(rand(), TRUE));
            foreach($list as &$v)
            {
                $oauth_obj = \plugin::instance('oauth', $v['party'], array($v['params'], $mod), TRUE);
                $v['url'] = $oauth_obj->create_login_url($state);
            }
        }
        return $list;
    }

    public function get_config($party)
    {
        $find = $this->find(array('party' => $party));
        return json_decode($find['params'],true);
    }

    public function get_oauth_keys($list)
    {
        $reset_list = array();
        foreach ($list as $k => $v)
        {
            $reset_list[] = $v['user_id'];
        }
        $binds[':list_string'] = implode(',',$reset_list);
        $binds[':party'] = 'wechat';
        $sql = "select oauth_key from {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}user_oauth  where user_id in (:list_string) and party = :party";
        $res = $this->query($sql,$binds);
        if(!empty($res))
        {
            $reset_res = array();
            foreach ($res as $k => $v)
            {
                $reset_res[] = $v['oauth_key'];
            }
            return implode(',',$reset_res);
        }
        return '';
    }
}