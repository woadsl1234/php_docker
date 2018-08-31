<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/10
 * Time: 上午1:03
 */
namespace plugin\payment\strategy;

use app\dto\order_dto;
use app\model\payment_method_model;
use app\model\order_model;

abstract class PaySuccess
{
    /**
     * @var order_dto
     */
    private $order;

    protected $pcode;

    public abstract function execute();

    protected function setOrder(order_dto $order)
    {
        $this->order = $order;
    }

    protected function getOrder()
    {
        return $this->order;
    }

    public function setPcode($pcode)
    {
        $this->pcode = $pcode;
    }

    /**
     * 保存支付数据
     * @throws \Dto\Exceptions\InvalidDataTypeException
     */
    protected function save_payment_info_into_order()
    {
        $order = $this->order->toObject();
        $payment_method_model = new payment_method_model();
        $payment = $payment_method_model->find(array('pcode' => $this->pcode));
        $order_model = new order_model();
        $data = array
        (
            'payment_method' => $payment['id'],
            'order_status' => $order->order_status,
            'thirdparty_trade_id' => $order->thirdparty_trade_id,
            'payment_date' => $_SERVER['REQUEST_TIME'],
        );

        $order_model->update(array('order_id' => $order->order_id), $data);
    }
}