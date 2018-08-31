<?php

namespace app\model;

class user_model extends model
{
    public $table_name = 'user';
    
    public $rules = array
    (
        'mobile' => array
        (
            'is_required' => array(TRUE, '手机号码不能为空'),
            'is_moblie_no' => array(TRUE, '无效的手机号码'),
        )
    );
    
    public $addrules = array
    (
        'mobile' => array
        (
            'addrule_mobile_exist' => '该手机已被使用',
        ),
    );
    
    //自定义验证器：检查用户名格式(可包含字母、数字，长度为5-16个字符)
    public function addrule_username_format($val)
    {
        return preg_match('/[a-zA-Z0-9]{5,25}$/', $val) != 0;
    }
    
    //自定义验证器：检查用户名是否存在
    public function addrule_username_exist($val)
    {
        if($this->find(array('username' => $val))) return FALSE;
        return TRUE;
    }
    
    //自定义验证器：检查密码格式(可包含字母、数字或特殊符号，长度为6-32个字符)
    public function addrule_password_format($val)
    {
        return preg_match('/^[\\~!@#$%^&*()-_=+|{}\[\],.?\/:;\'\"\d\w]{5,31}$/', $val) != 0;
    }
    
    //自定义验证器：检查手机号是否存在
    public function addrule_mobile_exist($val)
    {
        if($this->find(array('mobile' => $val))) return FALSE;
        return TRUE;
    }
    
    //自定义验证器：检查注册时验证码
    public function addrule_check_captcha($val)
    {
        if($GLOBALS['cfg']['captcha_user_register'])
        {
            if(empty($_SESSION['CAPTCHA']) || $_SESSION['CAPTCHA'] != $val)
            {
                unset($_SESSION['CAPTCHA']);
                return FALSE;
            }
        }
        unset($_SESSION['CAPTCHA']);
        return TRUE;
    }
    
    /**
     * 保持登录
     */
    public function stay_login($user_id, $password, $ip)
    {
        $cookie = vencrypt(md5("2.2.2.2".substr($password, 6, 24)).$user_id, TRUE);
        setcookie('USER_STAYED', $cookie, $_SERVER['REQUEST_TIME'] +  120 * 24 * 60 * 60, '/');
    }
    
    /**
     * 验证保持登陆
     */
    public function check_stayed($cookie, $ip)
    {
        if(!empty($cookie))
        {
            if($cookie = vdecrypt($cookie, 604800))
            {
                if($user = $this->find(array('user_id' => (int)substr($cookie, 32))))
                {
                    if(md5("2.2.2.2".substr($user['password'], 6, 24)) == substr($cookie, 0, 32))
                    {
                        $this->set_logined_info($ip, $user['user_id'], $user['username'], $user['avatar']);
                        return TRUE;
                    }
                }
            }
        }

        return FALSE;
    }
    
    /**
     * 设置登录后信息
     */
    public function set_logined_info($ip, $user_id, $username, $avatar = '')
    {
        $record_model = new user_record_model();
        $rec = $record_model->find(array('user_id' => $user_id));
        $record_model->update(array('user_id' => $user_id), array('last_date' => $_SERVER['REQUEST_TIME'], 'last_ip' => $ip));
        $_SESSION['USER']['USER_ID'] = $user_id;
        $_SESSION['USER']['LAST_DATE'] = $rec['last_date'];
        $_SESSION['USER']['LAST_IP'] = $rec['last_ip'];
        setcookie('LOGINED_USER', $username, null, '/');
        setcookie('USER_AVATAR', $avatar, null, '/');
        unset($_SESSION['LOGIN_TOKEN']);
    }
    
    /**
     * 用户注册
     */
    public function register($row)
    {
        unset($row['repassword'], $row['captcha']);
        $row['password'] = md5e($row['password']);
        if($user_id = $this->create($row))
        {
            $ip = get_ip();   
            $sql  = "INSERT INTO {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}user_account (`user_id`) VALUES ('{$user_id}');";
            $sql .= "INSERT INTO {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}user_profile (`user_id`) VALUES ('{$user_id}');";
            $sql .= "INSERT INTO {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}user_record (`user_id`, `created_date`, `created_ip`, `last_date`, `last_ip`) 
                     VALUES ('{$user_id}', '{$_SERVER['REQUEST_TIME']}', '{$ip}', '{$_SERVER['REQUEST_TIME']}', '{$ip}');
                    ";
            $this->execute($sql);
            $_SESSION['USER']['USER_ID'] = $user_id;
            $_SESSION['USER']['LAST_DATE'] = $_SERVER['REQUEST_TIME'];
            $_SESSION['USER']['LAST_IP'] = $ip;
            setcookie('LOGINED_USER', $row['username'], null, '/');
            return $user_id;
        }
        return FALSE;
    }
    
    /**
     * 注销登录信息
     */
    public function logout()
    {
        unset($_SESSION['USER'], $_SESSION['OAUTH']);
        $overtime = $_SERVER['REQUEST_TIME'] - 3600;
        setcookie('LOGINED_USER', null, $overtime, '/');
        setcookie('USER_AVATAR', null, $overtime, '/');
        setcookie('USER_STAYED', null, $overtime, '/');
    }

    public function find_user_by_account($account, $password)
    {
        $account = trim($account);
        $phone_reg = '/^(13[0-9]|15[012356789]|17[678]|18[0-9]|14[57])[0-9]{8}$/';
        $email_reg = '/^[0-9a-z_][_.0-9a-z-]{0,32}@([0-9a-z][0-9a-z-]{0,32}\.){1,4}[a-z]{2,4}$/i';
        if(preg_match($phone_reg, $account)){
            return $this->find(['mobile' => $account, 'password' => $password]);
        }elseif(preg_match($email_reg,$account)){
            return $this->find(['email' => $account, 'password' => $password]);
        }else{
            return $this->find(['username' => $account, 'password' => $password]);
        }
    }

    public function get_applet_auth_info($code)
    {
        if (!$code) {
            return [];
        }
        //获取小程序相关配置信息
        $oauth_model = new oauth_model();
        $config = $oauth_model->get_config("wx_applet");
        $appid = $config['appid'];
        $secret = $config['secret'];
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$secret}&js_code={$code}&grant_type=authorization_code";
        $response = file_get_contents($url);
        $responseArr = json_decode($response, 1);
        if (isset($responseArr['errcode'])) {
            return [];
        }

        return $responseArr;
    }
}