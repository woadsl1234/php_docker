<?php

use app\model\order_model;
use app\model\payment_method_model;
use app\model\user_account_model;
use wxpay\JsApiPay;
use wxpay\WxPayApi;
use wxpay\database\WxPayUnifiedOrder;
use wxpay\WxPayConfig;

class pay_controller extends general_controller
{
    public function action_index()
    {
        $user_id = $this->is_logined();
        $order_model = new order_model();
        if ($order = $order_model->find(array('order_id' => request('order_id'), 'user_id' => $user_id))) {
            $payment_list = vcache::instance()->payment_method_model('indexed_list');
            $this->order = $order;

            //余额充值
            if (2 == $order['order_type']) {
                foreach ($payment_list as $key => &$payment) {
                    //不支持货到付款和余额支付
                    if ($payment['pcode'] == 'balance' || $payment['pcode'] == 'cod') {
                        $payment['enable'] = 0;
                        $payment['reason'] = "不支持";
                    }
                }
            } else {
                foreach ($payment_list as $key => &$payment) {
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

                            $payment['href'] = url('mobile/user', 'recharge');
                        }
                    }
                }
            }
            $this->payment_list = $payment_list;
            $this->compiler('pay.html');
        } else {
            jump(url('mobile/main', '400'));
        }
    }

    public function action_return()
    {
        $pcode = request('pcode', '', 'get');
        $payment_model = new payment_method_model();
        if ($payment = $payment_model->find(array('pcode' => $pcode, 'enable' => 1), null, 'params')) {
            $plugin_name = "\\plugin\\payment\\".$pcode;
            /**
             * @var \plugin\payment\abstract_payment
             */
            $plugin = new $plugin_name($payment['params']);
            $this->status = $plugin->response($_GET);
            $this->message = $plugin->message;
            $this->order = $plugin->order;
            $this->compiler('pay_return.html');
        } else {
            jump(url('mobile/main', '400'));
        }
    }

    /**
     * 唤起微信支付
     */
    public function action_invoke_wxpay()
    {
        if (!isset($_SESSION['WX_PAY_ARGS'])) {
            $this->prompt('error', '参数错误');
        }
        //支付配置
        $payment_model = new payment_method_model();
        $wxpay_params = $payment_model->get_pay_params('wxpay');
        WxPayConfig::setAppid($wxpay_params['appid']);
        WxPayConfig::setMchid($wxpay_params['mch_id']);
        WxPayConfig::setKey($wxpay_params['key']);
        WxPayConfig::setAppSecret($wxpay_params['secret']);

        //获取用户openid
        $tools = new JsApiPay();
        $openId = $tools->GetOpenid();

        //统一下单
        $wxPayArgs = $_SESSION['WX_PAY_ARGS'];
        $input = new WxPayUnifiedOrder();
        $input->SetBody($GLOBALS['cfg']['site_name']);
        $input->SetOut_trade_no($wxPayArgs['order_id']);

        $input->SetTotal_fee((int)($wxPayArgs['order_amount'] * 100));
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        $input->SetNotify_url($GLOBALS['cfg']['http_host']."/api/pay/notify/wxpay");
        $input->SetTrade_type("JSAPI");
        $input->SetOpenid($openId);
        try {
            $order = WxPayApi::unifiedOrder($input);
            $this->jsApiParameters = json_decode($tools->GetJsApiParameters($order));
            $this->jumpUrl = url('mobile/order', 'view', array('id' => $wxPayArgs['order_id']));
            $this->compiler('invoke_wxpay.html');
        }catch (Exception $e) {
            $this->prompt('error', $e->getMessage());
        }
    }

    public function action_invoke_alipay()
    {
        if (!isset($_GET['pay_url'])) {
            $this->prompt('error', '参数错误');
        }
        if (!$this->is_weixin_browser()) {
            jump(urldecode($_GET['pay_url']));
            return;
        }
        //是微信浏览器做特殊处理
        $this->phone_type = $this->get_mobile_phone_type();
        $this->compiler('invoke_alipay.html');
    }

    /**
     *
     * @return string
     */
    private function get_mobile_phone_type()
    {
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){
            return 'ios';
        } else {
            return 'android';
        }
    }

    /**
     *
     */
    private function is_weixin_browser()
    {
        if( !preg_match('/micromessenger/i', strtolower($_SERVER['HTTP_USER_AGENT'])) ) {
            return false;
        } else {
            return true;
        }
    }
}