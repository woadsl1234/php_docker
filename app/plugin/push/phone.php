<?php

/**
 * Created by PhpStorm.
 * User: chenshengkun
 * Date: 2017/12/24
 * Time: 下午3:26
 */

namespace plugin\push;

class phone
{
    const SCENE_CODE = 160443; // 短信验证码
    const SCENE_BALANCE_INFO = 160445; // 余额变动提醒
    const SCENE_PAY_SUCCESS = 160446; // 支付成功
    const SCENE_SHIPPING = 160447; // 出库通知
    const SCENE_REFUND_PASS = 160448; // 退款申请通过提醒
    const SCENE_INSTEAD_SELL_SUCCESS = 160450; //帮卖成功通知

    public static function send_code($mobile)
    {
        $code = rand(1001, 9999);

        // 发布验证码消息
        kafka::produce(kafka::VERIFY_CODE, [
            'message' => [
                'phoneNumber' => $mobile,
                'tempId' => phone::SCENE_CODE,
                'params' => [$code],
            ],
        ]);

        // 设置验证码
        $vcache = \vcache::instance();
        $vcache->set("mobile_".$mobile, $code, 10 * 60);
    }

    /**
     * 使验证码失效
     * @param $code
     * @param $mobile
     * @return bool
     */
    public static function verify_code($mobile, $code)
    {
        $vcache = \vcache::instance();
        $code = (int) $code;
        $key = "mobile_" . $mobile;
        $real_code = (int) $vcache->get($key);

        if ($real_code && $code && $real_code === $code) {
            $vcache->delete($key);
            return true;
        }

        return false;
    }
}