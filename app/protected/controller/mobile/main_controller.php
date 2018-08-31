<?php

class main_controller extends general_controller
{
    public function action_index()
    {
/*        $vcache = vcache::instance();
        $this->hot_searches = array_column($vcache->goods_model("hot_searches",
            array(date("Y-m-d H:i:s", 0), date("Y-m-d H:i:s", strtotime('tomorrow')), 6)), "keyword", null);
        $categories = $vcache->goods_cate_model('goods_cate_bar');
        if (!empty($categories)) {
            foreach ($categories as &$category) {
                $category['list'] = $vcache->goods_model('find_goods', array(array('cate' => $category['cate_id']), array('page' => 1, 'limit' => 6)), $GLOBALS['cfg']['data_cache_lifetime']);
            }
        }
        $this->categories = $categories;
        $this->full_cut = $vcache->full_cut_model('get_underway_activity');

        $this->compiler('index.html');*/
        $this->compiler('scan.html');
    }

    public function action_more()
    {
        header("Content-type: text/html; charset=utf-8");

        $vcache = vcache::instance();

        $res = $vcache->goods_model('find_goods', array($_GET, array(request('page', 1), request('pernum', 10))), $GLOBALS['cfg']['data_cache_lifetime']);

        if ($res) {
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([]);
        }
    }

    public function action_400()
    {
        $this->status = 400;
        $this->title = '错误请求';
        $this->content = '您的客户端发送了一个错误或非法的请求';
        $this->compiler('error.html');
        exit;
    }

    public function action_404()
    {
        $this->status = 404;
        $this->title = '页面未找到';
        $this->content = '很抱歉, 你要访问的页面或资源不存在';
        $this->compiler('error.html');
        exit;
    }
}
