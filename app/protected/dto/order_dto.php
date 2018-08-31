<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/10
 * Time: 上午12:17
 */

namespace app\dto;

class order_dto extends \Dto\Dto
{
    protected $schema = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'order_id' => ['type' => 'string'],
            'order_type' => ['type' => 'integer'],
            'user_id' => ['type' => 'integer'],
            'shipping_method' => ['type' => 'integer'],
            'payment_method' => ['type' => 'integer'],
            'order_status' => ['type' => 'integer'],
            'goods_amount' => ['type' => 'number'],
            'shipping_amount' => ['type' => 'number'],
            'order_amount' => ['type' => 'number'],
            'refund_amount' => ['type' => 'number'],
            'memos' => ['type' => 'string'],
            'payment_date' => ['type' => 'string'],
            'created_date' => ['type' => 'string'],
            'finish_date' => ['type' => 'string'],
            'thirdparty_trade_id' => ['type' => 'string'],
            'prepay_id' => ['type' => 'string']
        ],
        'additionalProperties' => true
    ];
}