<?php

use app\model\goods_model;

class search_controller extends general_controller
{
    public function action_index()
    {
        $vcache = vcache::instance();
        $conditions = array
        (
            'cate' => (int)request('cate', 0),
            'brand' => (int)request('brand', 0),
            'att' => request('att', ''),
            'minpri' => request('minpri', null),
            'maxpri' => request('maxpri', null),
            'kw' => strip_tags(trim(request('kw', ''))),
            'sort' => (int)request('sort', 0),
            'page' => (int)request('page', 1),
        );

        $goods_model = new goods_model();
        $goods = $goods_model->find_goods($conditions);
        $this->goods_list = $goods;
        //过滤搜索记录
        if (!isset($_SESSION['FILTER_SEARCH'])) {
            $result = (!empty($goods)) ? implode(",",array_column($this->goods_list, 'goods_id', null)) : "";
            $log = [
                'user_id' => isset($_SESSION['USER']['USER_ID']) ? $_SESSION['USER']['USER_ID'] : 0,
                'result' => $result,
                'keyword' => $conditions['kw']
            ];
            $goods_model->record_search_log($log);
        }

        $this->filters = $goods_model->set_search_filters($conditions);
        $this->u = $conditions;
        $this->hot_searches = array_column($vcache->goods_model("hot_searches",
            array(date("Y-m-d H:i:s", 0), date("Y-m-d H:i:s", strtotime('tomorrow')), 6)), "keyword", null);

        $this->compiler('search.html');
    }
}
