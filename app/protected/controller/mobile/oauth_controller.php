<?php

use app\model\oauth_model;
use app\model\request_error_model;
use app\model\user_oauth_model;

class oauth_controller extends general_controller
{
    public function action_bind()
    {
        $party = sql_escape(request('party'));
        $oauth_model = new oauth_model();
        if($oauth = $oauth_model->find(array('party' => $party)))
        {
            $oauth_obj = plugin::instance('oauth', $party, array($oauth['params']), TRUE);
            if($access_token = $oauth_obj->check_callback($_GET))
            {
                if($oauth_key = $oauth_obj->get_oauth_key($access_token)) {
                    $user_oauth_model = new user_oauth_model();
                    if ($user_oauth_model->is_authorized($party, $oauth_key, $unionid)) {
                        //刷新unionid
                        if (!$unionid && ($party == 'wechat')) {
                            $res = $oauth_obj->get_user_info($access_token, $oauth_key);
                            $row = array(
                                'unionid' => $res['unionid']
                            );
                            $user_oauth_model->update(array('oauth_key' => $oauth_key), $row);
                        }
                        if (isset($_SESSION['REDIRECT'])) {
                            jump($_SESSION['REDIRECT']);
                        } else {
                            jump(url('mobile/user', 'index'));
                        }
                        return;
                    }

                    //统一用户id，直接绑定
                    $userinfo = $oauth_obj->get_user_info($access_token, $oauth_key);
                    if (isset($res['unionid']) && ($userinfo['unionid'] != '')
                        && ($user_id = $user_oauth_model->is_authorized_by_unionid($userinfo['unionid']))) {
                        $row = array(
                            'user_id' => $user_id,
                            'party' => $party,
                            'oauth_key' => $oauth_key,
                            'unionid' => $userinfo['unionid']
                        );
                        $user_oauth_model->create($row);
                        if (isset($_SESSION['REDIRECT'])) {
                            jump($_SESSION['REDIRECT']);
                        } else {
                            jump(url('mobile/user', 'index'));
                        }
                        return;
                    }

                    $_SESSION['OAUTH']['KEY'] = $oauth_key;
                    $_SESSION['OAUTH']['UNIONID'] = isset($userinfo['unionid']) ? $userinfo['unionid'] : '';
                    $_SESSION['OAUTH']['NICKNAME'] = isset($userinfo['nickname']) ? $userinfo['nickname'] : '';
                    $_SESSION['OAUTH']['AVATAR'] = isset($userinfo['avatar']) ? $userinfo['avatar'] : '';
                    $_SESSION['OAUTH']['GENDER'] = isset($userinfo['gender']) ? $userinfo['gender'] : 0;

                    $this->oauth = array('name' => $oauth['name'], 'party' => $party);
                    $error_model = new request_error_model();
                    $this->login_captcha = $error_model->check(get_ip(), $GLOBALS['cfg']['captcha_user_login']);
                    $this->compiler('oauth_bind.html');
                }
                else
                {
                    $this->prompt('error', '获取第三方授权登录身份标识失败!', url('mobile/user', 'login'), 5);
                }
            }
            else
            {
                $this->prompt('error', '第三方授权验证未通过!', url('mobile/user', 'login'), 5);
            }
        }
        else
        {
            jump(url('mobile/main', '404'));
        }
    }
}