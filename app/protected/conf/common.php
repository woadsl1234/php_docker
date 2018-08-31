<?php
/**
 * Created by PhpStorm.
 * User: chenshengkun
 * Date: 2017/11/8
 * Time: 上午12:51
 */

return [
    'verydows' => [
        'VERSION' => '2.0',
        'RELEASE' => '20161112',
        'COMMENCED' => '1488038400',
    ],
    'order_type' => [
        1 => "商品购买",
        2 => "余额充值"
    ],
    'qi_niu' => [
        'access_key' => '2gktQG-lAlb8heQKTN6qFtfw-gpz55qAbQz39S4a',
        'secret_key' => 'SA-7BcL3a2flE4mpCP_ggul_wTZvwMiF2GKvn0sA',
        'bucket' => 'flea'
    ],
    'rewrite_enable' => '1',
    'rewrite_rule' => [
        'm/pay/return/<pcode>.html' => 'mobile/pay/return',
        'pay/return/<pcode>.html' => 'pay/return',
        'api/pay/notify/<pcode>' => 'api/pay/notify',
        'api/oauth/callback/<party>' => 'api/oauth/callback',
        'm/index.html' => 'mobile/main/index',
        'm/<c>/<a>.html' => 'mobile/<c>/<a>',
        'api/<c>/<a>' => 'api/<c>/<a>',
        'applet/<c>/<a>' => 'applet/<c>/<a>',
        '404.html' => 'main/404',
        'index.html' => 'main/index',
        '<c>/<a>.html' => '<c>/<a>',
    ],
    'alidayu' => [
        'app_key' => "23743105",
        'app_secret' => '7c64a2b619d42bc08fdfe85910bebca7'
    ]
];