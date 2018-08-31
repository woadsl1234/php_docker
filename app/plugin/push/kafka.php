<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/8/23
 * Time: 上午12:51
 */

namespace plugin\push;

use Kafka\Producer;
use Kafka\ProducerConfig;

class kafka
{
    /**
     * @var Producer
     */
    static private $producer = null;

    const USER_ACCOUNT_CHANGED = 'user_account_changed'; // 余额变动topic

    const VERIFY_CODE = 'verify_code'; //短信验证码

    const PAY_SUCCESS = 'pay_success'; // 支付成功

    const ORDER_SEND_OUT = 'order_send_out'; // 订单发货

    const REFUND_PASS = 'refund_pass'; // 退款申请通过

    const PRINT_ORDER = 'print_order'; // 打印订单

    const RECYCLE_ACCEPT = 'recycle_accept'; // 回收受理

    private static function init()
    {
        $config = ProducerConfig::getInstance();
        $config->setMetadataRefreshIntervalMs(10000);
        $config->setMetadataBrokerList($GLOBALS['cfg']['kafka']['broker_list']);
        $config->setBrokerVersion('0.10.2.1');
        $config->setRequiredAck(1);
        $config->setIsAsyn(false);
        $config->setProduceInterval(500);

        self::$producer = new Producer();
    }

    public static function produce($topic, $value)
    {
        if (!self::$producer) {
            self::init();
        }
        self::$producer->send([
            [
                'topic' => $topic,
                'value' => json_encode($value),
                'key' => time().rand(10000, 99999),
            ],
        ]);
    }
}

