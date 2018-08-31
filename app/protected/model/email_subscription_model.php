<?php

namespace app\model;

class email_subscription_model extends model
{
    public $table_name = 'email_subscription';
    
    public $status_map = array
    (
        0 => '未确认',
        1 => '已确认',
        2 => '已退订',
    );
}
