<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/12
 * Time: 下午10:27
 */

namespace app\model;

use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;

class qiniu_model
{
    /**
     * 获取上传token
     * @return string 上传凭证
     */
    public function get_up_token()
    {
        $QiNiu = $GLOBALS['qi_niu'];
        $auth = new Auth($QiNiu['access_key'], $QiNiu['secret_key']);
        $bucket = $QiNiu['bucket'];
        $policy = array(
            'mimeLimit' => 'image/*',
        );
        $headers = array();
        $headers[] = 'Content-Type:image/png';

        return $auth->uploadToken($bucket, null, 3600, $policy);
    }

    /**
     * 通过base64上传
     * @param $base64
     * @return mixed
     * @throws \Exception
     */
    public function put64($base64)
    {
        $headers = array();
        $upToken = $this->get_up_token();
        $headers[] = 'Content-Type:image/png';
        $headers[] = 'Authorization:UpToken ' . $upToken;
        $response = json_decode(curl_post_ssl(
            'http://upload.qiniu.com/putb64/-1', $base64, 30, $headers), true);
        if (!isset($response['key'])) {
            throw new \Exception($response['error']);
        }

        return $response['key'];
    }

    public function upload($input_name)
    {
        $token = $this->get_up_token();
        $uploadManager = new UploadManager();
        $name = md5($_FILES[$input_name]['name'] . time());
        $filePath = $_FILES[$input_name]['tmp_name'];
        $type = $_FILES[$input_name]['type'];
        try {
            list($ret, $err) = $uploadManager->putFile($token, $name, $filePath, null, $type, false);
            if (!$err) {
                return $ret['key'];
            } else {
                var_dump($err);
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            return null;
        }
    }
}