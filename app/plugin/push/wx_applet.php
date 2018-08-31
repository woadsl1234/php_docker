<?php

/*
* Created by PhpStorm.
* User: StandOpen
* Date: 15-1-7
* Time: 9:41
*/
namespace plugin\push;

class wx_applet
{
    protected static $appid;
    protected static $secret;
    protected $accessToken;

    function __construct($appid, $secret)
    {
        self::$appid = $appid;
        self::$secret = $secret;
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
     * 获取token（添加缓存）
     */
    public function get_access_token()
    {
        $key = 'wx_applet_access_token';

        $vcache = \vcache::instance();
        $wechat_access_token = $vcache->get($key);
        if (!empty($wechat_access_token)) {
            return $wechat_access_token;
        }
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid="
            . self::$appid . "&secret=" . self::$secret;
        $content = $this->request_get($url);
        $arr = json_decode(stripslashes($content), true);
        $vcache->set($key, $arr['access_token'], 1 * 60 * 60);//设置一小时过期

        return $arr['access_token'];
    }
}