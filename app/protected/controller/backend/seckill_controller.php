<?php

use app\model\goods_model;
use app\model\seckill_model;

/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/5/2
 * Time: 下午3:31
 */


class seckill_controller extends general_controller
{
    public function action_list()
    {
        $seckill_model = new seckill_model();
        $list = $seckill_model->find_all(null, 'created_at desc');
        if (!empty($list)) {
            foreach ($list as $key => &$item) {
                $now = time();
                $start_at = strtotime($item['start_at']);
                $end_at = strtotime($item['end_at']);
                if ($start_at - $now > 0) {
                    $item['status'] = 'not_start';
                    $item['status_text'] = '未开始';
                } elseif ($start_at < $now && $now < $end_at) {
                    $item['status'] = 'going_on';
                    $item['status_text'] = '进行中';
                } else {
                    $item['status'] = 'end';
                    $item['status_text'] = '已结束';
                }
            }
        }
        echo json_encode(['list' => $list ? $list : []]);
    }

    /**
     * 添加秒杀活动
     */
    public function action_add()
    {
        if (request('step') == 'submit') {
            $seckill_model = new seckill_model();
            $goods_model = new goods_model();
            $post_data = $_POST;
            $verifier = $seckill_model->verifier($post_data);
            if ($verifier === true) {
                if (!$goods_model->find(['goods_id' => $post_data['goods_id']])) {
                    $this->prompt('error', '商品不存在');
                }
                $post_data['is_buy_limit'] = (isset($post_data['is_buy_limit']) && $post_data['is_buy_limit'] == 'on') ? 1 : 0;
                $post_data['created_at'] = date('Y-m-d H:i:s', time());
                $post_data['updated_at'] = date('Y-m-d H:i:s', time());
                $seckill_model->create($post_data);
                $this->prompt('success', "新建成功", url($this->MOD.'/marketing_plugin', 'seckill'));
            } else {
                $this->prompt('error', $verifier);
            }
        } else {
            $this->compiler('seckill/add.html');
        }
    }

    public function action_view()
    {
        $id = request('id');
        $seckill_model = new seckill_model();
        $seckill = $seckill_model->find(['id' => $id]);
        $this->seckill = $seckill;
        $this->compiler('seckill/edit.html');
    }

    public function action_end()
    {
        $id = request('id');
        $seckill_model = new seckill_model();
        $seckill_model->update(['id' => $id], ['end_at' => date('Y-m-d H:i:s')]);
        $this->prompt('success', "结束成功", url($this->MOD.'/marketing_plugin', 'seckill'));
    }

    public function action_del()
    {
        $id = request('id');
        $seckill_model = new seckill_model();
        $seckill_model->delete(['id' => $id]);
        $this->prompt('success', "删除成功", url($this->MOD.'/marketing_plugin', 'seckillController'));
    }

    public function action_edit()
    {
        $id = request('id');
        $seckill_model = new seckill_model();
        $goods_model = new goods_model();
        $post_data = $_POST;
        $verifier = $seckill_model->verifier($post_data);
        if ($verifier === true) {
            if (!$goods_model->find(['goods_id' => $post_data['goods_id']])) {
                $this->prompt('error', '商品不存在');
            }
            $post_data['is_buy_limit'] = (isset($post_data['is_buy_limit']) && $post_data['is_buy_limit'] == 'on') ? 1 : 0;
            $post_data['updated_at'] = date('Y-m-d H:i:s', time());
            $seckill_model->update(['id' => $id], $post_data);
            $this->prompt('success', "编辑成功", url($this->MOD.'/marketing_plugin', 'seckill'));
        } else {
            $this->prompt('error', $verifier);
        }
    }

    /**
     * 生成推广图
     */
    public function action_generate_popularize_image()
    {
        $id = request('id');
        $seckill_model = new seckill_model();
        $seckill = $seckill_model->find(['id' => $id]);
        if (empty($seckill)) {
            $this->prompt('error', "秒杀活动不存在");
        }
        $goods_model = new goods_model();
        $goods = $goods_model->find(['goods_id' => $seckill['goods_id']], null,
            'goods_name,goods_image,goods_id,now_price,selled_amount,goods_brief');
        if (empty($goods)) {
            $this->prompt('error', "秒杀商品不存在");
        }
        $this->goods = $goods;
        $this->seckill = $seckill;
        $this->compiler('seckill/popularize_image.html');
    }
}