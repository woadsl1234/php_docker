<?php

use app\model\payment_method_model;

/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/4/28
 * Time: 上午2:47
 */

class qrcode_controller extends general_controller
{
    public function action_generate()
    {
        $path = request('path', 'pages/index');
        $width = request('width', '430');
        $auto_color = request('auto_color', false);

        $payment_method_model  = new payment_method_model();
        $pay_params = $payment_method_model->get_pay_params('wxpay');

        // 获取access_token
        $vcache = vcache::instance();
        $access_token_key = 'access_token';
        $access_token = $vcache->get($access_token_key);
        if ($access_token === false) {
            $accessTokenObject = json_decode(file_get_contents(
                'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$pay_params['appid'].'&secret='.$pay_params['secret']));
            $access_token = $accessTokenObject->access_token;
            $vcache->set($access_token_key, $access_token, 60 * 60);
        }
        $url = 'https://api.weixin.qq.com/wxa/getwxacode?access_token=' . $access_token;
        $json = json_encode([
            'path' => $path,
            'width' => $width,
            'auto_color' => $auto_color
        ]);
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,0);//严格校验
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //设置header
        header('Content-Type: image/jpeg');
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        //运行curl
        $data = curl_exec($ch);

        if ($data === false) {
            echo 'Curl error: ' . curl_error($ch);
        } else {
            echo $data;
        }
        //返回结果
        curl_close($ch);
    }
}