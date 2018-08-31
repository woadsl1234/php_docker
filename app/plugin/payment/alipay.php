<?php

namespace plugin\payment;

/**
 * Alipay Payment
 * @author Cigery
 */
class alipay extends abstract_payment
{
    protected $pcode = 'alipay';
    protected $ret_success = 'success';
    protected $ret_fail = 'fail';

    public function create_pay_url($args)
    {
        $baseurl = baseurl();
        $params = array
        (
            'partner' => $this->config['partner'],
            'payment_type' => '1',
            'notify_url' => $baseurl . '/api/pay/notify/alipay',
            'out_trade_no' => $args['order_id'],
            'subject' => "{$GLOBALS['cfg']['site_name']}订单-{$args['order_id']}",
            'total_fee' => $args['order_amount'],
            '_input_charset' => 'utf-8',
            'transport' => 'http',
        );

        if ($this->device == 'mobile') {
            $params['service'] = 'alipay.wap.create.direct.pay.by.user';
            $params['seller_id'] = $this->config['seller'];
            $params['return_url'] = $baseurl . '/m/pay/return/alipay.html';
            $params['show_url'] = url('mobile/order', 'view', array('id' => $args['order_id']));
        }
        $pay_url = 'https://mapi.alipay.com/gateway.do?' . $this->set_params($params);
        return url('mobile/pay', 'invoke_alipay', array('pay_url' => urlencode($pay_url)));
    }

    protected function check_result_code($args)
    {
        return $args['trade_status'] === 'TRADE_SUCCESS' || $args['trade_status'] === 'TRADE_FINISHED';
    }

    protected function check_sign($args)
    {
        if (empty($args) || empty($args['sign'])) {
            return false;
        }

        return $args['sign'] === $this->make_sign($args);
    }

    protected function save_args($args)
    {
        parent::save_args($args);
        $this->order_id = $args['out_trade_no'];
        $this->thirdparty_trade_id = $args['trade_no'];
    }

    protected function make_sign($args)
    {
        ksort($args);

        $args_str = '';
        foreach ($args as $k => $v) {
            if (in_array($k, array('m', 'c', 'a'))) continue;
            if ($k == 'sign' || $k == 'sign_type' || $k == 'pcode' || $v == '') continue;
            $args_str .= $k . '=' . $v . '&';
        }

        $args_str = substr($args_str, 0, strlen($args_str) - 1) . $this->config['key'];
        if (get_magic_quotes_gpc()) $args_str = stripslashes($args_str);

        return md5($args_str);
    }

    private function set_params($params)
    {
        ksort($params);
        $args = $sign = '';
        foreach ($params as $k => $v) {
            $args .= $k . '=' . urlencode($v) . '&';
            $sign .= $k . '=' . $v . '&';
        }
        $args = substr($args, 0, strlen($args) - 1);
        $sign = md5(substr($sign, 0, strlen($sign) - 1) . $this->config['key']);
        return $args . '&sign=' . $sign . '&sign_type=MD5';
    }

    /**
     * @return bool|string
     * @throws \Exception
     */
    protected function get_data_from_request()
    {
        return $_POST;
    }

    /**
     * @param $payee_account
     * @param $amount
     * @param $remark
     * @param $out_biz_no
     * @return bool
     * @throws \Exception
     */
    public function transfer_to_account($payee_account, $amount, $remark, $out_biz_no)
    {
        $payer_show_name = $GLOBALS['cfg']['site_name'];
        $aop = new \AopClient();
        $aop->gatewayUrl = $GLOBALS['cfg']['alipay']['gateway'];
        $aop->appId = $this->config['appid'];
        $aop->rsaPrivateKey = $GLOBALS['cfg']['alipay']['rsa_private_key'];
        $aop->alipayrsaPublicKey = $GLOBALS['cfg']['alipay']['rsa_public_key'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'utf-8';
        $aop->format = 'json';
        $request = new \AlipayFundTransToaccountTransferRequest();
        $request->setBizContent("{" .
            "\"out_biz_no\":\"$out_biz_no\"," .
            "\"payee_type\":\"ALIPAY_LOGONID\"," .
            "\"payee_account\":\"$payee_account\"," .
            "\"amount\":\"$amount\"," .
            "\"payer_show_name\":\"$payer_show_name\"," .
            "\"remark\":\"$remark\"" .
            "}");
        $result = $aop->execute($request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if (!empty($resultCode) && $resultCode == 10000) {
            //提现成功以后 更新表状态
            //并且记录 流水等等
            return true;
        } else {
            //$result->$responseNode->sub_msg 这个参数 是返回的错误信息
            throw new \Exception($result->$responseNode->sub_msg);
        }
    }

    /**
     * @param $order_id
     * @param $refund_id
     * @param $amount
     * @param $reason
     * @return bool
     * @throws \Exception
     */
    public function refund($order_id, $refund_id, $amount, $reason)
    {
        $aop = new \AopClient ();
        $aop->gatewayUrl = $GLOBALS['cfg']['alipay']['gateway'];
        $aop->appId = $this->config['appid'];
        $aop->rsaPrivateKey = $GLOBALS['cfg']['alipay']['rsa_private_key'];
        $aop->alipayrsaPublicKey = $GLOBALS['cfg']['alipay']['rsa_public_key'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='utf-8';
        $aop->format='json';
        $request = new \AlipayTradeRefundRequest ();
        $request->setBizContent("{" .
            "\"out_trade_no\":$order_id," .
            "\"refund_amount\":$amount," .
            "\"refund_reason\":$reason," .
            "\"out_request_no\":$refund_id" .
            "}");
        $result = $aop->execute($request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if(!empty($resultCode)&&$resultCode == 10000){
            return true;
        } else {
            throw new \Exception($result->$responseNode->sub_msg);
        }
    }
}