<?php

namespace plugin\push;

/**
 * Created by PhpStorm.
 * User: chenshengkun
 * Date: 2017/9/19
 * Time: 上午12:10
 */
class printer
{
    private $config = [
        'appid' => '5c2054918614',
        'app_secret' => '4878ab2e953eaa2ae124',
        'uuid' => "bc5d6d1f9ea552e7",
        'open_user_id' => 160822,
        'user_id' => 2
    ];

    private static $apis = [
        'queryDeviceStatus' => 'http://www.open.mstching.com/home/getdevicestate',
        'printContent' => 'http://www.open.mstching.com/home/printcontent2'
    ];

    const STATE_MAP = [
        '设备正常', '设备缺纸', '设备温度保护报警', '设备忙碌', '设备离线'
    ];

    private $print_contents = [];

    private $print_content_buff = [];

    public function get_print_contents()
    {
        return json_encode($this->print_contents);
    }

    /**
     * 绑定url
     * @var string
     */
    private $user_bind_url = "http://www.open.mstching.com/home/userbind";

    /**
     * 绑定用户
     * @return string
     */
    public function user_bind()
    {
        return http_post_data($this->user_bind_url, [
            'Uuid' => $this->config['uuid'],
            "UserId" => $this->config['user_id']
        ]);
    }

    public function isDeviceOnline()
    {
        $url = $this->getApiUrl(self::$apis['queryDeviceStatus']);
        $params = [
            'Uuid' => $this->config['uuid'],
        ];
        $objs = new \StdClass();
        foreach ($params as $key => $val) {
            $objs->$key = $val;
        }
        $json = json_encode($objs);

        $req = $this->http_post_json($url, $json);
        $res = json_decode($req, true);
        if ($res['Code'] === 200 && $res['State'] === 0) {
            return true;
        } else {
            return $res;
        }
    }

    private function getApiUrl($url)
    {
        $params = [
            'nonce' => strval($params['nonce'] = rand(pow(10, 7), pow(10, 10) - 1)),//随机数
            'timestamp' => strval(time()),
            'appsecret' => $this->config['app_secret']
        ];
        asort($params, SORT_LOCALE_STRING);//根据字典排序
        $signature_param = '';
        foreach ($params as $k => $v) {
            $signature_param .= $v;
        }
        $params['signature'] = sha1($signature_param);
        $params['appid'] = $this->config['appid'];
        return $url . '?appid=' . $params['appid'] . '&nonce=' . $params['nonce']
            . '&timestamp=' . $params['timestamp'] . '&signature=' . $params['signature'];
    }

    /**
     * 输出数据
     * @param $success
     * @param string $msg
     * @param array $data
     */
    protected function r($success, $msg = 'ok', array $data = [])
    {
        $success = (bool) $success;
        if (!empty($data)) {
            die(json_encode(['success' => $success, 'msg' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE));
        } else {
            die(json_encode(['success' => $success, 'msg' => $msg], JSON_UNESCAPED_UNICODE));
        }
    }

    public function print_content($print_contents)
    {
        kafka::produce(kafka::PRINT_ORDER, [
            'receipt' => [
                'content' => $print_contents
            ]
        ]);

        $this->r(true, '加入打印队列成功');
    }

    private function http_post_json($url, $jsonData)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function set_base_text($text)
    {
        if ($text) {
            $this->print_content_buff['BaseText'] = $text;
        }
    }

    public function bold($text = null)
    {
        $this->set_base_text($text);
        $this->print_content_buff['Bold'] = 1;
        return $this;
    }

    public function scale($text = null)
    {
        $this->set_base_text($text);
        $this->print_content_buff['FontSize'] = 1;
        return $this;
    }

    public function center($text = null)
    {
        $this->set_base_text($text);
        $this->print_content_buff['Alignment'] = 1;
        return $this;
    }

    public function br($text = null)
    {
        $this->set_base_text($text);
        $this->print_content_buff['BaseText'] .= "\n";
        return $this;
    }

    function init_print_content()
    {
        $this->print_content_buff = [];
    }

    function append($content = null)
    {
        $this->set_base_text($content);
        $this->print_content_buff['BaseText'] = base64_encode($this->charset_to_gbk($this->print_content_buff['BaseText']));
        $this->print_contents[] = $this->print_content_buff;
        $this->print_content_buff = [];
        return $this;
    }

    function divide_line()
    {
        return $this->padding_space('+', 32, STR_PAD_RIGHT, "+");
    }

    function black_line()
    {
        return $this->padding_space('', 32);
    }

    function price($price)
    {
        return strval($price * 1);
    }

    /**
     * 填充空格
     * @param $input
     * @param $padStr
     * @param $padType
     * @param $expandLength
     * @return string
     */
    public function padding_space($input, $expandLength, $padType = STR_PAD_RIGHT, $padStr = " ")
    {
        $diff = strlen($input) - mb_strlen($input);
        return str_pad($input, $expandLength + $diff / 2, $padStr, $padType);
    }

    /**
     * 转换编码
     * @param $mixed
     * @return array|string
     */
    private function charset_to_gbk($mixed)
    {
        if (is_array($mixed)) {
            foreach ($mixed as $k => $v) {
                if (is_array($v)) {
                    $mixed[$k] = $this->charset_to_gbk($v);
                } else {
                    $encode = mb_detect_encoding($v, array('ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5'));
                    if ($encode == 'UTF-8') {
                        $mixed[$k] = iconv('UTF-8', 'GBK//TRANSLIT//IGNORE', $v);
                    }
                }
            }
        } else {
            $encode = mb_detect_encoding($mixed, array('ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5'));
            //var_dump($encode);
            if ($encode == 'UTF-8') {
                $mixed = iconv('UTF-8', 'GBK//TRANSLIT//IGNORE', $mixed);
            }
        }
        return $mixed;
    }
}