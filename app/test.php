<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/8/25
 * Time: 上午10:24
 */

class P
{
    public function __construct()
    {
        $this->name();
    }

    public function name()
    {
        echo "p_name";
    }
}

class C1 extends P
{
    public function name()
    {
        echo "c1_name";
    }
}

$c = new C1();
