<?php

use app\model\goods_model;
use app\model\order_model;
use app\model\visitor_stats_model;

class stats_controller extends general_controller
{
    public function action_order()
    {
        if(request('step') == 'search')
        {
            $start_year = request('start_year', '');
            if(empty($start_year)) $start_year = date('Y'); 
            $start_timestamp = strtotime($start_year.'0101');
            $next_timestamp = strtotime($start_year + 1 .'0101');
            
            $order_model = new order_model();
            $sql = "SELECT COUNT(*) AS num, FROM_UNIXTIME(created_date, '%m') AS month 
                    FROM {$order_model->table_name}
                    WHERE created_date >= {$start_timestamp} and order_status >= 2 && created_date < {$next_timestamp}
                    GROUP BY month
                   ";
            if($stats_data = $order_model->query($sql))
            {
                $results = array('status' => 'success', 'data' => $stats_data);
            }
            else
            {
                $results = array('status' => 'nodata');
            }
            echo json_encode($results);
        }
        else
        {
            include(VIEW_DIR.DS.'function'.DS.'html_date_options.php');
            $today_stamp = strtotime('today');
            $yesterday_stamp = strtotime('yesterday');
            $this_month_stamp = strtotime(date('Ym').'01');
            $order_model = new order_model();
            $sql = "SELECT COUNT(*) AS total, 
                    SUM(CASE WHEN order_status = 2 then 1 else 0 end) AS paid,
                    SUM(CASE WHEN order_status = 1 then 1 else 0 end) AS nonpay,
                    SUM(CASE WHEN order_status = 0 then 1 else 0 end) AS canceled
                    FROM {$order_model->table_name}
                    WHERE
                   ";
            $today = $order_model->query($sql." created_date >= {$today_stamp}");
            $yesterday = $order_model->query($sql." created_date >= {$yesterday_stamp} && created_date < {$today_stamp}");
            $this_month = $order_model->query($sql." created_date >= {$this_month_stamp} && created_date < {$today_stamp}");
            $this->latest = array
            (
                '今日' => $today[0],
                '昨日' => $yesterday[0],
                '本月' => $this_month[0],
            );
            $this->def_year = date('Y');
            $this->compiler('operation/stats_order.html');
        }
    }
    
    public function action_revenue()
    {
        if(request('step') == 'search')
        {
            $start_year = request('start_year', '');
            if(empty($start_year)) $start_year = date('Y'); 
            $start_timestamp = strtotime($start_year.'0101');
            $next_timestamp = strtotime($start_year + 1 .'0101');
            
            $order_model = new order_model();
            $sql = "SELECT SUM(order_amount - refund_amount) AS revenue, FROM_UNIXTIME(created_date, '%m.%d') AS month 
                    FROM {$order_model->table_name}
                    WHERE order_status >= 2 AND created_date >= {$start_timestamp} AND created_date < {$next_timestamp}
                    GROUP BY month
                   ";
            if($stats_data = $order_model->query($sql))
            {
                $results = array('status' => 'success', 'data' => $stats_data);
            }
            else
            {
                $results = array('status' => 'nodata');
            }
            echo json_encode($results);
        }
        else
        {
            include(VIEW_DIR.DS.'function'.DS.'html_date_options.php');
            $today_stamp = strtotime('today');
            $yesterday_stamp = strtotime('yesterday');
            $this_month_stamp = strtotime(date('Ym').'01');
            $order_model = new order_model();
            $sql = "SELECT SUM(order_amount) AS revenue
                    FROM {$order_model->table_name}
                    WHERE order_status >= 2
                   ";
            $today = $order_model->query($sql." AND created_date >= {$today_stamp}");
            $yesterday = $order_model->query($sql." AND created_date >= {$yesterday_stamp} AND created_date < {$today_stamp}");
            $this_month = $order_model->query($sql." AND created_date >= {$this_month_stamp} AND created_date < {$today_stamp}");
            $this->latest = array
            (
                '今日' => $today[0],
                '昨日' => $yesterday[0],
                '本月' => $this_month[0],
            );
            $this->def_year = date('Y');
            $this->compiler('operation/stats_revenue.html');
        }
    }
    
    public function action_visitor()
    {
        $todaystamp = strtotime('today');
        $this->todaystamp = $todaystamp;
        switch(request('col'))
        {
            case 'referrer':
                
                if(request('search') == 'async')
                {
                    $start_date = strtotime(request('start_date', ''));
                    $end_date = strtotime(request('end_date', date('Ymd')));
                    $results = array('status' => 'nodata');
                    if($start_date != FALSE && $end_date != FALSE && $end_date > $start_date)
                    {
                        $type = request('type', '');
                        $stats_model = new visitor_stats_model();
                        if($stats_data = $stats_model->stats_period_referrer($type, $start_date, $end_date))
                        {
                            $results = array
                            (
                                'status' => 'success',
                                'data' => $stats_data,
                            );
                        }
                    }
                    echo json_encode($results);
                }
                else
                {
                    $stats_model = new visitor_stats_model();
                    $this->latest = array
                    (
                        '今日' => $stats_model->stats_period_referrer('cate', $todaystamp, $todaystamp),
                        '昨日' => $stats_model->stats_period_referrer('cate', $todaystamp - 86400, $todaystamp - 86400),
                    );
                    $this->compiler('operation/stats_referrer.html');
                }
                
            
            break;
            
            case 'terminal':
                 
                if(request('search') == 'async')
                {
                    $start_date = strtotime(request('start_date', ''));
                    $end_date = strtotime(request('end_date', date('Ymd')));
                    $type = request('type', '');
                    $results = array('status' => 'nodata');
                    if($start_date != FALSE && $end_date != FALSE && $end_date > $start_date && in_array($type, array('browser', 'platform')))
                    {
                        $stats_model = new visitor_stats_model();
                        if($stats_data = $stats_model->stats_period_terminal($type, $start_date, $end_date))
                        {
                            $results = array
                            (
                                'status' => 'success',
                                'data' => $stats_data,
                            );
                        }
                    }
                    echo json_encode($results);
                }
                else
                {
                    $this->compiler('operation/stats_terminal.html');
                }

            break;
            
            case 'area':
            
                if(request('search') == 'async')
                {
                    $start_date = strtotime(request('start_date', ''));
                    $end_date = strtotime(request('end_date', date('Ymd')));
                    $results = array('status' => 'nodata');
                    if($start_date != FALSE && $end_date != FALSE && $end_date > $start_date)
                    {
                        $stats_model = new visitor_stats_model();
                        if($stats_data = $stats_model->stats_period_area($start_date, $end_date))
                        {
                            $results = array
                            (
                                'status' => 'success',
                                'data' => $stats_data,
                            );
                        }
                    }
                    echo json_encode($results);
                }
                else
                {
                    $this->compiler('operation/stats_area.html');
                }
            
            break;
            
            case 'traffic':
            default:
                
                if(request('search') == 'async')
                {
                    $start_date = strtotime(request('start_date', ''));
                    $end_date = strtotime(request('end_date', date('Ymd')));
                    $results = array('status' => 'nodata');
                    if($start_date != FALSE && $end_date != FALSE && $end_date > $start_date)
                    {
                        $stats_model = new visitor_stats_model();
                        if($stats_data = $stats_model->stats_period_traffic($start_date, $end_date))
                        {
                            $results = array
                            (
                                'status' => 'success',
                                'data' => $stats_data,
                            );
                        }
                    }
                    echo json_encode($results);
                }
                else
                {
                    $stats_model = new visitor_stats_model();
                    $this->latest = $stats_model->stats_period_traffic(strtotime('yesterday'), strtotime('today'), 'dateline DESC');
                    $this->compiler('operation/stats_traffic.html');
                }
        }
    }

    public function action_searcher()
    {
        if (request('step', '') == 'chart') {
            $start_date = request('start_date', date("Y-m-d H:i:s", strtotime('today')));
            $end_date = request('end_date', date("Y-m-d H:i:s", strtotime('tomorrow')));
            $goods_model = new goods_model();
            $hot_searches = $goods_model->hot_searches($start_date, $end_date);
            echo json_encode(array('status' => 'success', 'data' => $hot_searches), JSON_UNESCAPED_UNICODE);
            return;
        }
        $_SESSION['FILTER_SEARCH'] = 1;
        $this->todaystamp = time();
        $this->compiler('operation/stats_searcher.html');
    }

    public function action_data()
    {
        $sql = "select from_unixtime(created_date, '%Y-%m') as cd ,count(*) as amount from verydows_user_record  group by cd";
        $order_model = new order_model();
        $res = $order_model->query($sql);
        $total = 0;
        $date = array();
        $amounts = array();
        $amounts1 = array();
        foreach ($res as &$row) {
            $amounts1[] = $row['amount'];
            $date[] = '"' . $row['cd'] . '"';
            $total += $row['amount'];
            $amounts[] = $total;
        }
        echo implode(',', $amounts1) ."<br>";
        echo implode(',', $amounts) . "<br>";

        $num = 0;
        $percents = 0;
        foreach ($amounts as $key => $val) {
            if ($key <= 1) {
                continue;
            }
            $num ++;
            $percent = $amounts1[$key] / $amounts[$key - 1] * 100;
            echo sprintf("%.2f", $percent) . "% ";
            $percents += $percent;
        }

        echo "<br>月均用户增速为：" . sprintf("%.2f", $percents / $num) . "%";
    }
}