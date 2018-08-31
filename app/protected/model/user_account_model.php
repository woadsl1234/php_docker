<?php

namespace app\model;

use plugin\push\kafka;
use plugin\push\phone;

class user_account_model extends model
{
    public $table_name = 'user_account';

    public $rules = array
    (
        'balance' => array
        (
            'is_decimal' => array(TRUE, '余额值格式不正确'),
        ),
        'points' => array
        (
            'is_nonegint' => array(TRUE, '积分值格式不正确'),
        ),
        'exp' => array
        (
            'is_nonegint' => array(TRUE, '经验值格式不正确'),
        ),
    );

    public function get_user_account($user_id)
    {
        $sql = "SELECT balance, points, exp, group_name, discount_rate 
                FROM {$this->table_name} JOIN {$GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']}user_group
                WHERE user_id = :user_id AND exp >= min_exp ORDER BY min_exp DESC LIMIT 1";
        if ($res = $this->query($sql, array(':user_id' => $user_id))) return $res[0];
        return null;
    }

    /**
     * @param $user_id
     * @param $data
     * @param $reason
     * @return bool
     */
    public function update_account($user_id, $data, $reason = '')
    {
        $sql = "UPDATE {$this->table_name}
                SET
                    balance = balance + :balance,
                    points = points + :points,
                    exp = exp + :exp
                WHERE
                    user_id = :user_id
               ";

        $binds = array(
            ':balance' => isset($data['balance']) ? $data['balance'] : 0.00,
            ':points' => isset($data['points']) ? $data['points'] : 0.00,
            ':exp' => isset($data['exp']) ? $data['exp'] : 0.00,
            ':user_id' => $user_id
        );
        if($this->execute($sql, $binds) > 0) {
            $log = [
                'user_id' => $user_id,
                'balance' => $binds[':balance'],
                'admin_id' => isset($_SESSION['ADMIN']['USER_ID']) ? $_SESSION['ADMIN']['USER_ID'] : '' ,
                'dateline' => $_SERVER['REQUEST_TIME'],
                'cause' => $reason
            ];
            $log_model = new user_account_log_model();
            $log_model->create($log);

            if (isset($data['balance']) && $data['balance']) {
                $this->send_account_change_info($user_id, $data['balance']);
            }
            return TRUE;
        }
        return FALSE;
    }

    /**
     * 发送账户变动提醒
     * @param $user_id
     * @param $amount
     */
    public function send_account_change_info($user_id, $amount)
    {
        $type = $amount > 0 ? "充值" : "扣除";
        $amount = sprintf('%.2f', abs($amount));
        $account = $this->get_user_account($user_id);
        $current_amount = sprintf('%.2f', $account['balance']);
        $current_date = date('n月j日H:i', time());
        $user_model = new user_model();
        $user = $user_model->find(['user_id' => $user_id]);
        $params = [
            $current_date,
            $type.$amount,
            $current_amount
        ];

        // 发布账户变动消息
        kafka::produce(kafka::USER_ACCOUNT_CHANGED, [
            // 短信数据
            'message' => [
                'phoneNumber' => $user['mobile'],
                'tempId' => phone::SCENE_BALANCE_INFO,
                'params' => $params
            ],
        ]);
    }

    /**
     * 活动金额
     * @param $account
     * @return int
     */
    public function get_recharge_in_activity($account)
    {
        $activity_map = array
        (
            30 => 35,
            50 => 60,
            80 => 100
        );
        if (isset($activity_map[$account])) {
            return $activity_map[$account];
        } else {
            return $account;
        }
    }
}