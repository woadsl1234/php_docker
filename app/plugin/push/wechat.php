<?php

/*
* Created by PhpStorm.
* User: StandOpen
* Date: 15-1-7
* Time: 9:41
*/

namespace plugin\push;

use app\model\oauth_model;

class wechat
{
    protected static $appid;
    protected static $secret;

    function __construct()
    {
        $oauth_model = new oauth_model();
        $config = $oauth_model->get_config("wechat");
        self::$appid = $config['appid'];
        self::$secret = $config['secret'];
    }

    /**
     * 发送post请求
     * @param string $url
     * @param string $param
     * @return bool|mixed
     */
    function request_post($url = '', $param = '')
    {
        if (empty($url) || empty($param)) {
            return false;
        }
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $param); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        $tmpInfo = curl_exec($curl); // 执行操作
        if (curl_errno($curl)) {
            echo 'Errno' . curl_error($curl);//捕抓异常
        }
        curl_close($curl); // 关闭CURL会话
        return $tmpInfo; // 返回数据
    }

    /**
     * 发送get请求
     * @param string $url
     * @return bool|mixed
     */
    function request_get($url = '')
    {
        if (empty($url)) {
            return null;
        }
        return file_get_contents($url);
    }

    /**
     * @return mixed
     * 获取token
     */
    public function get_access_token()
    {
        $vcache = \vcache::instance();
        $wechat_access_token = $vcache->get('wechat_access_token');
        if (!empty($wechat_access_token)) {
            return $wechat_access_token;
        }
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid="
            . self::$appid . "&secret=" . self::$secret;
        $content = $this->request_get($url);
        $arr = json_decode(stripslashes($content), true);
        $vcache->set('wechat_access_token', $arr['access_token'], 1 * 60 * 60);//设置一小时过期

        return $arr['access_token'];
    }

    /**
     * @param $template_id
     * @param $touser
     * @param $send_data
     * @param string $page_path
     * @return bool
     */
    public function doSend($template_id, $touser, $send_data, $page_path = '')
    {
        $template = array(
            'touser' => $touser,
            'template_id' => $template_id,
            'topcolor' => '#7B68EE',
            'data' => $send_data
        );
        if ($GLOBALS['run_mode'] === 'online') {
            $template['miniprogram'] = [
                'appid' => 'wx90bb220f309fc2e4',
                'pagepath' => $page_path,
            ];
        }

        $json_template = json_encode($template);
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $this->get_access_token();
        $dataRes = $this->request_post($url, urldecode($json_template));
        if (isset($dataRes['errcode']) && isset($dataRes['errcode']) == 0) {
            return true;
        } else {
            return false;
        }
    }
}