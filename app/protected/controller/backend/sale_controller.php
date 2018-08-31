<?php

use app\model\goods_instead_sell_model;
use app\model\goods_model;
use app\model\instead_sell_apply_model;
use app\model\oauth_model;
use app\model\user_oauth_model;
use app\model\recycle_user_model;
use plugin\push\kafka;

/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/3/9
 * Time: 下午7:46
 */
class sale_controller extends general_controller
{
    public function action_user_list()
    {
        $user_id = request('id');
        $goods_instead_sell = new goods_instead_sell_model();
        $this->list = $goods_instead_sell->user_goods_list($user_id);
        $this->status_map = $goods_instead_sell::status_map;
        $this->compiler('sale/user_list.html');
    }

    public function action_apply_list()
    {
        if (!request('api')) {
            $this->compiler('sale/apply_list.html');
        } else {
            $status = request('status', '');
            $instead_sell_apply_model = new instead_sell_apply_model();
            $list = $instead_sell_apply_model->apply_list($status);
            echo json_encode(['list' => $list]);
        }
    }

    public function action_goods_list()
    {
        $id = request('id');
        $instead_sell_apply_model = new instead_sell_apply_model();
        $res = $instead_sell_apply_model->find(['id' => $id], null, 'goods_list');
        $short_goods_list = json_decode($res['goods_list'], true);
        $goods_ids = array_column($short_goods_list, 'goods_id');
        $goods_model = new goods_model();
        $complete_goods_list = $goods_model->find_all_by_ids($goods_ids);
        $short_column_goods_id_goods_list = array_column($short_goods_list, null, 'goods_id');
        foreach ($complete_goods_list as &$complete_goods) {
            $complete_goods['qty'] = $short_column_goods_id_goods_list[$complete_goods['goods_id']]['qty'];
        }
        echo json_encode(['status' => 'success', 'list' => $complete_goods_list]);
    }

    /**
     * 通过
     */
    public function action_pass()
    {
        $id = request('id');
        $recycle_user_id = request('recycle_user_id');

        $recycle_user_model = new recycle_user_model();
        $recycle_user = $recycle_user_model->find(['id' => $recycle_user_id]);

        $instead_sell_apply_model = new instead_sell_apply_model();
        $res = $instead_sell_apply_model->update(['id' => $id],[
            'status' => $instead_sell_apply_model::status_pass,
            'recycle_user_id' => $recycle_user_id
        ]);
        $apply = $instead_sell_apply_model->find(['id' => $id]);
        $this->send_template($id, $apply['user_id'], $apply['form_id'], $apply['time'], $recycle_user['name'], $recycle_user['phone']);
        echo json_encode(['status' => '受理成功']);
    }

    /**
     * 拒绝
     */
    public function action_reject()
    {
        $id = request('id');
        $instead_sell_apply_model = new instead_sell_apply_model();
        $res = $instead_sell_apply_model->update(['id' => $id],[
            'status' => $instead_sell_apply_model::status_reject
        ]);
        echo json_encode(['status' => '拒绝成功']);
    }

    /**
     * 撤销受理
     */
    public function action_cancel()
    {
        $id = request('id');
        $instead_sell_apply_model = new instead_sell_apply_model();
        $res = $instead_sell_apply_model->update(['id' => $id],[
            'status' => $instead_sell_apply_model::status_review,
            'recycle_user_id' => 0
        ]);
        echo json_encode(['status' => '撤销成功']);
    }

    /**
     * 上架
     */
    public function action_putway()
    {
        $id = request('id');
        $instead_sell_apply_model = new instead_sell_apply_model();
        $res = $instead_sell_apply_model->find(['id' => $id]);
        if ($res['status'] == $instead_sell_apply_model::status_putaway) {
            echo json_encode(['status' => '已上架']);
            return;
        }
        $short_goods_list = json_decode($res['goods_list'], true);
        $goods_instead_sell_model = new goods_instead_sell_model();
        $instead_sell_apply_model->start_transaction();
        try {
            //以旧换新
            if ($res['way'] == 1) {
                $goods_model = new goods_model();
                $ids = array_column($short_goods_list, 'goods_id');
                $goods_list = $goods_model->find_all_by_ids($ids);
                $goods_qty_list = array_column($short_goods_list, 'qty', 'goods_id');
                $total_fee = 0;
                foreach ($goods_list as $key => $goods) {
                    $goods_qty = $goods_qty_list[$goods['goods_id']];
                    $total_fee += $goods['original_price'] * 0.1 * $goods_qty;
                    $goods_model->incr(['goods_id' => $goods['goods_id']], 'stock_qty', $goods_qty);
                }
                $user_account_model = new \app\model\user_account_model();
                $user_account_model->recharge($res['user_id'], $total_fee, '卖书');
            } else {
                //书籍代售
                $goods_instead_sell_model->putway($id, $short_goods_list);
            }
            $instead_sell_apply_model->update(
                ['id' => $id],
                ['status' => $instead_sell_apply_model::status_putaway]
            );
            $instead_sell_apply_model->commit();
            echo json_encode(['status' => '上架成功']);
        } catch (\Exception $e) {
            echo json_encode(['status' => '上架失败']);
            $instead_sell_apply_model->roll_back();
        }
    }

    // 接单用户列表
    public function action_accept_users()
    {
        $recycle_user_model = new recycle_user_model();
        $res = $recycle_user_model->find_all();
        echo json_encode($res);
    }

    private function send_template($id, $user_id, $form_id, $apply_time, $accept_name, $phone)
    {
        if (!$form_id) {
            return;
        }

        $user_oauth_model = new user_oauth_model();
        $user_oauth = $user_oauth_model->find(['user_id' => $user_id, 'party' => 'wx_applet']);
        $template_id = "d1v_Jqet1ymD5cogkArPRvu4IzYTGLgaGF28LSA5RJU";//受理结果通知模板
        $page = 'pages/applySaleDetail?id=' . $id;
        $send_data = array(
            'keyword1' => array('value' => $accept_name),
            'keyword2' => array('value' => '上门收书'),
            'keyword3' => array('value' => $apply_time),
            'keyword4' => array('value' => $phone),
            'keyword5' => array('value' => '回收员将在48小时内与您联系，请做好准备并保持手机通话顺畅'),
        );

        $oauth_model = new oauth_model();
        $config = $oauth_model->get_config("wx_applet");
        $wx_applet = new plugin\push\wx_applet($config['appid'], $config['secret']);
        kafka::produce(
            kafka::RECYCLE_ACCEPT,
            [
                'wxApplet' => [
                    'tousers' => [$user_oauth['oauth_key']],
                    'template_id' => $template_id,
                    'page' => $page,
                    'form_id' => $form_id,
                    'data' => $send_data,
                    'emphasis_keyword' => 'keyword1.DATA',
                    'access_token' => $wx_applet->get_access_token()
                ]
            ]
        );
    }
}