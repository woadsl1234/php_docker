<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/10
 * Time: 上午12:03
 */

namespace app\exception;

class order_not_found_exception extends \Exception
{
    public function errorMessage()
    {
        return '订单不存在';
    }
}