<?php

namespace app\model;

class user_oauth_model extends model
{
    public $table_name = 'user_oauth';
    
    /**
     * 是否已授权
     */
    public function is_authorized($party, $oauth_key, &$unionid = 0)
    {
        $sql = "SELECT a.user_id, a.unionid, b.username, b.avatar FROM {$this->table_name} AS a 
                INNER JOIN {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}user AS b
                ON a.user_id = b.user_id WHERE party = :party AND oauth_key = :oauth_key
                LIMIT 1
               ";
        
        if($res = $this->query($sql, array(':party' => $party, 'oauth_key' => $oauth_key)))
        {
            $res = array_pop($res);
            $user_model = new user_model();
            $user_model->set_logined_info(get_ip(), $res['user_id'], $res['username'], $res['avatar']);
            $unionid = $res['unionid'];
            unset($_SESSION['OAUTH']);
            return TRUE;
        }
        return FALSE;
    }

    /**
     * 判断unionid是否已授权
     */
    public function is_authorized_by_unionid($unionid)
    {
        $sql = "SELECT a.user_id, b.username, b.avatar FROM {$this->table_name} AS a 
                INNER JOIN {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}user AS b
                ON a.user_id = b.user_id WHERE unionid = :unionid
                LIMIT 1
               ";

        if($res = $this->query($sql, array('unionid' => $unionid)))
        {
            $res = array_pop($res);
            $user_model = new user_model();
            $user_model->set_logined_info(get_ip(), $res['user_id'], $res['username'], $res['avatar']);
            unset($_SESSION['OAUTH']);
            return $res['user_id'];
        }
        return FALSE;
    }

}