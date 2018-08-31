<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/11
 * Time: 下午9:18
 */

use app\model\full_cut_model;
use app\model\goods_cate_model;
use app\model\goods_model;

class main_controller extends general_controller
{
    public function action_notices()
    {
        $notices = array();
        try {
            $full_cut_notice = vcache::instance()->full_cut_model('get_notice');
            $full_cut_notice && $notices[] = $full_cut_notice;
        } catch (\Dto\Exceptions\InvalidDataTypeException $e) {

        }
        $this->r(true, 'ok', $notices);
    }

    /**
     * 子分类的商品
     */
    public function action_child_goods()
    {
        $parent_id = request('parent_id');
        $goods_cate_model = new goods_cate_model();
        $goods_model = new goods_model();
        $cates = $goods_cate_model->find_all(['parent_id' => $parent_id], 'seq asc', 'cate_id,cate_name');
        $res = [];
        if (!empty($cates)) {
            foreach ($cates as $cate) {
                $condition = [
                    'cate' => $cate['cate_id'],
                    'limit' => 6
                ];
                $goods_list = arraySequence($goods_model->find_goods($condition), 'created_date', 'SORT_DESC');
                $res[] = [
                    'cate' => $cate,
                    'goods_list' => $goods_list
                ];
            }
        }

        $this->r(true,'ok',$res);
    }
}