<?php
/**
 * 接收HTTP变量
 */
function request($name, $default = FALSE, $method = 'request')
{
    switch ($method) {
        case 'get':
            $value = isset($_GET[$name]) ? $_GET[$name] : FALSE;
            break;
        case 'post':
            $value = isset($_POST[$name]) ? $_POST[$name] : FALSE;
            break;
        case 'cookie':
            $value = isset($_COOKIE[$name]) ? $_COOKIE[$name] : FALSE;
            break;
        case 'request':
            $value = isset($_REQUEST[$name]) ? $_REQUEST[$name] : FALSE;
            break;
    }
    if (FALSE === $value) return $default;
    return $value;
}

/**
 * 页面跳转
 */
function jump($url, $delay = 0)
{
    echo "<html><head><meta http-equiv='refresh' content='{$delay};url={$url}'></head><body></body></html>";
    exit;
}

/**
 * 数据加密
 */
function vencrypt($val, $timed = FALSE, $key = null)
{
    if (is_string($val) && strlen($val) > 0) {
        $key = empty($key) ? sha1($GLOBALS['cfg']['encrypt_key']) : sha1($key);
        $val = base64_encode($val) . ($timed ? $_SERVER['REQUEST_TIME'] : '');
        $key = str_pad($key, strlen($val), $key, STR_PAD_RIGHT);
        $key_arr = str_split($key);
        $en = '';
        foreach (str_split($val) as $k => $v) $en .= str_pad(ord($v) + ord($key_arr[$k]), 3, 0, STR_PAD_LEFT);
        if (ord($key) % 2) $en = strrev($en);
        return $en;
    }
    return FALSE;
}

/**
 * 数据解密
 */
function vdecrypt($val, $expires = FALSE, $key = null)
{
    if (is_string($val) && strlen($val) > 0) {
        $key = empty($key) ? sha1($GLOBALS['cfg']['encrypt_key']) : sha1($key);
        if (ord($key) % 2) $val = strrev($val);
        $val_arr = str_split($val, 3);
        $key_arr = str_split(str_pad($key, count($val_arr), $key, STR_PAD_RIGHT));
        $de = '';
        foreach ($val_arr as $k => $v) $de .= chr($v - ord($key_arr[$k]));
        if ($expires) {
            if (((int)substr($de, -10) + (int)$expires) - $_SERVER['REQUEST_TIME'] <= 0) return FALSE;
            $de = substr($de, 0, strlen($de) - 10);
        }
        return base64_decode($de);
    }
    return FALSE;
}

function md5e($string, $salt = 'Verydows')
{
    return md5($string . $salt);
}

if (!function_exists('array_column')) {
    function array_column(array $input, $column_key = null, $index_key = null)
    {
        $results = array();
        foreach ($input as $item) {
            if (!is_array($item)) continue;
            if (is_null($column_key)) $value = $item; else $value = $item[$column_key];
            if (!is_null($index_key)) {
                $key = $item[$index_key];
                $results[$key] = $value;
            } else {
                $results[] = $value;
            }
        }
        return $results;
    }
}
/**
 * 随机字符
 */
function random_chars($length = 20, $is_numeric = FALSE)
{
    $hex = base_convert(md5(microtime() . str_shuffle($GLOBALS['cfg']['encrypt_key'])), 16, $is_numeric ? 10 : 35);
    $hex = $is_numeric ? (str_replace('0', '', $hex) . '012340567890') : ($hex . 'zZ' . strtoupper($hex));
    $random = '';
    if (!$is_numeric) {
        $random = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
        $length--;
    }
    for ($i = 0; $i < $length; $i++) $random .= $hex{mt_rand(0, strlen($hex) - 1)};
    return $random;
}

/**
 * 获取用户ip地址
 */
function get_ip()
{
    $ip = '0.0.0.0';
    $client = isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : null;
    $forward = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : null;
    $remote = $_SERVER['REMOTE_ADDR'];
    if (filter_var($client, FILTER_VALIDATE_IP)) $ip = $client;
    elseif (filter_var($forward, FILTER_VALIDATE_IP)) $ip = $forward;
    else $ip = $remote;
    return $ip;
}

/**
 * 字节转换成具体单位
 */
function bytes_to_size($size, $unit = 'B', $decimals = 2, $target_unit = 'auto')
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $the_unit = array_search(strtoupper($unit), $units);
    if ($target_unit != 'auto')
        $target_unit = array_search(strtoupper($target_unit), $units);
    while ($size >= 1024) {
        $size /= 1024;
        $the_unit++;
        if ($the_unit == $target_unit) break;
    }
    return sprintf("%1\$.{$decimals}f", $size) . ' ' . $units[$the_unit];
}

/**
 * 具体单位转换成字节
 */
function size_to_bytes($str = '')
{
    if (empty($str)) return 0;
    $str = strtoupper(str_replace(' ', '', $str));
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $unit = preg_replace('/[^A-Z]/', '', $str);
    $size = preg_replace('/[^0-9.]/', '', $str);
    $target_unit = array_search($unit, $units);
    $target_unit = empty($target_unit) ? 0 : $target_unit;
    return round($size * pow(1024, $target_unit));
}

/**
 * 数组分页
 */
function array_paging($data, $page_no = 1, $per_qty = 10, $scope = 10)
{
    $start = ($page_no - 1) * $per_qty;
    $end = $start + $per_qty;
    $count = count($data);
    $results = array('slice' => null, 'pagination' => null);
    if ($start < 0 || $count <= $start) return $results;
    if ($count <= $end) $results['slice'] = array_slice($data, $start);
    else $results['slice'] = array_slice($data, $start, $end - $start);
    if ($count > $per_qty) {
        $total_page = ceil($count / $per_qty);
        $page_no = min(intval(max($page_no, 1)), $count);
        $pagination = array
        (
            'total_count' => $count,
            'page_size' => $per_qty,
            'total_page' => $total_page,
            'first_page' => 1,
            'prev_page' => ((1 == $page_no) ? 1 : ($page_no - 1)),
            'next_page' => (($page_no == $total_page) ? $total_page : ($page_no + 1)),
            'last_page' => $total_page,
            'current_page' => $page_no,
            'all_pages' => array(),
            'scope' => $scope,
            'offset' => ($page_no - 1) * $per_qty,
            'limit' => $per_qty,
        );
        if ($total_page <= $scope) $pagination['all_pages'] = range(1, $total_page);
        else if ($page_no <= $scope / 2) $pagination['all_pages'] = range(1, $scope);
        else if ($page_no <= $total_page - $scope / 2) $pagination['all_pages'] = range(($page_no + intval($scope / 2)) - $scope + 1, $page_no + intval($scope / 2));
        else $pagination['all_pages'] = range($total_page - $scope + 1, $total_page);
        $results['pagination'] = $pagination;
    }
    return $results;
}

/**
 * 随机取出数组单元
 */
function array_range(array $input, $num = 1)
{
    if (count($input) >= $num) return array_rand($input, $num);
    return $input;
}

/**
 * 计算n个数组的交集
 */
function mult_array_intersect($arrays)
{
    $count = count($arrays);
    if ($count >= 2) {
        $array_tmp = $arrays[0];
        for ($i = 1; $i < $count; $i++) $array_tmp = array_intersect($array_tmp, $arrays[$i]);
        return $array_tmp;
    }
    return FALSE;
}

/**
 * Sql过滤
 */
function sql_escape($val)
{
    return preg_replace('/select|inert|update|delete|\'|\/\*|\*|\.\.\/|\.\/|union|into|load_file|outfile/i', '', $val);
}

/**
 * 32位系统上处理较大的数字
 * @return string $res
 */
function bigintstr($val)
{
    return ($res = preg_replace('/[^\-\d]*(\-?\d*).*/', '$1', $val)) ? $res : '0';
}

/**
 * 客户端是否是移动设备
 */
function is_mobile_device()
{
    if (!empty($_COOKIE['IS_MOBILE'])) return TRUE;

    $agent = $_SERVER['HTTP_USER_AGENT'];
    if (
        preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $agent)
        ||
        preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($agent, 0, 4))) {
        setcookie('IS_MOBILE', 1, null, '/');
        return TRUE;
    }
    return FALSE;
}

/**
 * 返回基准URL
 */
function baseurl()
{
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 1 || $_SERVER['HTTPS'] === 'on' || $_SERVER['SERVER_PORT'] == 443)) {
        $protocol = 'https://';
    } else {
        $protocol = 'http://';
    }
    return $protocol . dirname($_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);
}

/**
 * 二维数组根据字段进行排序
 * @params array $array 需要排序的数组
 * @params string $field 排序的字段
 * @params string $sort 排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
 * return array
 */
function arraySequence($array, $field, $sort = 'SORT_ASC')
{
    if (empty($array) || !is_array($array)) {
        return [];
    }
    $arrSort = array();
    foreach ($array as $uniqid => $row) {
        foreach ($row as $key => $value) {
            $arrSort[$key][$uniqid] = $value;
        }
    }
    array_multisort($arrSort[$field], constant($sort), $array);
    return $array;
}

function curl_post_ssl($url, $postData, $second = 30, $aHeader = array(), $ssl_config = array())
{
    $ch = curl_init();
    //超时时间
    curl_setopt($ch, CURLOPT_TIMEOUT, $second);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //这里设置代理，如果有的话
    //curl_setopt($ch,CURLOPT_PROXY, '10.206.30.98');
    //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if (count($aHeader) >= 1) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
    }

    if (count($ssl_config) == 2) {
        curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLCERT, $ssl_config['cert_path']);
        //默认格式为PEM，可以注释
        curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLKEY, $ssl_config['key_path']);
    }
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $data = curl_exec($ch);
    if ($data) {
        curl_close($ch);
        return $data;
    } else {
        $error = curl_errno($ch);
        echo "call faild, errorCode:$error\n";
        curl_close($ch);
        return false;
    }
}

/**
 * 将一个数组转换为 XML 结构的字符串
 * @param array $arr 要转换的数组
 * @param int $level 节点层级, 1 为 Root.
 * @return string XML 结构的字符串
 */
function array2xml($arr, $level = 1)
{
    $s = $level == 1 ? "<xml>" : '';
    foreach ($arr as $tagname => $value) {
        if (is_numeric($tagname)) {
            $tagname = $value['TagName'];
            unset($value['TagName']);
        }
        if (!is_array($value)) {
            $s .= "<{$tagname}>" . (!is_numeric($value) ? '<![CDATA[' : '') . $value . (!is_numeric($value) ? ']]>' : '') . "</{$tagname}>";
        } else {
            $s .= "<{$tagname}>" . $this->array2xml($value, $level + 1) . "</{$tagname}>";
        }
    }
    $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
    return $level == 1 ? $s . "</xml>" : $s;
}

/**
 * 将xml转为array
 * @param  string $xml xml字符串
 * @return array    转换得到的数组
 */
function xml2array($xml)
{
    //禁止引用外部xml实体
    libxml_disable_entity_loader(true);
    $result = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    return $result;
}

/**
 *
 * 产生随机字符串，不长于32位
 * @param int $length
 * @return $str 产生的随机字符串
 */
function getNonceStr($length = 32)
{
    $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
    $str = "";
    for ($i = 0; $i < $length; $i++) {
        $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
}

function is_weixin_browser()
{
    if( !preg_match('/micromessenger/i', strtolower($_SERVER['HTTP_USER_AGENT'])) ) {
        return false;
    } else {
        return true;
    }
}

/**
 * 转换时间字符串为几天前的函数
 * @param $the_time
 * @return false|string
 */
function time_tran($the_time)
{
    date_default_timezone_set("Asia/Shanghai");   //设置时区
    $now_time = date("Y-m-d H:i:s", time());
    //echo $now_time;
    $now_time = strtotime($now_time);
    $show_time = strtotime($the_time);
    $dur = $now_time - $show_time;
    if ($dur < 0) {
        $res_time = strtotime($the_time);
        return date('m-d', $res_time);
    } else {
        if ($dur < 60) {
            return $dur . '秒前';
        } else {
            if ($dur < 3600) {
                return floor($dur / 60) . '分钟前';
            } else {
                if ($dur < 86400) {
                    return floor($dur / 3600) . '小时前';
                } else {
                    if ($dur < 259200) {//3天内
                        return floor($dur / 86400) . '天前';
                    } else {
                        $res_time = strtotime($the_time);
                        return date('Y年m月d日', $res_time);
                    }
                }
            }
        }
    }
}
