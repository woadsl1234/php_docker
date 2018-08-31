<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/10
 * Time: 上午1:08
 */

namespace plugin\payment\strategy;

use app\model\user_account_model;
use app\dto\order_dto;
use app\model\user_account_log_model;

class RechargePaySuccess extends PaySuccess
{
    public function __construct(order_dto $order)
    {
        $order->order_status = 4;
        $this->setOrder($order);
    }

    /**
     * @throws \Dto\Exceptions\InvalidDataTypeException
     */
    public function execute()
    {
        $this->save_payment_info_into_order();
        $order = $this->getOrder()->toObject();
        $user_id = $order->user_id;
        $order_id = $order->order_id;
        $order_amount = $order->order_amount;
        $user_account_model = new user_account_model();
        $total_fee = $user_account_model->get_recharge_in_activity($order_amount);

        $account = [
            'balance' => $total_fee
        ];
        $user_account_model->update_account($user_id, $account, "余额充值" . $order_id);
    }
}