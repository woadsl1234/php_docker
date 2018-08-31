<?php

use app\model\order_goods_model;
use app\model\order_model;
use app\model\payment_method_model;
use app\model\token_model;
use app\model\user_account_model;
use app\model\user_model;
use app\model\user_oauth_model;

/**
 * Created by PhpStorm.
 * User: chenshengkun
 * Date: 2017/7/23
 * Time: 下午1:03
 */
class pay_controller extends general_controller
{
    private $unifiedorder_url = "https://api.mch.weixin.qq.com/pay/unifiedorder";

    private $appid = "wx90bb220f309fc2e4";

    private $secret = "00e18ed3888fad1363f611d2e11731f9";

    /**
     * 获取微信支付配置
     * @return array|mixed
     */
    private function get_pay_params()
    {
        $payment_method_model  = new payment_method_model();
        $pay_params = $payment_method_model->get_pay_params('wxpay');
        if (empty($pay_params)) {
            $this->r(false,'支付配置不存在');
        }

        return $pay_params;
    }

    /**
     * 预支付请求接口(POST)
     * @param string $openid openid
     * @param string $body 商品简单描述
     * @param string $order_sn 订单编号
     * @param string $total_fee 金额
     * @return []
     */
    public function action_prepay()
    {
        $order_id = request('order_id', '');
        $openid = request('openid', '');
        //兼容老的小程序版本接口
        if (!$openid) {
            $order_model = new order_model();
            $order = $order_model->find(['order_id' => $order_id]);
            $user_oauth_model = new user_oauth_model();
            $oauth = $user_oauth_model->find(array('user_id' => $order['user_id'], 'party' => 'wx_applet'));
            $openid = $oauth['oauth_key'];
        }
        $config = $this->get_pay_params();
        $body = request('body', ' ');

        $total_fee = request('total_fee', 0);
        $unifiedorder = array(
            'appid' => $this->appid,
            'mch_id' => $config['mch_id'],
            'nonce_str' => getNonceStr(),
            'body' => $body,
            'out_trade_no' => $order_id,
            'total_fee' => $total_fee * 100,
            'spbill_create_ip' => get_ip(),
            'notify_url' => $GLOBALS['cfg']['http_host'] . '/applet/pay/notify',
            'trade_type' => 'JSAPI',
            'openid' => $openid
        );
        $unifiedorder['sign'] = self::makeSign($unifiedorder);
        //请求数据
        $xmldata = array2xml($unifiedorder);
        $res = curl_post_ssl($this->unifiedorder_url, $xmldata);
        if (!$res) {
            $this->r(false,'网络错误');
        }
        $content = xml2array($res);
        if (strval($content['return_code']) == 'FAIL') {
            $this->r(false, strval($content['return_msg']));
        }
        $res = ['prepay_id' => $content['prepay_id']];
        $order_model = new order_model();
        $order_model->update(['order_id' => $order_id], $res);
        $this->r(true, 'ok', $res);
    }

    /**
     * 进行支付接口(POST)
     * @param string $prepay_id 预支付ID(调用prepay()方法之后的返回数据中获取)
     * @return  json的数据
     */
    public function action_pay()
    {
        $prepay_id = request('prepay_id', 0, 'post');
        $data = array(
            'appId' => $this->appid,
            'timeStamp' => time(),
            'nonceStr' => getNonceStr(),
            'package' => 'prepay_id=' . $prepay_id,
            'signType' => 'MD5'
        );
        $data['paySign'] = self::makeSign($data);

        $this->r(true,'ok', $data);
    }

    //微信支付回调验证
    public function action_notify()
    {
        $pcode = 'wxpay';
        $payment_model = new payment_method_model();
        if ($payment = $payment_model->find(array('pcode' => $pcode, 'enable' => 1), null, 'params')) {
            $plugin_name = "\\plugin\\payment\\".$pcode;
            //修改为小程序的appid
            $params = json_decode($payment['params'], true);
            $params['appid'] = $this->appid;
            $params['secret'] = $this->secret;
            $payment['params'] = json_encode($params);
            /**
             * @var \plugin\payment\abstract_payment
             */
            $plugin = new $plugin_name($payment['params']);
            echo $plugin->notify();
        }
    }

    /**
     * 生成签名
     * @return string 签名
     */
    protected function makeSign($data)
    {
        //获取微信支付秘钥
        $pay_params = $this->get_pay_params();
        $key = $pay_params['key'];
        // 去空
        $data = array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a = http_build_query($data);
        $string_a = urldecode($string_a);
        //签名步骤二：在string后加入KEY
        //$config=$this->config;
        $string_sign_temp = $string_a . "&key=" . $key;
        //签名步骤三：MD5加密
        $sign = md5($string_sign_temp);
        // 签名步骤四：所有字符转为大写
        return strtoupper($sign);
    }

    public function action_methods()
    {
        $user_id = $this->is_logined();
        $order_model = new order_model();
        if ($order = $order_model->find(array('order_id' => request('order_id'), 'user_id' => $user_id))) {
            $payment_list = vcache::instance()->payment_method_model('indexed_list');
            $this->order = $order;

            $methods = [];
            //余额充值
            if ($order_model::ORDER_TYPE_RECHARGE == $order['order_type']) {
                foreach ($payment_list as $key => $payment) {
                    //只支持微信支付
                    if ($payment['pcode'] == 'wxpay') {
                        $methods[] = $payment;
                        break;
                    }
                }
            } else {
                foreach ($payment_list as $key => $payment) {
                    //不支持支付宝
                    if ($payment['pcode'] == 'alipay' || $payment['enable'] == 0) {
                        continue;
                    }

                    //余额付款不足时不可选择
                    if ($payment['pcode'] == 'balance') {
                        $user_account_model = new user_account_model();
                        $user_account = $user_account_model->get_user_account($user_id);
                        $dis = ($order['order_amount'] - $user_account['balance']) * 1;
                        if ($dis > 0) {
                            $payment['enable'] = 0;
                            if ($user_account['balance'] > 0) {
                                $payment['reason'] = "还差{$dis}元";
                            } else {
                                $payment['reason'] = "余额不足";
                            }

                            $payment['href'] = '';
                        }
                    }

                    $methods[] = $payment;
                }
            }

            echo json_encode(['status' => 'success', 'data' => $methods]);
        }
    }

    public function action_return()
    {
        $this->is_logined();
        $pcode = request('pcode', '', 'get');
        $payment_model = new payment_method_model();
        if ($payment = $payment_model->find(array('pcode' => $pcode, 'enable' => 1), null, 'params')) {
            $plugin_name = "\\plugin\\payment\\".$pcode;
            /**
             * @var \plugin\payment\abstract_payment
             */
            $plugin = new $plugin_name($payment['params']);
            $status = $plugin->response($_GET);
            if ($status == 'success') {
                $this->r(true);
            } else {
                $this->r(false, $plugin->message);
            }
        } else {
            jump(url('mobile/main', '400'));
        }
    }

    public function action_show_agent()
    {
        $order_id = request('order_id', 0);
        if (!$order_id) {
            $this->r(false,'order not exist');
        }
        $order_model = new order_model();
        $order = $order_model->find(['order_id' => $order_id],null, 'user_id,order_amount,order_status,created_date');
        if (empty($order)) {
            $this->r(false,'order not exist');
        }
        $user_id = $this->get_user_id(request('token'));
        $data = [];
        $owner_id = $order['user_id'];
        $data['is_buyer'] = $owner_id == $user_id ? true : false;
        unset($order['user_id']);
        $user_model = new user_model();
        $user = $user_model->find(['user_id' => $owner_id], null, 'avatar');
        if (!trim($user['avatar'])) {
            $user['avatar'] = $GLOBALS['cfg']['image_host'] . 'default-headimg.jpg';
        }
        $data['buyer'] = $user;
        $data['order'] = $order;
        $order_goods_model = new order_goods_model();
        $data['order_goods'] = $order_goods_model->get_goods_list($order_id);
        $this->r(true,'ok',$data);
    }

    private function get_user_id($token)
    {
        if (!$token) {
            return 0;
        }
        $token_model = new token_model();
        $data = $token_model->resolve($token);
        if(isset($data['user_id']) && $data['user_id'])
        {
            return $data['user_id'];
        } else {
            return 0;
        }
    }
}