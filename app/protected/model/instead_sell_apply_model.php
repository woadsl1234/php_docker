<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/3/10
 * Time: 上午10:14
 */

namespace app\model;

class instead_sell_apply_model extends model
{
    public $table_name = 'instead_sell_apply';

    const account_type_alipay = 1;
    const account_type_mp_wxpay = 2;
    const account_type_applet_wxpay = 3;

    const status_reject = 0;//拒绝
    const status_review = 1;//审核中
    const status_pass = 2;//审核通过
    const status_putaway = 3;//已上架

    const status_map = [
        self::status_reject => "被拒绝",
        self::status_review => "待受理",
        self::status_pass => "已受理",
        self::status_putaway => "已完成",
    ];

    const way_exchange = 1;//以旧换新
    const way_instead_way = 2;//书籍帮卖

    const way_map = [
        self::way_exchange => '以旧换新',
        self::way_instead_way => '书籍帮卖'
    ];

    public function apply_list($status)
    {
        $condition = '';
        if ($status != '') {
            $condition .= ' where p.status = ' . $status;
        }
        $sql = "select p.*,s.address,u.username,s.mobile,s.receiver,r.name from {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}instead_sell_apply as p 
                left join {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}user_consignee as s on p.csn_id = s.id
                left JOIN {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}user as u on p.user_id = u.user_id
                left JOIN {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}recycle_users as r on p.recycle_user_id = r.id
                {$condition}
                order by p.time desc";
        return $this->query($sql);
    }
}