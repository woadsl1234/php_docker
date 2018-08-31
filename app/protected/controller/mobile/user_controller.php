<?php

use app\model\oauth_model;
use app\model\order_goods_model;
use app\model\order_model;
use app\model\request_error_model;
use app\model\user_account_model;
use app\model\user_model;
use app\model\user_profile_model;

class user_controller extends general_controller
{
    public function action_index()
    {
        $user_id = $this->is_logined();
        $user_model = new user_model();
        $this->user = $user_model->find(array('user_id' => $user_id));
        $account_model = new user_account_model();
        $this->account = $account_model->get_user_account($user_id);
        $this->compiler('user_index.html');
    }

    public function action_login_by_password()
    {

        $this->compiler('login.html');
    }

    public function action_login()
    {
        $this->step = (int)request('step', 1);
        switch($this->step) {
            case 1:
                unset($_SESSION['REGISTER']['UID']);
                $client_ip = get_ip();
                if($cookie = request('USER_STAYED', null, 'cookie'))
                {
                    $user_model = new user_model();
                    if($user_model->check_stayed($cookie, $client_ip)) jump(url('mobile/user', 'index'));
                }
                $error_model = new request_error_model();
                $this->captcha = $error_model->check($client_ip, $GLOBALS['cfg']['captcha_user_login']);
                $oauth_model = new oauth_model();
                $this->oauth_list = $oauth_model->get_enable_list('mobile');
                $this->compiler('register_by_mobile.html');
                break;

            case 2:
                $mobile = trim(request('mobile', '', 'post'));
                $phone = plugin::instance('push', 'phone');
                $res = $phone->send_code($mobile);
                if (true !== $res) {
                    $this->prompt('error', $res, url(array('c'=>'mobile/user', 'a'=>'login')));
                }

                $user_model = new user_model();
                if ($mobile && ($user = $user_model->find(array('mobile' => $mobile), null))) {
                    $_SESSION['REGISTER']['UID'] = $user['user_id'];
                    $this->username = $user['username'];
                } else {
                    $this->username = null;
                }
                $this->mobile = $mobile;
                $_SESSION['TEMP']['MOBILE'] = $mobile;
                $this->compiler('register_by_mobile.html');
                break;

            case 3:
                if (!isset($_SESSION['TEMP']['MOBILE']) || empty($_SESSION['TEMP']['MOBILE'])) jump(url('mobile/main', '400'));

                $phone = plugin::instance('push', 'phone', null, TRUE);
                if (!$phone->verify_code($_SESSION['TEMP']['MOBILE'], trim(request('phone_captcha', '', 'post')))) {
                    $this->prompt('error', "验证码错误，请重新发送", url('mobile/user', 'login'));
                }
                if (isset($_SESSION['REGISTER']['UID']) && $_SESSION['REGISTER']['UID']) {
                    $_SESSION['USER']['USER_ID'] = $_SESSION['REGISTER']['UID'];
                    $jump_url = isset($_SESSION['REDIRECT']) ? $_SESSION['REDIRECT'] : url('mobile/user', 'index');
                    jump($jump_url);
                }
                $_SESSION['REGISTER']['MOBILE'] = $_SESSION['TEMP']['MOBILE'];
                $this->compiler('register_by_mobile.html');
                break;

            case 4:
                if (!isset($_SESSION['REGISTER']['MOBILE']) || empty($_SESSION['REGISTER']['MOBILE'])) {
                    jump(url('mobile/main', '400'));
                }
                $jump_url = isset($_SESSION['REDIRECT']) ? $_SESSION['REDIRECT'] : url('mobile/user', 'index');
                $type = request('type', '');

                if ($type == "reg") {
                    $data = array
                    (
                        'username' => trim(request('username', '', 'post')),
                        'mobile' => trim($_SESSION['REGISTER']['MOBILE']),
                        'password' => trim(request('password', '', 'post')),
                        'repassword' => trim(request('repassword', '', 'post')),
                        'captcha' => strtolower(trim(request('captcha', ''))),
                    );

                    $user_model = new user_model();
                    $verifier = $user_model->verifier($data, array('email' => FALSE));
                    if (TRUE === $verifier) {
                        if ($user_id = $user_model->register($data)) {
                            $this->prompt('success', '注册成功，即将跳转', $jump_url, 3);
                        } else {
                            $this->prompt('error', '注册失败!请稍后重试', url(array('c'=>'mobile/user', 'a'=>'login', 'step' => '4')));
                        }
                    } else {
                        $this->prompt("error", $verifier[0], url(array('c'=>'mobile/user', 'a'=>'login', 'step' => '4')));
                    }
                } elseif ($type == "bind") {
                    $username = trim(request('username', '', 'post'));
                    $password = md5e(trim(request('password', '', 'post')));
                    $user_model = new user_model();
                    $user = $user_model->find_user_by_account($username, $password);
                    if (empty($user)) {
                        $this->prompt('error', "用户名或密码错误", url(array('c'=>'mobile/user', 'a'=>'login', 'step' => '4')));
                    }
                    $user_model->update(array('user_id' => $user['user_id']), array('mobile' => $_SESSION['REGISTER']['MOBILE']));
                    $_SESSION['USER']['USER_ID'] = $user['user_id'];
                    $this->prompt('success', '绑定成功，即将跳转', $jump_url, 3);
                } else {
                    $this->step = 3;
                    $this->compiler('register_by_mobile.html');
                }

                break;

            default:
                jump(url('mobile/main', '404'));
        }
    }
    
    public function action_footprint()
    {
        $this->compiler('user_footprint.html');
    }
    
    public function action_profile()
    {
        $user_id = $this->is_logined();
        $condition = array('user_id' => $user_id);
        $user_model = new user_model();
        $this->user = $user_model->find($condition);
        $profile_model = new user_profile_model();
        $this->profile = $profile_model->find($condition);
        $this->compiler('user_profile.html');
    }
    
    public function action_info()
    {
        $user_id = $this->is_logined();
        $condition = array('user_id' => $user_id);
        $this->field = request('field');
        switch($this->field)
        {
            case 'avatar':
            
                $user_model = new user_model();
                $user = $user_model->find($condition);
                $this->avatar = $user['avatar'];
                $this->title = '更换头像';
                
            break;
            
            case 'email':
                
                $user_model = new user_model();
                $user = $user_model->find($condition);
                $this->email = $user['email'];
                $this->title = '更换邮箱';
            
            break;
            
            case 'mobile':
            
                $user_model = new user_model();
                $user = $user_model->find($condition);
                $this->mobile = $user['mobile'];
                $this->title = '更换手机';
            
            break;
            
            case 'nickname':
            
                $profile_model = new user_profile_model();
                $profile = $profile_model->find($condition);
                $this->nickname = $profile['nickname'];
                $this->title = '更换昵称';
                
            break;
            
            case 'gender':
            
                $profile_model = new user_profile_model();
                $profile = $profile_model->find($condition);
                $this->gender = $profile['gender'];
                $this->title = '更换性别';
                
            break;
            
            case 'qq':
            
                $profile_model = new user_profile_model();
                $profile = $profile_model->find($condition);
                $this->qq = $profile['qq'];
                $this->title = '更换QQ';
                
            break;
            
            case 'birthdate':
                
                include(VIEW_DIR.DS.'function'.DS.'html_date_options.php');
                $profile_model = new user_profile_model();
                $this->birthdate = $profile_model->find($condition, null, 'birth_year, birth_month, birth_day');
                $this->title = '更换生日';
                
            break;
            
            case 'signature':
            
                $profile_model = new user_profile_model();
                $profile = $profile_model->find($condition);
                $this->signature = $profile['signature'];
                $this->title = '更换个性签名';
                
            break;
            
            default: jump(url('mobile/main', '404'));
        }
        $this->compiler('user_info.html');
    }
    
    public function action_logout()
    {   
        $user_model = new user_model();
        $user_model->logout();
        jump(url('mobile/user', 'login'));
    }

    public function action_recharge()
    {
        $user_id = $this->is_logined();
        if (!isset($_POST['recharge_money']) || $_POST['recharge_money'] <= 0) {
            $user_account_model = new user_account_model();
            $user_account = $user_account_model->get_user_account($user_id);
            $this->balance = $user_account['balance'] * 1;
            $this->compiler("recharge.html");
        } else {
            $order_model = new order_model();
            //检查付款方式
            $payment_id = (int)request('payment_id', 0);
            $payment_map = vcache::instance()->payment_method_model('indexed_list');
            if(!isset($payment_map[$payment_id]))
            {
                $payment_id = current($payment_map);
                $payment_id = $payment_id['id'];
            }
            $data = array
            (
                'order_id' => $order_model->create_order_id(),
                'user_id' => $user_id,
                'shipping_method' => 0,
                'payment_method' => $payment_id,
                'goods_amount' => $_POST['recharge_money'],
                'shipping_amount' => 0,
                'order_amount' => $_POST['recharge_money'],
                'order_type' => $order_model::ORDER_TYPE_RECHARGE,
                'created_date' => $_SERVER['REQUEST_TIME'],
                'order_status' => 1,
            );

            if($order_model->create($data))
            {
                $order_goods_model = new order_goods_model();
                $goods = array
                (
                    'order_id' => $data['order_id'],
                    'goods_id' => 0,
                    'goods_name' => "余额充值",
                    'goods_image' => "recharge.png",
                    'goods_price' => $_POST['recharge_money'],
                    'goods_qty' => 1,
                );
                $order_goods_model->create($goods);
                jump(url('mobile/pay', 'index', array('order_id' => $data['order_id'])));
            }
            else
            {
                $this->prompt('error', '创建订单失败，请稍后重试');
            }
        }
    }
}
