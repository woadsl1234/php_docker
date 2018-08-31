<?php
use app\model\token_model;

class general_controller extends \Controller
{
    public function init()
    {
        utilities::crontab();
    }

    /**
     * 输出数据
     * @param $success
     * @param string $msg
     * @param array $data
     */
    protected function r($success, $msg = 'ok', array $data = [])
    {
        $success = (bool) $success;
        if (!empty($data)) {
            die(json_encode(['success' => $success, 'msg' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE));
        } else {
            die(json_encode(['success' => $success, 'msg' => $msg], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 是否登录
     * @return bool
     */
    protected function is_logined()
    {
        $jwt = request('token');
        $token_model = new token_model();
        $data = $token_model->resolve($jwt);
        if(!isset($data['user_id']) || !$data['user_id'])
        {
            return false;
        }
        $_SESSION['USER']['USER_ID'] = $data['user_id'];
        return $data['user_id'];
    }
}