<?php

namespace app\model;

use plugin\recommend\Apriori;

class goods_model extends model
{
    public $table_name = 'goods';

    public $rules = array
    (
        'goods_name' => array
        (
            'is_required' => array(TRUE, '商品名称不能为空'),
            'max_length' => array(180, '商品名称不能超过180个字符'),
        ),
        'goods_sn' => array
        (
            'max_length' => array(20, '商品货号不能超过20个字符'),
        ),
        'now_price' => array
        (
            'is_required' => array(TRUE, '当前售价不能为空'),
            'is_decimal' => array(TRUE, '当前售价格式不正确'),
        ),
        'original_price' => array
        (
            'is_decimal' => array(TRUE, '原售价格式不正确'),
        ),
        'stock_qty' => array
        (
            'is_nonegint' => array(TRUE, '库存数量必须是非负整数'),
        ),
        'goods_weight' => array
        (
            'is_decimal' => array(TRUE, '重量格式不正确'),
        ),
    );

    /**
     * 按条件查找商品
     */
    public function find_goods($conditions = array())
    {
        $where = " where status = 1";
        $limit = isset($conditions['limit']) ? $conditions['limit'] : 10;
        $offset = ((isset($conditions['page']) ? $conditions['page'] : 1) - 1) * $limit;
        $order = "";
        $binds = [];

        if (!empty($conditions['newarrival'])) {
            return $this->get_newest(array($offset, $limit));
        }
        if (!empty($conditions['recommend'])) {
            return $this->get_bestseller(null, array($offset, $limit));
        }
        if (!empty($conditions['bargain'])) {
            $where .= ' AND bargain = 1';
        }
        if (!empty($conditions['cate']) && $conditions['cate']) {
            $where .= ' AND cate_id = :cate_id';
            $binds[':cate_id'] = (int)$conditions['cate'];
        }
        if (!empty($conditions['kw'])) {
            $xs = new \XS('goods');
            $search = $xs->search; // 获取 搜索对象

            switch ($conditions['sort']) {
                case 1 :
                    $search->setSort('now_price', true);
                    $order = " order by now_price asc";
                    break;
                case 2:
                    $search->setSort('now_price');
                    $order = " order by now_price desc";
                    break;
                case 3:
                    $search->setSort('selled_amount', true);
                    $order = " order by selled_amount asc";
                    break;
                case 4:
                    $search->setSort('selled_amount');
                    $order = " order by selled_amount desc";
            }

            $search ->addRange('status', 1, 1)
                ->addRange('cate_id', $conditions['cate'], $conditions['cate'])
                ->setQuery($conditions['kw'])
                ->setFuzzy()
                ->setLimit($limit, $offset);

            $docs = $search->search(); // 执行搜索，将搜索结果文档保存在 $docs 数组中
            if (empty($docs)) {
                return null;
            }

            $ids = array();
            foreach ($docs as $doc) {
                $ids[] = (int)$doc['goods_id'];
            }
            $ids = implode(',', $ids);
            $where .= " AND {$this->table_name}.goods_id in ({$ids})";
            $page = "";
        } else {
            $page = $this->get_limit(array($offset, $limit));
        }

        $fields = "goods_id,goods_name,goods_image,goods_brief,now_price,original_price,pre_sell,
         (stock_qty + instead_sell_stock_qty) as stock_qty, selled_amount,created_date";
        $sql = "select {$fields} from {$this->table_name} {$where} GROUP BY goods_id {$order} {$page} ";
        $list = $this->query($sql, $binds);
        if (!empty($list)) {
            foreach ($list as &$goods) {
                $goods['url'] = $GLOBALS['cfg']['image_host'] . $goods['goods_image'] . $GLOBALS['cfg']['image_style']['middle'];
                if ($goods['original_price'] > 0) {
                    $goods['discount'] = sprintf("%.1f",$goods['now_price'] / $goods['original_price'] * 10);
                }
                $goods['goods_brief'] = preg_replace('/(<.*?>|<.*?\/>|&nbsp;)/', '', $goods['goods_brief']);
            }
        }
        return empty($list) ? null : $list;
    }

    /**
     * 获取商品销量
     * @param $goods_id
     * @return mixed
     */
    public function get_goods_sales_volume($goods_id)
    {
        $sql = "select count(order_id) as sales_volume from {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}order_goods where goods_id = {$goods_id} limit 1";
        return $this->query($sql);
    }

    public function set_search_filters($conditions)
    {
        $filters = $binds = array();
        $where = 'WHERE status = 1';

        if ($conditions['kw']) {
            $conditions['kw'] = sql_escape($conditions['kw']);
            if ($GLOBALS['cfg']['goods_fulltext_query'] == 1) {
                $where .= ' AND MATCH (goods_name,meta_keywords) AGAINST (:kw IN BOOLEAN MODE)';
                $binds[':kw'] = $conditions['kw'];
            } else {
                $where .= ' AND (goods_name LIKE :inskw OR LOCATE(:kw, meta_keywords))';
                $binds[':inskw'] = '%' . $conditions['kw'] . '%';
                $binds[':kw'] = $conditions['kw'];
            }
        }

        $sql = "SELECT cate_id, cate_name FROM {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}goods_cate
                WHERE cate_id in (SELECT cate_id FROM {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}goods {$where} GROUP BY cate_id)
                ORDER BY seq ASC
               ";
        $filters['cate'] = $this->query($sql, $binds);

        $sql = "SELECT brand_id, brand_name FROM {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}brand
                WHERE brand_id in (SELECT brand_id FROM {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}goods {$where} GROUP BY brand_id)
                ORDER BY seq ASC
               ";
        $filters['brand'] = $this->query($sql, $binds);

        if ($conditions['cate']) {
            $filters['attr'] = vcache::instance()->goods_cate_attr_model('find_all', array(array('cate_id' => $conditions['cate'], 'filtrate' => 1), 'seq ASC'));
            if ($filters['attr']) {
                $attarr = !empty($conditions['att']) ? explode('@', urldecode($conditions['att'])) : array();
                $newatt = array();
                foreach ($attarr as $u) {
                    if (!empty($u)) $newatt[substr($u, 0, strpos($u, '_'))] = $u;
                }
                $newattstr = !empty($newatt) ? implode('@', $newatt) : '';

                foreach ($filters['attr'] as &$v) {
                    if (!empty($v['opts'])) {
                        $opts = json_decode($v['opts'], TRUE);
                        $v['opts'] = array();
                        foreach ($opts as $k => $o) {
                            $v['opts'][$k]['name'] = $o . $v['uom'];
                            $v['opts'][$k]['att'] = urlencode($newattstr . '@' . $v['attr_id'] . '_' . $o);
                            $v['opts'][$k]['checked'] = 0;
                            if (in_array($v['attr_id'] . '_' . $o, $newatt)) $v['opts'][$k]['checked'] = 1;
                        }
                        $v['unlimit']['att'] = urlencode(implode('@', array_diff_key($newatt, array($v['attr_id'] => ''))));
                        $v['unlimit']['checked'] = isset($newatt[$v['attr_id']]) ? 0 : 1;
                    } else {
                        $v['opts'] = array();
                    }
                }
            }
        }

        //价格筛选
        $filters['price'] = array();
        $sql = "SELECT count(goods_id) AS count, min(now_price) AS min, max(now_price) AS max
                FROM {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}goods {$where}
               ";
        if ($pri_query = $this->query($sql, $binds)) {
            $pri_max_num = round($pri_query[0]['count'] / 10);
            if ($pri_max_num >= 2) {
                if ($pri_max_num >= 6) $pri_max_num = 6;
                $pri_incr = ceil(($pri_query[0]['max'] - $pri_query[0]['min']) / $pri_max_num);
                for ($i = 1; $i <= $pri_max_num; $i++) {
                    $l = $pri_incr * ($i - 1) + 1;
                    $r = $pri_incr * $i;

                    if ($i == 1) $min = 0; else $min = intval(str_pad(substr($l, 0, 2), strlen($l), 9, STR_PAD_RIGHT));
                    if ($i == $pri_max_num) {
                        $max = 0;
                        $str = $min . '以上';
                    } else {
                        $max = intval(str_pad(substr($r, 0, 2), strlen($r), 9, STR_PAD_RIGHT));
                        $str = $min . '-' . $max;
                    }
                    $filters['price'][] = array('min' => $min, 'max' => $max, 'str' => $str);
                }
            }
        }

        return $filters;
    }

    /**
     * 获取猜你喜欢的商品
     */
    public function get_guess_like($cookie = null)
    {
        if ($cookie) {
            $ids = array();
            $history = array_slice(explode(',', $cookie), 0, 5);
            foreach ($history as $k => $v) $ids[$k + 1] = (int)$v;
            $questionmarks = str_repeat('?,', count($ids) - 1) . '?';
            $related_model = new goods_related_model();
            $sql = "SELECT goods_id, goods_name, original_price, now_price, goods_image
                    FROM {$this->table_name}
                    WHERE status = 1 AND goods_id in (SELECT goods_id FROM {$related_model->table_name} WHERE related_id in ({$questionmarks}))
                    ORDER BY goods_id DESC
                   ";
            return $this->query($sql, $ids);
        }

        return null;
    }

    /**
     * 保存商品浏览历史
     */
    public function set_history($goods_id, $num = 20)
    {
        if ($history = request('FOOTPRINT', null, 'cookie')) {
            $history = explode(',', $history);
            if (!in_array($goods_id, $history)) {
                array_unshift($history, $goods_id);
                setcookie('FOOTPRINT', implode(',', array_slice($history, 0, $num)), $_SERVER['REQUEST_TIME'] + 604800, '/');
            }
        } else {
            setcookie('FOOTPRINT', $goods_id, $_SERVER['REQUEST_TIME'] + 604800, '/');
        }
    }

    /**
     * 获取商品浏览历史
     */
    public function get_history($limit = 10)
    {
        if ($cookie = request('FOOTPRINT', null, 'cookie')) {
            $ids = array();
            $history = array_slice(explode(',', $cookie), 0, $GLOBALS['cfg']['goods_history_num']);
            foreach ($history as $k => $v) $ids[$k + 1] = (int)$v;
            $marks = str_repeat('?,', count($ids) - 1) . '?';
            $sql = "SELECT goods_id, goods_name, original_price, now_price, goods_image
                    FROM {$this->table_name}
                    WHERE goods_id in ({$marks})
                   ";
            if (!empty($limit)) $sql .= " LIMIT {$limit}";
            return $this->query($sql, $ids);
        }
        return null;
    }

    /**
     * 获取购物车中商品数据
     */
    public function get_cart_items(array $items)
    {
        $ids = array();
        $i = 0;
        foreach ($items as $v) {
            if (!in_array($v['id'], $ids)) {
                $i += 1;
                $ids[$i] = (int)$v['id'];
            }
        }
        unset($i);

        if (empty($ids)) return FALSE;

        $marks = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "SELECT goods_id, goods_name, now_price, goods_image,pre_sell, goods_weight, (stock_qty + instead_sell_stock_qty) as stock_qty
                ,instead_sell_stock_qty FROM {$this->table_name} WHERE goods_id in ({$marks}) and status = 1";

        if ($goods_map = $this->query($sql, $ids)) {
            unset($ids, $marks);
            $goods_map = array_column($goods_map, null, 'goods_id');
            $res['items'] = array();
            $res['shortAgeItems'] = array();
            $res['amount'] = $res['qty'] = $res['weight'] = 0;
            foreach ($items as $k => $v) {
                if (!isset($goods_map[$v['id']])) continue;
                $item = $goods_map[$v['id']];
                $item['opts'] = null;
                $item['now_price'] = $goods_map[$v['id']]['now_price'];
                if (!empty($v['opts'])) {
                    $ids = array();
                    foreach ($v['opts'] as $i => $opt_id) $ids[$i + 1] = (int)$opt_id;
                    $marks = str_repeat('?,', count($ids) - 1) . '?';
                    $item['opts'] = $this->query(
                        "SELECT a.id, a.opt_text, a.opt_price, b.name as type
                         FROM {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}goods_optional AS a
                         INNER JOIN {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}goods_optional_type AS b
                         ON a.type_id = b.type_id
                         WHERE a.goods_id = " . (int)$v['id'] . " AND a.id in ({$marks})", $ids
                    );
                    foreach ($item['opts'] as $prices) $item['now_price'] += $prices['opt_price'];
                }
                $item['now_price'] = sprintf('%1.2f', $item['now_price']);
                $item['qty'] = (int)$v['qty'] ? $v['qty'] : 1;
                $item['pre_sell'] = $goods_map[$v['id']]['pre_sell'];
                $item['subtotal'] = sprintf('%1.2f', $item['now_price'] * $item['qty']);
                $item['json'] = json_encode($v);
                $res['amount'] += $item['subtotal'];
                $res['qty'] += $item['qty'];
                $res['weight'] += (float)$item['goods_weight'];

                if ($goods_map[$v['id']]['stock_qty'] >= $v['qty']) {
                    $res['items'][$k] = $item;
                } else {
                    $res['shortAgeItems'][$k] = $item;
                }
            }
            $res['amount'] = sprintf('%1.2f', $res['amount']);
            $res['kinds'] = count($res['items']) + count($res['shortAgeItems']);
            return $res;
        }
        return FALSE;
    }

    /**
     * 获取相关联商品
     */
    public function get_related($goods_id, $limit = 6)
    {
        //缓存命中
        $cache_key = 'goods_recommend_'.$goods_id;
        $vcache = new \vcache();
        $cache = $vcache->get($cache_key);
        if ($cache) {
            return $cache;
        }

        $order_goods_model = new order_goods_model();
        $data = $order_goods_model->find_all(null, null, "order_id,goods_id");
        foreach ($data as &$v) {
            $v = array_values($v);
        }

        //variables
        $minSupp = 1;                  //minimal support
        $minConf = 1;                 //minimal confidence
        $type = Apriori::SRC_DB; //data type
        try {
            $apri = new Apriori($type, $data, $minSupp, $minConf);
            $recommend = $apri->solve()
                ->generateRules()
                ->getRecommendations($goods_id);
            $result = array();
            $count = 0;
            foreach ($recommend as $k => $v) {
                if ($count >= $limit) {
                    break;
                }
                $set = explode(",", $v['Y']);
                foreach ($set as $goods_id) {
                    if (array_search($goods_id, $result) === false) {
                        $result[] = $goods_id;
                        $count++;
                        if ($count >= $limit) {
                            break;
                        }
                    }
                }
            }

            $goods_recommend = null;
            if (!empty($result)) {
                $where = "goods_id in (" . implode(",", $result) . ")";
                $sql = "select * from {$this->table_name} where {$where}";
                $goods_recommend = $this->query($sql);
                foreach ($goods_recommend as &$goods) {
                    if ($goods['original_price'] > 0) {
                        $goods['discount'] = sprintf("%.1f",$goods['now_price'] / $goods['original_price'] * 10);
                    }
                    $goods['goods_brief'] = preg_replace('/(<.*?>|<.*?\/>|&nbsp;)/', '', $goods['goods_brief']);
                }
                $vcache->set($cache_key, $goods_recommend);//设置缓存
            }
            return $goods_recommend;

        } catch (\Exception $exc) {
            //echo $exc->getMessage();
            return [];
        }
    }

    /**
     * 商品销售排行
     */
    public function get_bestseller($cate_id = null, $limit = null)
    {
        $limit = $this->get_limit($limit);
        $where = "WHERE 1";
        if (!empty($cate_id)) $where .= " AND b.cate_id = {$cate_id}";
        $sql = "SELECT a.goods_id,pre_sell, COUNT(1) AS count, b.goods_name, b.now_price, b.goods_image,b.stock_qty,b.original_price
                FROM {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}order_goods AS a 
                INNER JOIN {$this->table_name} AS b
                ON a.goods_id = b.goods_id
                {$where}
                GROUP BY a.goods_id
                ORDER BY COUNT(1) DESC
                {$limit}
               ";
        return $this->query($sql);
    }

    public function get_newest($limit)
    {
        $limit = $this->get_limit($limit);
        $sql = "select goods_id, goods_name,pre_sell, now_price, goods_image,stock_qty,original_price 
                FROM {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}goods where status = 1 ORDER BY created_date DESC 
                {$limit}";
        return $this->query($sql);
    }

    /**
     * 获取limit语句
     * @param $limit
     * @return string
     */
    private function get_limit($limit)
    {
        if (count($limit) == 2) {
            return "LIMIT " . $limit[0] . "," . $limit[1];//修复下面这条语句的bug
        } else {
            return "";
        }
    }

    public function find_by_goods_sn($isbn)
    {
        $isbn = implode(",", $isbn);
        $sql = "select * from {$this->table_name} where goods_sn in ($isbn)";
        return $this->query($sql);
    }

    public function record_search_log($log)
    {
        if (trim($log['keyword']) === '') {
            return;
        }
        $sql = "INSERT INTO {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}search_log (user_id, keyword, result) VALUES (:user_id, :keyword, :result) ";
        return $this->execute($sql, array(':user_id' => $log['user_id'], ':keyword' => $log['keyword'], ':result' => $log['result']));
    }

    public function hot_searches($start_date, $end_date,  $limit = 0)
    {
        $limit = $limit > 0 ? " limit 0,$limit " : "";

        $sql = "select keyword,result,count(*) as amount from verydows_search_log where time >= '$start_date' and time <= '$end_date' group by keyword order by amount desc $limit";
        $res = $this->query($sql);
        return $res;
    }

    /**
     *
     * @param $goods_id
     * @return number
     */
    public function get_stock_qty($goods_id)
    {
        $goods = $this->find(['goods_id' => $goods_id, 'status' => 1], null, 'stock_qty,instead_sell_stock_qty');
        if (empty($goods)) {
            return -1;
        }

        return $goods['stock_qty'] + $goods['instead_sell_stock_qty'];
    }

    public function find_all_by_ids($ids)
    {
        if (empty($ids)) {
            return [];
        }
        $ids = implode(',',$ids);
        $sql = "select * from $this->table_name where goods_id in ($ids)";
        return $this->query($sql);
    }

}