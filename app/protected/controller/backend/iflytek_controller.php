<?php

/**
 * 讯飞语音
 * Class iflytek_controller
 */
class iflytek_controller extends general_controller
{
    private static $appid = '5b234d3e';

    private static $apikey = '6647146b8273f9c78fc6f004e9492fc7';

    private function get_oauth_header($json_request_param)
    {
        $header = [
            'X-Appid' => self::$appid,
            'X-CurTime' => (string) time(),
            'X-Param' => base64_encode($json_request_param),
            'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8'
        ];
        $header['X-CheckSum'] = md5(self::$apikey . $header['X-CurTime'] . $header['X-Param']);

        return $header;
    }

    public function action_combine_voice()
    {
        $text = request('text');
        //用户参数
        $apikey = self::$apikey;
        $appid = self::$appid;
        $CurTime = (string)time();
        $audioType = 'lame';
        //语音参数
        $json = array('aue' => $audioType, 'auf' => 'audio/L16;rate=16000', 'voice_name' => 'xiaoyan');
        $param = base64_encode(utf8_encode(json_encode($json, JSON_UNESCAPED_SLASHES)));
        $body = 'text=' . $text;
        //验证参数
        $checkSum = md5($apikey . $CurTime . $param);
        //http头
        $header = array(
            'X-Appid:' . $appid,
            'X-CurTime:' . $CurTime,
            'X-Param:' . $param,
            'X-CheckSum:' . $checkSum,
        );
        //请求
        $url = 'http://api.xfyun.cn/v1/service/v1/tts';
        $result = curl_post_ssl($url, $body, 30, $header);
        //处理响应
        header ("Content-Type:audio/mpeg MP3"); 
        echo($result);
        $saveType = $audioType == 'raw' ? '.wav' : '.mp3';
        $saveName = $CurTime . $saveType;
    }
}