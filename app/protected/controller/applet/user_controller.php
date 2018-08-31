<?php

use app\model\order_goods_model;
use app\model\order_model;
use app\model\token_model;
use app\model\user_account_model;
use app\model\user_model;
use app\model\user_oauth_model;
use plugin\push\phone;

/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/4
 * Time: 下午12:58
 */

class user_controller extends general_controller
{
    private static $msgs = [
        'login_success' => '登录成功',
        'bind_success' => '绑定成功',
        'unbind' => '该账户未注册绑定',
        'param_error' => '参数错误',
        'password_error' => '账号或密码错误',
        'already_bind' => '已绑定',
        'bind_fail' => '绑定失败'
    ];

    public function action_short_info()
    {
        $user_id = $this->is_logined();
        $user_model = new user_model();
        $user = $user_model->find(array('user_id' => $user_id),null,'avatar,mobile,username');
        $user_account_model = new user_account_model();
        $user_account = $user_account_model->get_user_account($user_id);
        $user['balance'] = $user_account['balance'];
        $this->r(true,'ok', $user);
    }

    /**
     * 小程序登录
     */
    public function action_login()
    {
        $user_model = new user_model();
        $token_model = new token_model();
        $user_oauth_model = new user_oauth_model();

        $oauth_info = $user_model->get_applet_auth_info(request('code'));
        if (empty($oauth_info)) {
            $this->r(false, self::$msgs['param_error']);
        }
        $oauth_key = $oauth_info['openid'];
        $user_oauth = $user_oauth_model->find(['oauth_key' => $oauth_key]);

        //1、已授权注册
        if (!empty($user_oauth)) {
            //unionid存在且未设置，则自动刷新
            if (isset($oauth_info['unionid']) && $user_oauth['unionid'] != $oauth_info['unionid']) {
                $user_oauth_model->update(['id' => $user_oauth['id']], ['unionid' => $oauth_info['unionid']]);
            }
            $jwt = $token_model->generate(['openid' => $oauth_key, 'user_id' => $user_oauth['user_id']]);
            $this->r(true, self::$msgs['login_success'], ['token' => $jwt]);
        }

        //2、未授权注册
        //在公众号或其他方式授权注册过
        if (isset($oauth_info['unionid'])
            && ($user_id = $user_oauth_model->is_authorized_by_unionid($oauth_info['unionid']))) {
            $row = array(
                'user_id' => $user_id,
                'oauth_key' => $oauth_key,
                'party' => "wx_applet",
                'unionid' => $oauth_info['unionid']
            );
            $user_oauth_model->create($row);
            $jwt = $token_model->generate(['openid' => $oauth_key, 'user_id' => $user_id]);
            $this->r(true, self::$msgs['login_success'], ['token' => $jwt]);
        } else {
            $this->r(false, self::$msgs['unbind'], ['openid' => $oauth_key]);
        }
    }

    public function action_get_openid()
    {
        $user_model = new user_model();
        $oauth_info = $user_model->get_applet_auth_info(request('code'));
        if (empty($oauth_info)) {
            $this->r(false, self::$msgs['param_error']);
        }

        $this->r(true, 'ok', ['openid' => $oauth_info['openid']]);
    }

    /**
     * 通过手机号和验证码登录
     */
    public function action_login_by_mobile()
    {
        $mobile = request('mobile');
        $res = phone::verify_code($mobile, request('code'));
        if (true !== $res) {
            $this->r(false, "验证码错误");
        }

        $user_model = new user_model();
        $user = $user_model->find(['mobile' => $mobile]);
        if (empty($user)) {
            $this->r(false, "该手机未注册");
        }

        $token_model = new token_model();
        $jwt = $token_model->generate(['user_id' => $user['user_id']]);

        $this->r(true, self::$msgs['login_success'], ['token' => $jwt]);
    }

    public function action_login_by_password()
    {
        $username = trim(request('username', '', 'post'));
        $password = request('password', '', 'post');
        $user_model = new user_model();
        if ($username && $user = $user_model->find_user_by_account($username, $password)) {
            $token_model = new token_model();
            $token = $token_model->generate(['user_id' => $user['user_id']]);
            $this->r(true, self::$msgs['login_success'], ['token' => $token]);
        } else {
            $this->r(false, self::$msgs['password_error']);
        }
    }

    public function action_recharge()
    {
        $user_id = $this->is_logined();
        $recharge_money = request('recharge_money');
        $order_model = new order_model();
        $data = array
        (
            'order_id' => $order_model->create_order_id(),
            'user_id' => $user_id,
            'shipping_method' => 0,
            'goods_amount' => $recharge_money,
            'shipping_amount' => 0,
            'order_amount' => $recharge_money,
            'order_type' => $order_model::ORDER_TYPE_RECHARGE,
            'created_date' => $_SERVER['REQUEST_TIME'],
            'order_status' => 1,
        );

        if($order_model->create($data))
        {
            $order_goods_model = new order_goods_model();
            $goods = array
            (
                'order_id' => $data['order_id'],
                'goods_id' => 0,
                'goods_name' => "余额充值",
                'goods_image' => "recharge.png",
                'goods_price' => $recharge_money,
                'goods_qty' => 1,
            );
            $order_goods_model->create($goods);
            $this->r(true, 'ok', ['order_id' => $data['order_id']]);
        }
        else
        {
            $this->r(false, "创建订单失败，请稍后重试");
        }
    }
}