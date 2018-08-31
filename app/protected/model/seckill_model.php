<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/5/2
 * Time: 下午5:09
 */

namespace app\model;


class seckill_model extends model
{
    public $table_name = 'seckill';

    public $rules = [
        'goods_id' => [
            'min' => array(0, '商品id必须为正'),
        ],
        'name' => [
            'is_required' => [true, '名称不能为空'],
            'max_length' => [10, '名称不能超过10个字符'],
        ],
        'start_at' => [
            'is_time' => [TRUE, '起始日期不是一个有效时间格式'],
        ],
        'end_at' => [
            'is_time' => [TRUE, '结束日期不是一个有效时间格式'],
        ],
        'order_cancel_minute' => [
            'min' => [3, '订单取消时间，不得小于1分钟'],
            'max' => [30, '订单取消时间，不得大于30分钟']
        ],
        'seckill_stock_qty' => [
            'min' => [1, '秒杀库存，必须大于0']
        ],
        'seckill_price' => [
            'min' => [0, '秒杀价格，不得小于0']
        ]
    ];

    public function get($id)
    {
        $vache = \vcache::instance();
        $seckill = $vache->get('seckill');
        //不存在，或者更新时间超过一秒
        if (!$seckill || (time() - $seckill['last_push_time'] >= 1)) {
            $seckill = $this->find(['id' => $id]);
            if (empty($seckill)) {
                return null;
            }
            $goods_model = new goods_model();
            $seckill['goods'] = $goods_model->find(['goods_id' => $seckill['goods_id']], null, 'goods_id,
             goods_name,now_price,goods_image,goods_brief,goods_content,selled_amount');
            $seckill['last_push_time'] = time();
            $vache->set('seckill', $seckill);
        }
        unset($seckill['last_push_time']);
        return $seckill;
    }

}