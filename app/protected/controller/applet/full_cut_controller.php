<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/11
 * Time: 下午10:07
 */

class full_cut_controller extends general_controller
{
    public function action_get()
    {
        $vcache = new vcache();
        $activity = $vcache->full_cut_model('get_underway_activity');
        if (empty($activity)) {
            $this->r(false);
        } else {
            $this->r(true, 'ok', $activity);
        }
    }
}