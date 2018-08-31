<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/4
 * Time: 下午1:32
 */

namespace app\model;
use \Firebase\JWT\JWT;

class token_model
{
    /**
     * 生成token
     * @param $encrypt_data
     * @return string
     */
    public function generate($encrypt_data)
    {
        /*
         * Application setup, database connection, data sanitization and user
         * validation routines are here.
         */
        $bytes = $this->random_token(32);
        $tokenId = base64_encode($bytes);
        $issuedAt = time();
        $notBefore = $issuedAt;
        $expire = $notBefore + 3600 * 30;            // Adding 30个小时
        $serverName = $GLOBALS['cfg']['http_host']; // Retrieve the server name from config file

        /*
         * Create the token as an array
         */
        $data = [
            'iat' => $issuedAt,         // Issued at: time when the token was generated
            'jti' => $tokenId,          // Json Token Id: an unique identifier for the token
            'iss' => $serverName,       // Issuer
            'nbf' => $notBefore,        // Not before
            'exp' => $expire,           // Expire
            'data' => $encrypt_data                 // Data related to the signer user
        ];
        /*
         * Extract the key, which is coming from the config file.
         *
         * Best suggestion is the key to be a binary string and
         * store it in encoded in a config file.
         *
         * Can be generated with base64_encode(openssl_random_pseudo_bytes(64));
         *
         * keep it secure! You'll need the exact key to verify the
         * token later.
         */
        $secretKey = $GLOBALS['cfg']['encrypt_key'];

        /*
         * Encode the array to a JWT string.
         * Second parameter is the key to encode the token.
         *
         * The output string can be validated at http://jwt.io/
         */
        $jwt = JWT::encode(
            $data,      //Data to be encoded in the JWT
            $secretKey, // The signing key
            'HS512'     // Algorithm used to sign the token, see https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40#section-3
        );

        return $jwt;
    }

    /**
     * 解析jwt中的数据
     * @param $jwt
     * @return array|bool
     */
    public function resolve($jwt)
    {
        /*
         * decode the jwt using the key from config
         */
        $secretKey = $GLOBALS['cfg']['encrypt_key'];
        try {
            $token = JWT::decode($jwt, $secretKey, array('HS512'));
            if ($token && isset($token->data)) {
                return $this->object_array($token->data);
            }
        } catch (Exception $e) {

        }
        return false;
    }

    private function random_token($length = 32)
    {
        if (!isset($length) || intval($length) <= 8) {
            $length = 32;
        }
        if (function_exists('random_bytes')) {
            return random_bytes($length);
        }
        if (function_exists('mcrypt_create_iv')) {
            return mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            return openssl_random_pseudo_bytes($length);
        }
    }

    private function object_array($array)
    {
        if (is_object($array)) {
            $array = (array)$array;
        }
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $array[$key] = $this->object_array($value);
            }
        }
        return $array;
    }
}