<?php
/**
 * WxPay Payment
 * @author Cigery
 */

namespace plugin\payment;

use app\model\order_model;
use app\model\payment_method_model;
use wxpay\database\WxPayRefund;
use wxpay\WxPayConfig;
use wxpay\WxPayApi;

class wxpay extends abstract_payment
{
    protected $pcode = 'wxpay';

    public $ret_success = '<xml><return_code>SUCCESS</return_code></xml>';

    public $ret_fail = '<xml><return_code>FAIL</return_code></xml>';

    protected $transfer_url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';

    const SSL_KEY_PATH = __dir__ . '/cert/wxpay/apiclient_key.pem';

    const SSL_CERT_PATH = __dir__ . '/cert/wxpay/apiclient_cert.pem';

    public function create_pay_url($args)
    {
        $_SESSION['WX_PAY_ARGS'] = $args;

        return url('mobile/pay', 'invoke_wxpay');
    }

    protected function check_result_code($args)
    {
        return $args['result_code'] === "SUCCESS";
    }

    /**
     * @throws \Exception
     */
    protected function check_sign($args)
    {
        $data_sign = $args['sign'];
        // sign不参与签名算法
        unset($args['sign']);
        $sign = $this->make_sign($args);

        return $sign === $data_sign;
    }

    protected function save_args($args)
    {
        parent::save_args($args);
        $this->order_id = $args['out_trade_no'];
        $this->thirdparty_trade_id = $args['transaction_id'];
    }

    /**
     * 生成签名
     * @return string $result 签名
     */
    protected function make_sign($data)
    {
        $key = $this->config['key'];
        // 去空
        $data = array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a = http_build_query($data);
        $string_a = urldecode($string_a);
        //签名步骤二：在string后加入KEY
        $string_sign_temp = $string_a . "&key=" . $key;
        //签名步骤三：MD5加密
        $sign = md5($string_sign_temp);
        // 签名步骤四：所有字符转为大写
        $result = strtoupper($sign);

        return $result;
    }

    protected function get_data_from_request()
    {
        $data = file_get_contents("php://input");
        return xml2array($data);
    }

    /**
     * 转账到零钱
     */
    public function transfer_to_change($openid, $money, $desc, $user_real_name = "")
    {
        $Parameters=array();
        $Parameters['amount']           = $money;//企业付款金额，单位为分
        $Parameters['check_name']       = 'NO_CHECK';//NO_CHECK：不校验真实姓名 FORCE_CHECK：强校验真实姓名（未实名认证的用户会校验失败，无法转账） OPTION_CHECK：针对已实名认证的用户才校验真实姓名（未实名认证用户不校验，可以转账成功）
        $Parameters['desc']             = $desc;//企业付款操作说明信息。必填。
        $Parameters['mch_appid']        = $this->config['appid'];;//微信分配的公众账号ID
        $Parameters['mchid']            = $this->config['mch_id'];//微信支付分配的商户号
        $Parameters['nonce_str']        = getNonceStr();//随机字符串，不长于32位
        $Parameters['openid']           = $openid;//商户appid下，某用户的openid
        $Parameters['re_user_name']     = $user_real_name;//收款用户真实姓名。 如果check_name设置为FORCE_CHECK或OPTION_CHECK，则必填用户真实姓名
        $Parameters['spbill_create_ip'] = $_SERVER['SERVER_ADDR'];//调用接口的机器Ip地址
        $Parameters['sign']             = $this->make_sign($Parameters);//签名
        $xml  = array2xml($Parameters);
        $ssl_config = [
            'cert_path' => self::SSL_CERT_PATH,
            'key_path' => self::SSL_KEY_PATH
        ];
        $res  = curl_post_ssl($this->transfer_url, $xml, 30, [], $ssl_config);
        $return = xml2array($res);
        return true;
    }

    /**
     * @param $order_id
     * @param $refund_id
     * @param $amount
     * @param $reason
     * @return bool
     * @throws \wxpay\WxPayException
     */
    public function refund($order_id, $refund_id, $amount, $reason)
    {
        //支付配置
        $payment_model = new payment_method_model();
        $wxpay_params = $payment_model->get_pay_params('wxpay');
        WxPayConfig::setAppid($wxpay_params['appid']);
        WxPayConfig::setMchid($wxpay_params['mch_id']);
        WxPayConfig::setKey($wxpay_params['key']);
        WxPayConfig::setAppSecret($wxpay_params['secret']);
        WxPayConfig::setSslkeyPath(self::SSL_KEY_PATH);
        WxPayConfig::setSslcertPath(self::SSL_CERT_PATH);

        $input = new WxPayRefund();
        $input->SetOut_trade_no($order_id);
        $order_model = new order_model();
        $order = $order_model->find(['order_id' => $order_id], null,'order_amount');
        $input->SetTotal_fee(intval($order['order_amount'] * 100));
        $input->SetRefund_fee(intval($amount * 100));
        $input->SetOut_refund_no($refund_id);
        $input->SetOp_user_id($_SESSION['ADMIN']['USER_ID']);
        $res = WxPayApi::refund($input);
        if ($res['return_code'] == 'FAIL') {
            throw new \wxpay\WxPayException($res['return_msg']);
        }

        return true;
    }
}
