<?php

use app\model\token_model;

class general_controller extends Controller
{
    public function init()
    {
        utilities::crontab();
    }

    protected function is_logined()
    {
        //兼容小程序端
        $jwt = request("token", null);
        if ($jwt) {
            $token_model = new token_model();
            $data = $token_model->resolve($jwt);
            if (isset($data['user_id']) && $data['user_id']) {
                $_SESSION['USER']['USER_ID'] = $data['user_id'];
            }
        }

        if (isset($_SESSION['USER']['USER_ID'])) return $_SESSION['USER']['USER_ID'];
        die(json_encode(array('status' => 'unlogined', 'msg' => '您还未登陆或登录超时')));
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
}