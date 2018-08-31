<?php

use app\model\book_order_model;
use app\model\goods_album_model;
use app\model\goods_model;
use app\model\goods_optional_model;
use app\model\goods_review_model;

class goods_controller extends general_controller
{
    public function action_search()
    {
        $conditions = array
        (
            'cate' => (int)request('cate', 0),
            'brand' => (int)request('brand', 0),
            'att' => request('att', ''),
            'minpri' => (int)request('minpri', 0),
            'maxpri' => (int)request('maxpri', 0),
            'kw' => strip_tags(trim(request('kw', ''))),
            'sort' => (int)request('sort', 0),
            'page' => (int)request('page', 1),
        );

        $goods_model = new goods_model();
        $list = $goods_model->find_goods($conditions);
        $result = (!empty($list)) ? implode(",",array_column($list, 'goods_id', null)) : "";
        $log = [
            'user_id' => isset($_SESSION['USER']['USER_ID']) ? $_SESSION['USER']['USER_ID'] : 0,
            'result' => $result,
            'keyword' => $conditions['kw']
        ];
        $goods_model->record_search_log($log);

        if ($list) {
            echo json_encode(array('status' => 'success', 'list' => $list), JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(array('status' => 'nodata'));
        }
    }

    public function action_hot_search()
    {
        $hot_searches = array_column(vcache::instance()->goods_model("hot_searches",
            array(date("Y-m-d H:i:s", strtotime('-15 day')), date("Y-m-d H:i:s", strtotime('tomorrow')), 6)), "keyword", null);
        echo json_encode(['status' => 'success', 'data' => $hot_searches], JSON_UNESCAPED_UNICODE);
    }

    public function action_search_suggest()
    {
        $keyword = request('kw');
        if (!$keyword) {
            echo json_encode(array('status' => 'nodata'));
            return;
        }
        $xs = new \XS("goods");
        $search = $xs->search;
        $words = $search->getRelatedQuery($keyword, 10);
        if (empty($words)) {
            echo json_encode(array('status' => 'nodata'));
            return;
        }
        $list = [];
        foreach ($words as $word) {
            $search->addRange('status', 1, 1)->setQuery($word);
            $docs = $search->search();
            if (!empty($docs)) {
                $list[] = ['keyword' => $word, 'count' => $search->count()];
            }
        }
        if (empty($list)) {
            echo json_encode(array('status' => 'nodata'));
            return;
        }
        echo json_encode(array('status' => 'success', 'list' => $list), JSON_UNESCAPED_UNICODE);
    }

    public function action_detail()
    {
        $condition = array('goods_id' => (int)request('id', 0));
        $goods_model = new goods_model();
        if ($goods = $goods_model->find($condition)) {
            $goods['stock_qty'] += $goods['instead_sell_stock_qty'];
            //商品信息
            $data['goods'] = $goods;
            //商品相册
            //$album_model = new goods_album_model();
            //$data['album_list'] = $album_model->find_all($condition);
            //购买选择项
            $optl_model = new goods_optional_model();
            $data['opt_list'] = $optl_model->get_goods_optional($condition['goods_id']);
            //商品评价
            //$review_model = new goods_review_model();
            //$data['review_rating'] = $review_model->get_rating_stats($condition['goods_id']);
            //关联商品
            $data['related'] = $goods_model->get_related($condition['goods_id'], $GLOBALS['cfg']['goods_related_num']);
            //保存浏览历史
            //$goods_model->set_history($condition['goods_id']);

            echo json_encode(array('status' => 'success', 'data' => $data), JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(array('status' => 'nodata'));
        }
    }

    public function action_rating()
    {
        $goods_id = (int)request('goods_id', 0);
        $review_model = new goods_review_model();
        $res = $review_model->get_rating_stats($goods_id);
        echo json_encode($res);
    }

    public function action_reviews()
    {
        $goods_id = (int)request('goods_id', 0);
        $rating_type = (int)request('rating_type', 0);
        $review_model = new goods_review_model();
        if ($list = $review_model->get_goods_reviews($goods_id, $rating_type, array(request('page', 1), request('pernum', 10)))) {
            echo json_encode(array('status' => 'success', 'list' => $list, 'paging' => $review_model->page), JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(array('status' => 'nodata'));
        }
    }

    /**
     * 库存是否充足
     */
    public function action_get_current_stock()
    {
        $goods_id = request('goodsId');

        $goods_model = new goods_model();
        $stock_qty = $goods_model->get_stock_qty($goods_id);
        if ($stock_qty >= 0) {
            echo json_encode(array('success' => true, 'stock' => $stock_qty));
        } else {
            echo json_encode(array('success' => false));
        }
    }
}