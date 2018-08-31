<?php

use app\model\user_model;
use plugin\push\kafka;
use plugin\push\phone;

/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/3/2
 * Time: 下午8:38
 */


class mobile_controller extends general_controller
{
    public function action_send()
    {
        $mobile = request('mobile');
        phone::send_code($mobile);

        $user_model = new user_model();
        $user = $user_model->find(['mobile' => $mobile]);

        $this->r(true, "发送成功",['exist' => (int)!empty($user)]);
    }

    public function action_verify()
    {
        $res = phone::verify_code(request('mobile'), request('code'));
        if (true !== $res) {
            $this->r(false, "验证失败");
        }

        $this->r(true, "验证成功");
    }

}