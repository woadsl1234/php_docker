<?php

use app\model\card_model;
use app\model\receipt_model;
use plugin\push\printer;

/**
 * Created by PhpStorm.
 * User: chenshengkun
 * Date: 2017/10/5
 * Time: 上午12:13
 */
class card_controller extends general_controller
{
    public function action_list()
    {
        $card_model = new card_model();

        echo json_encode($card_model->lists());
    }

    public function action_index()
    {
        $this->compiler("user/card.html");
    }


    public function action_add()
    {
        if(request('step') == 'submit')
        {
            $card_model = new card_model();
            $codes = $card_model->generate_promotion_code(1,'',6);
            $data = array
            (
                'amount' => trim(request('amount', '')),
                'code' => $codes[0]
            );

            $verifier = $card_model->verifier($data);
            if(TRUE === $verifier)
            {
                if($user_id = $card_model->create($data))
                {
                    $this->prompt('success', '添加充值卡成功', url($this->MOD.'/card', 'index'));
                }
            }
            else
            {
                $this->prompt('error', $verifier);
            }
        }
        else
        {
            $this->compiler('user/add_card.html');
        }
    }

    public function action_delete()
    {
        $id = request('id');
        $card_model = new card_model();
        if($card_model->delete(array('id' => $id)) > 0) $this->prompt('success', '删除成功');
        $this->prompt('error', '删除失败');
    }

    public function action_print()
    {
        $id = request('id');
        $card_model = new card_model();
        $card = $card_model->find(array('id' => $id));
        $printer = new printer();
        $receipt_model = new receipt_model();
        $res =  $printer->print_content($receipt_model->balance_card($card));
        die($res);
    }
}