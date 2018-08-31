<?php

use app\model\book_order_model;
use app\model\goods_model;

class book_order_controller extends general_controller
{
    public function action_list()
    {
        if (request('step') == 'api') {
            $book_order_model = new book_order_model();
            $book_orders = $book_order_model->find_all([], 'id desc');
            echo json_encode($book_orders);
        } else {
            $this->compiler('book_order/list.html');
        }
    }

    public function action_add()
    {
        if (request('step') == 'submit') {
            $data = [
                'grade' => request('grade'),
                'college' => trim(request('college')),
                'major' => trim(request('major')),
                'goods_ids' => trim(request('goods_ids'))
            ];
            $book_order_model = new book_order_model();
            $verifier = $book_order_model->verifier($data);
            if(TRUE === $verifier) {
                $find_data = $data;
                unset($find_data['goods_ids']);
                if ($book_order_model->find($find_data)) {
                    echo json_encode(['status' => 'error', 'msg' => '已存在']);
                } else {
                    $book_order_model->create($data);
                    echo json_encode(['status' => 'success', 'msg' => '保存成功']);
                }
            } else {
                echo json_encode(['status' => 'error', 'msg' => $verifier]);
            }
        } else {
            $this->compiler('book_order/add.html');
        }
    }

    public function action_edit()
    {
        if (request('step') == 'submit') {
            $data = [
                'grade' => request('grade'),
                'college' => trim(request('college')),
                'major' => trim(request('major')),
                'goods_ids' => trim(request('goods_ids'))
            ];

            $book_order_model = new book_order_model();
            $verifier = $book_order_model->verifier($data);
            if(TRUE === $verifier) {
                $book_order_model->update(['id' => request('id')], $data);
                echo json_encode(['status' => 'success', 'msg' => '保存成功']);
            } else {
                echo json_encode(['status' => 'error', 'msg' => $verifier]);
            }
        } else {
            $this->id = request('id');
            $this->compiler('book_order/edit.html');
        }
    }

    public function action_detail()
    {
        $id = request('id');
        $book_order_model = new book_order_model();
        $book_order = $book_order_model->find(['id' => $id]);
        $goods_model = new goods_model();
        $goods_list = $goods_model->find_all_by_ids(explode(',', $book_order['goods_ids']));
        unset($book_order['goods_ids']);
        $book_order['goods_list'] = $goods_list;
        echo json_encode($book_order);
    }

    public function action_del()
    {
        $id = request('id');
        $book_order_model = new book_order_model();
        $book_order_model->delete(['id' => $id]);
        echo json_encode(['status' => '删除成功']);
    }
}