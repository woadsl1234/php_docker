<?php
namespace plugin\payment;

use app\exception\order_not_found_exception;
use app\model\order_model;
use app\model\payment_method_model;
use app\model\user_account_log_model;
use app\model\user_account_model;
use app\model\user_model;
use plugin\push\phone;

/**
 * Balance Payment
 * @author Cigery
 */
class balance extends abstract_payment
{
    protected $pcode = 'balance';

    public function create_pay_url($args)
    {
        return url('mobile/pay', 'return', array('pcode' => 'balance', 'order_id' => $args['order_id']));
    }

    /**
     * @throws \Exception
     */
    public function custom_pay()
    {
        $order = $this->order;
        $user_id = $order->user_id;
        $account_model = new user_account_model();
        $account = $account_model->get_user_account($user_id);
        if ($account['balance'] < $order->order_amount) {
            throw new \Exception('余额不足');
        }
        $account_model->update_account($user_id, ['balance' => 0 - $order->order_amount],
            "余额支付订单[{$order->order_id}]");
    }

    public function refund($order_id, $refund_id, $amount, $reason)
    {
        $account_model = new user_account_model();
        $order_model = new order_model();
        $order = $order_model->find(['order_id' => $order_id], null,'user_id');
        $account_model->update_account($order['user_id'], ['balance' => $amount], "{$reason}，交易退款[{$order_id}]");
        return true;
    }
}