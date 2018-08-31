<?php

use app\model\token_model;
use app\model\user_model;
use app\model\user_oauth_model;

/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/3/2
 * Time: 下午10:46
 */



class oauth_controller extends general_controller
{
    private static $msgs = [
        'login_success' => '登录成功',
        'bind_success' => '绑定成功',
        'unbind' => '该账户未注册绑定',
        'param_error' => '参数错误',
        'password_error' => '用户名或密码错误',
        'already_bind' => '已绑定',
        'bind_fail' => '绑定失败'
    ];

    /**
     * 绑定小程序
     */
    public function action_bind()
    {
        $row['oauth_key'] = request("openid", "");
        if ($row['oauth_key'] == "") {
            $this->r(false, self::$msgs['param_error']);
        }
        $user_id = $this->is_logined();

        $row['user_id'] = $user_id;
        $row['party'] = "wx_applet";
        $oauth_model = new user_oauth_model();
        if ($oauth_model->find($row)) {
            $this->r(false, self::$msgs['already_bind']);
        }
        if (!$oauth_model->create($row)) {
            $this->r(false, self::$msgs['bind_fail']);
        }

        $this->r(true, self::$msgs['bind_success']);
    }
}