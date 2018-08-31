<?php

use app\model\full_cut_model;

/**
 * Created by PhpStorm.
 * User: chenshengkun
 * Date: 2017/11/8
 * Time: 下午6:43
 */

class marketing_plugin_Controller extends general_controller
{
    public function action_full_cut()
    {
        $full_cut_model = new full_cut_model();
        if (request('step') == "submit") {
            $data = array(
                'title' => $_POST['title'],
                'start_time' => $_POST['start_time'],
                'end_time' => $_POST['end_time'],
                'updated_at' => date("Y-m-d H:i:s")
            );
            $verifier = $full_cut_model->verifier($data);

            if (true === $verifier) {
                if ($data['start_time'] >= $data['end_time']) {
                    $this->prompt("error", "结束时间需大于开始时间");
                    return;
                }
                if (count($_POST['list']) < 1) {
                    $this->prompt("error", "至少设置一个优惠级别");
                    return;
                }
                foreach ($_POST['list'] as $v) {
                    if ($v['discount_fee'] >  $v['order_fee']) {
                        $this->prompt("error", "优惠金额不得大于订单金额");
                        return;
                    }
                }
                $data['list'] = json_encode(arraySequence($_POST['list'], 'order_fee'));
                $full_cut_model->save($data);
                $this->prompt('success', "保存成功");
            } else {
                $this->prompt("error", $verifier);
            }
        } else {
            $this->full_cut = $full_cut_model->get();
            $this->compiler("marketing_plugin/full_cut.html");
        }
    }

    public function action_seckill()
    {
        $this->compiler('seckill/list.html');
    }

}