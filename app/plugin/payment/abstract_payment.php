<?php

namespace plugin\payment;

use app\exception\order_not_found_exception;
use app\model\order_model;
use app\model\payment_method_model;
use app\model\user_model;
use plugin\payment\strategy\PaySuccess;
use app\dto\order_dto;
use plugin\push\kafka;
use plugin\push\phone;

abstract class abstract_payment
{
    protected $config = array();

    /**
     * @var PaySuccess
     */
    public $device;
    public $order;

    /**
     * @var PaySuccess
     */
    protected $strategy;
    protected $pcode;
    protected $ret_success;
    protected $ret_fail;
    protected $order_id;
    protected $thirdparty_trade_id;
    protected $args;
    protected $pay_success_message = '支付成功，您可在订单详情中查看订单状态';

    public function __construct($payment_params)
    {
        $this->config = json_decode($payment_params, true);
    }

    protected abstract function create_pay_url($args);

    /**
     * 读取请求数据
     * @return array
     * @throws \Exception
     */
    protected function get_data_from_request(){ return []; }

    /**
     * 校验并保存入参
     * @throws \Exception
     */
    protected function check_args($args)
    {
        if (false === $this->check_result_code($args)) {
            throw new \Exception('支付状态异常');
        }
        if (false === $this->check_sign($args)) {
            throw new \Exception('签名校验失败');
        }
    }

    /**
     * 校验支付返回码
     * @param $args
     * @return boolean
     */
    protected function check_result_code($args) {return false;}

    /**
     * 校验签名
     * @return boolean
     */
    protected function check_sign($args) {return false;}

    protected function save_args($args)
    {
        $this->args = $args;
    }

    /**
     * 支付回调
     */
    public function notify()
    {
        try {
            $args = $this->get_data_from_request();
            $this->check_args($args);
            $this->save_args($args);
            $order_model = new order_model();
            $order = $order_model->get($this->order_id);
            $order->thirdparty_trade_id = $this->thirdparty_trade_id;
            $this->execute_pay_success_strategy($order, $this->pcode);
            return $this->ret_success;
        } catch (\Exception $e) {
            echo $e->getMessage();
            return $this->ret_fail;
        }
    }

    /**
     * @param $args
     * @return string
     */
    public function response($args)
    {
        try {
            $order_model = new order_model();
            $order_dto = $order_model->get($args['order_id']);
            $order = $order_dto->toObject();

            if ($order->order_status == $order_model::ORDER_STATUS_CANCEL) {
                throw new \Exception('该订单已取消，请重新下单');
            }

            if ($order->order_status == $order_model::ORDER_STATUS_WAIT_PAY) {
                try {
                    $this->order = $order;
                    $this->custom_pay();
                    $this->execute_pay_success_strategy($order_dto, $this->pcode);
                } catch (\Exception $e) {
                    throw $e;
                }
            }

            $this->message = $this->pay_success_message;
            return 'success';
        } catch (\Exception $e) {
            $this->message = $e->getMessage();
            return 'error';
        }
    }

    /**
     * @throws \Exception
     */
    protected function custom_pay(){
        throw new \Exception('该订单未支付');
    }

    /**
     * 设置策略
     * @param PaySuccess $paySuccess
     */
    private function set_strategy(PaySuccess $paySuccess)
    {
        $this->strategy = $paySuccess;
    }

    /**
     * 获取策略名称
     * @param int $order_type
     * @return string
     */
    private function get_strategy_name($order_type)
    {
        $order_model = new order_model();
        $strategy_name = 'plugin\\payment\\strategy\\' . ucfirst($order_model->type_map[$order_type]) . 'PaySuccess';
        return $strategy_name;
    }

    /**
     * 执行支付成功策略
     * @param order_dto $order
     * @param $pcode
     * @throws \Dto\Exceptions\InvalidDataTypeException
     */
    protected function execute_pay_success_strategy(order_dto $order, $pcode)
    {
        $order_model = new order_model();
        $order_current_status = $order->toObject()->order_status;
        $order_status_wait_pay = $order_model::ORDER_STATUS_WAIT_PAY;
        if ($order_current_status == $order_status_wait_pay) {
            $strategyName = $this->get_strategy_name($order->toObject()->order_type);
            $this->set_strategy(new $strategyName($order));
            $this->strategy->setPcode($pcode);
            $this->strategy->execute();
        }
    }
}