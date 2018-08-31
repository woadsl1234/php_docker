<?php
/**
 * Created by PhpStorm.
 * User: chenshengkun
 * Date: 2017/11/8
 * Time: 下午10:20
 */

namespace app\model;

use app\dto\notice_dto;

class full_cut_model extends model
{
    public $table_name = 'full_cut';

    public $rules = array
    (
        'title' => array
        (
            'max_length' => array(20, '活动名称不能超过20个字符'),
        ),
        'start_time' => array
        (
            'is_required' => array(TRUE, '开始时间不能为空'),
        ),
        'end_time' => array
        (
            'is_required' => array(TRUE, '结束时间不能为空'),
        )
    );

    protected $status_desc_map = array
    (
        "not-start" => "未开始",
        "going-on" => "进行中",
        "end" => "已结束"
    );

    public function save($data)
    {
        $title = $data['title'];
        $start_time = $data['start_time'];
        $end_time = $data['end_time'];
        $list = $data['list'];
        $updated_at = date("Y-m-d H:i:s");

        $sql = "replace into $this->table_name(id, title, start_time, end_time, list, updated_at) select 1,
                '$title', '$start_time', '$end_time', '$list', '$updated_at'";
        return $this->query($sql);
    }

    public function get()
    {
        $activity = $this->find(array('id' => 1));

        if (!empty($activity)) {
            $activity['list'] = arraySequence(json_decode($activity['list'], true), 'order_fee');
            $activity['status'] = $this->get_status($activity['start_time'], $activity['end_time']);
            $activity['status_str'] = $this->status_desc_map[$activity['status']];

            return $activity;
        }

        return null;
    }

    /**
     * 获取进行中的活动
     * @return mixed
     */
    public function get_underway_activity()
    {
        $now = time();
        $activity = $this->get();
        $start_time = strtotime($activity['start_time']);
        $end_time = strtotime($activity['end_time']);
        if (!empty($activity) && ($now > $start_time && $now < $end_time)) {
            $activity['type'] = '满减';
            return $activity;
        }
        return null;
    }

    /**
     * 状态描述
     * @param $start_time
     * @param $end_time
     * @return string
     */
    public function get_status($start_time, $end_time)
    {
        $now = time();
        if (strtotime($start_time) > $now) {
            return "not-start";
        } elseif (strtotime($end_time) < $now) {
            return "end";
        } else {
            return "going-on";
        }
    }

    /**
     * @return notice_dto|array|null
     * @throws \Dto\Exceptions\InvalidDataTypeException
     */
    public function get_notice()
    {
        $activity = $this->find(array('id' => 1));
        $notice = null;
        if (!empty($activity)
            && $this->get_status($activity['start_time'], $activity['end_time']) == 'going-on') {
            $value = $activity['title'];
            $list = $activity['list'] = arraySequence(json_decode($activity['list'], true), 'order_fee');
            if (!empty($list)) {
                foreach ($list as $item) {
                    $value .= "，" . "满" . $item['order_fee'] . "减" . $item['discount_fee'];
                }
            }
            $notice = new notice_dto([
                'label' => '满减',
                'value' => $value
            ]);
            return $notice->toArray();
        }

        return $notice;
    }
}