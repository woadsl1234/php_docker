<?php

/**
 * Created by PhpStorm.
 * User: chenshengkun
 * Date: 2017/9/14
 * Time: 上午1:20
 */

namespace app\model;

class book_order_model extends model
{
    public $table_name = 'book_order';

    public $rules = array
    (
        'grade' => array
        (
            'is_required' => array(TRUE, '年级不能为空')
        ),
        'college' => array
        (
            'is_required' => array(TRUE, '学院不能为空')
        ),
        'major' => array
        (
            'is_required' => array(TRUE, '专业不能为空')
        ),
        'goods_ids' => array
        (
            'is_required' => array(TRUE, '商品不能为空')
        )
    );
}