<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/3/8
 * Time: 下午4:21
 */

use app\model\goods_instead_sell_model;
use app\model\goods_model;
use app\model\instead_sell_apply_model;
use app\model\user_consignee_model;
use app\model\recycle_user_model;

class sale_controller extends general_controller
{
    public function action_get_book()
    {
        $isbn = trim(request('isbn'));
        $goods_model = new goods_model();
        $goods = $goods_model->find(['goods_sn' => $isbn], null,
            'goods_id,now_price,goods_name,original_price,goods_image,status,bargain');
        if (empty($goods) || !$goods['status'] || $goods['bargain']) {
            //不存在，先录入进商品库
            if (empty($goods)) {
                $goods_model->create([
                    'goods_name' => "回收扫描",
                    'goods_sn' => $isbn,
                    'status' => 0,
                    'newarrival' => 1,
                    'created_date' => time()
                ]);
            }
            $this->r(false, '抱歉，二货暂时不收这本书');
        }

        $this->r(true, 'ok', $goods);
    }

    public function action_submit()
    {
        $user_id = $this->is_logined();
        $account = request('account');
        $csn_id = request('csn_id');
        $form_id = request('form_id');
        $list = json_decode(request('list'), true);
        $way = request('way');

        $this->check_min_sell($list);
        $this->check_csn($csn_id, $user_id);

        $instead_sell_apply_model = new instead_sell_apply_model();
        $instead_sell_apply_model->start_transaction();
        //商品列表
        $goods_list = [];
        foreach ($list as $goods) {
            $goods_list[] = [
                'goods_id' => $goods['goods_id'],
                'qty' => $goods['qty']
            ];
        }
        $data = [
            'user_id' => $user_id,
            'account_type' => $instead_sell_apply_model::account_type_alipay,
            'account' => $account,
            'csn_id' => $csn_id,
            'goods_list' => json_encode($goods_list),
            'form_id' => $form_id,
            'way' => $way
        ];

        if (request('id', 0)) {
            $goods_instead_sell_apply = $instead_sell_apply_model->find(
                ['id' => request('id'), 'user_id' => $user_id]
            );
            //未上架前均可修改
            if ($goods_instead_sell_apply['status'] < $instead_sell_apply_model::status_putaway) {
                if ($goods_instead_sell_apply['status'] != $instead_sell_apply_model::status_pass) {
                    $data['status'] = $instead_sell_apply_model::status_review;
                }
                $data['time'] = date('Y-m-d H:i:s', time());
                $instead_sell_apply_model->update(['id' => $goods_instead_sell_apply['id']], $data);
                $res = "修改成功";
            } else {
                $this->r(false, '不可修改');
            }
        } else {
            $res = "提交成功，请将书搬到五餐三楼的5号办公室回收";
            $instead_sell_apply_model->create($data);
        }
        $instead_sell_apply_model->commit();
        $this->r(true, $res);
    }

    private function check_min_sell($list)
    {
        $min_sell = 15;
        $count = 0;
        foreach ($list as $goods) {
            $count += $goods['qty'];
        }
        if ($count < $min_sell) {
            $this->r(false, $min_sell . '本起收');
        }
    }

    private function check_csn($csn_id, $user_id)
    {
        $consignee_model = new user_consignee_model();
        $consignee = $consignee_model->find([
            'id' => $csn_id,
                'user_id' => $user_id
            ]);
        if (!$consignee) {
            $this->r(false, '无法获取收件人地址信息');
        }
    }

    public function action_list()
    {
        $user_id = $this->is_logined();
        $instead_sell_apply_model = new instead_sell_apply_model();
        $instead_sell_applies = $instead_sell_apply_model->find_all(
            ['user_id' => $user_id], 'time desc'
        );
        if (!empty($instead_sell_applies)) {
            foreach ($instead_sell_applies as &$apply) {
                $apply['status_text'] = $instead_sell_apply_model::status_map[$apply['status']];
                $apply['way_text'] = $instead_sell_apply_model::way_map[$apply['way']];
                $goods_list = json_decode($apply['goods_list'], true);
                $total_goods_list = 0;
                foreach ($goods_list as $goods) {
                    $total_goods_list += $goods['qty'];
                }
                $apply['total_goods_list'] = $total_goods_list;
                $apply['time'] = time_tran($apply['time']);
            }
            $this->r(true, 'ok', $instead_sell_applies);
        } else {
            $this->r(false, '你还没卖过书哦');
        }
    }

    public function action_get()
    {
        $user_id = $this->is_logined();
        $instead_sell_apply_model = new instead_sell_apply_model();
        $instead_sell_apply = $instead_sell_apply_model->find(['id' => request('id'), 'user_id' => $user_id]);
        if (empty($instead_sell_apply)) {
            echo json_encode(['status' => 'nodata']);
            return;
        }
        //已受理
        if ($instead_sell_apply['status'] == $instead_sell_apply_model::status_pass
            && $instead_sell_apply['recycle_user_id']) {
            $recycle_user_model = new recycle_user_model();
            $recycle_user = $recycle_user_model->find(['id' => $instead_sell_apply['recycle_user_id']]);
            $instead_sell_apply['recycle_user'] = $recycle_user;
        }

        //未审核或被拒绝，直接取字段中的商品
        if ($instead_sell_apply['status'] <= $instead_sell_apply_model::status_pass) {
            $goods_list = json_decode($instead_sell_apply['goods_list'], true);
        } else {
            $goods_instead_sell_model = new goods_instead_sell_model();
            $goods_list = $goods_instead_sell_model->find_all(['instead_sell_id' => $instead_sell_apply['id']]);
        }

        $goods_ids = array_column($goods_list, 'goods_id');
        $goods_model = new goods_model();
        $complete_goods_list = $goods_model->find_all_by_ids($goods_ids);
        $short_column_goods_id_goods_list = array_column($goods_list, null, 'goods_id');
        $goods_list = [];
        foreach ($complete_goods_list as &$complete_goods) {
            $goods_list[] = [
                'goods_id' => $complete_goods['goods_id'],
                'goods_name' => $complete_goods['goods_name'],
                'goods_image' => $complete_goods['goods_image'],
                'qty' => $short_column_goods_id_goods_list[$complete_goods['goods_id']]['qty'],
                'now_price' => $complete_goods['now_price']
            ];
        }

        echo json_encode(['status' => 'success', 'apply' => $instead_sell_apply, 'list' => $goods_list]);
        return;
    }
}