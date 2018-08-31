<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/10
 * Time: 下午9:04
 */

use PHPUnit\Framework\TestCase;
use plugin\payment\wxpay;

class WxpayTest extends TestCase
{
    public function testNotify()
    {
        $wxPay = new wxpay(['']);
        $this->assertEquals($wxPay->ret_success, $wxPay->notify());
    }
}