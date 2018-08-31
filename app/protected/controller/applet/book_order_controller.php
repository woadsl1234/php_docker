<?php

use app\model\book_order_model;
use app\model\goods_model;

class book_order_controller extends general_controller
{
    public function action_grades()
    {
        $book_order_model = new book_order_model();
        $res = $book_order_model->query('select DISTINCT grade from verydows_book_order');
        echo json_encode(array_column($res, 'grade'), JSON_UNESCAPED_UNICODE);
    }

    public function action_colleges()
    {
        $book_order_model = new book_order_model();
        $res = $book_order_model->query('select DISTINCT college from verydows_book_order where grade
            = :grade', [':grade' => request('grade')]);
        echo json_encode(array_column($res, 'college'), JSON_UNESCAPED_UNICODE);
    }

    public function action_majors()
    {
        $book_order_model = new book_order_model();
        $res = $book_order_model->query('select DISTINCT major from verydows_book_order where grade
            = :grade and college = :college', [':grade' => request('grade'), ':college' => request('college')]);
        echo json_encode(array_column($res, 'major'), JSON_UNESCAPED_UNICODE);
    }

    public function action_query_goods_list()
    {
        $grade = request('grade');
        $college = request('college');
        $major = request('major');

        $book_order_model = new book_order_model();
        $book_order = $book_order_model->find(['grade' => $grade, 'college' => $college, 'major' => $major]);
        if (empty($book_order)) {
            $this->r(false, '查无此专业书单');
            return;
        }
        $goods_model = new goods_model();
        $goods_list = $goods_model->find_all_by_ids(explode(',', $book_order['goods_ids']));
        $res = [];
        if (!empty($goods_list)) {
            foreach ($goods_list as $key => $value) {
            if (($value['stock_qty'] + $value['instead_sell_stock_qty']) > 0) {
                    $res[] = $value;
                }
            }
        }

        if (empty($res)) {
            $this->r(false, '查无此专业书单');
        } else {
            $this->r(true, 'ok', ['goods_list' => $res]);
        }
    }
}