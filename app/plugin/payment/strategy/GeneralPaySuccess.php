<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/10
 * Time: 上午1:08
 */

namespace plugin\payment\strategy;

use app\dto\order_dto;
use app\model\order_model;
use app\model\user_model;
use plugin\push\kafka;
use plugin\push\phone;

class GeneralPaySuccess extends PaySuccess
{
    public function __construct(order_dto $order)
    {
        $order->order_status = 2;
        $this->setOrder($order);
    }

    /**
     * @throws \Dto\Exceptions\InvalidDataTypeException
     */
    public function execute()
    {
        $this->save_payment_info_into_order();

        $order = $this->getOrder()->toObject();
        $user_model = new user_model();
        $user = $user_model->find(['user_id' => $order->user_id]);
        $order_model = new order_model();
        $order_id =$order ->order_id;
        $we_chat_mp = $order_model->get_new_order_template($order_id, $this->pcode);

        kafka::produce(kafka::PAY_SUCCESS, [
            'message' => [
                'phoneNumber' => $user['mobile'],
                'tempId' => phone::SCENE_PAY_SUCCESS,
                'params' => [$order->order_id],
            ],
            'weChatMp' => $we_chat_mp
        ]);
    }
}