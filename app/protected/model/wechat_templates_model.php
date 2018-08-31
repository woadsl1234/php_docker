<?php

/**
 * Created by PhpStorm.
 * User: chenshengkun
 * Date: 2017/11/11
 * Time: 上午11:34
 */

namespace app\model;

class wechat_templates_model extends model
{
    public $table_name = 'wechat_templates';

    /**
     * 获取微信推送模板id
     * @param $key
     * @return null
     */
    public function get_template_id($key)
    {
        $res = $this->find(['tpl_key' => $key]);
        if ($res) {
            return $res['template_id'];
        } else {
            return null;
        }
    }
}