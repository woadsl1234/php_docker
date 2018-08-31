<?php
define('VIEW_DIR', APP_DIR.DS.'protected'.DS.'view');
$run_mode = get_cfg_var("erhuo.runmode") ? get_cfg_var("erhuo.runmode") : "qatest";
define('RUN_MODE', $run_mode);//运行环境

$conf_dir = "conf".DS.$run_mode;
$GLOBALS = require(APP_DIR.DS.'protected'.DS."conf".DS.'common.php');
$GLOBALS['run_mode'] = $run_mode;
$GLOBALS['mysql'] = require(APP_DIR.DS.'protected'.DS.$conf_dir.DS.'connection'.DS.'mysql.php');
$GLOBALS['cfg'] = require(APP_DIR.DS.'protected'.DS.$conf_dir.DS.'setting.php');

if($GLOBALS['cfg']['debug'])
{
    error_reporting(-1);
    ini_set('display_errors', 'On');
}
else
{
    error_reporting(E_ALL & ~(E_STRICT|E_NOTICE));
    ini_set('display_errors', 'Off');
    ini_set('log_errors', 'On');
}
set_error_handler('_err_handle');
require(INCL_DIR.DS.'functions.php');

if($GLOBALS['rewrite_enable'] && strpos($_SERVER['REQUEST_URI'], 'index.php?') === FALSE)
{
    if(($pos = strpos( $_SERVER['REQUEST_URI'], '?')) !== FALSE) parse_str(substr($_SERVER['REQUEST_URI'], $pos + 1), $_GET);
    foreach($GLOBALS['rewrite_rule'] as $rule => $mapper)
    {
        if('/' == $rule)$rule = '';
        if(0!==stripos($rule, 'http://')) $rule = 'http://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER["SCRIPT_NAME"]), '/\\') .'/'.$rule;
        $rule = '/'.str_ireplace(array('\\\\', 'http://', '/', '<', '>',  '.'), array('', '', '\/', '(?P<', '>\w+)', '\.'), $rule).'/i';
        if(preg_match($rule, 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], $matchs))
        {
            $route = explode('/', $mapper);
            if(isset($route[2]))
            {
                list($_GET['m'], $_GET['c'], $_GET['a']) = $route;
            }
            else
            {
                list($_GET['c'], $_GET['a']) = $route;
            }
            foreach($matchs as $matchkey => $matchval)
            {
                if(!is_int($matchkey))$_GET[$matchkey] = $matchval;
            }
            break;
        }
    }
}

$_REQUEST = array_merge($_POST, $_GET);
$__module     = request('m', '');
$__controller = request('c', 'main');
$__action     = request('a', 'index');

if(!empty($__module))
{
    if(!is_available_classname($__module)) err("Err: Module name '$__module' is not correct!");
    if(!is_dir(APP_DIR.DS.'protected'.DS.'controller'.DS.$__module))err("Err: Module '$__module' is not exists!");
}
if(!is_available_classname($__controller)) err("Err: Controller name '$__controller' is not correct!");

spl_autoload_register('inner_autoload');
function inner_autoload($class)
{
    GLOBAL $__module;
    foreach(array('model', 'lib', 'controller'.(empty($__module)?'':DS.$__module)) as $dir)
    {
        $file = APP_DIR.DS.'protected'.DS.$dir.DS.$class.'.php';
        if(is_file($file))
        {
            include $file;
            return;
        }
        $lowerfile = strtolower($file);
        foreach(glob(APP_DIR.DS.'protected'.DS.$dir.DS.'*.php') as $file)
        {
            if(strtolower($file) === $lowerfile)
            {
                include $file;
                return;
            }
        }
    }
}
session_name('VDSSKEY');
session_start();

$controller_name = $__controller.'_controller';
$action_name = 'action_'.$__action;
if(!class_exists($controller_name, true)) err("Err: Controller '$controller_name' is not exists!");
$controller_obj = new $controller_name();
if(!method_exists($controller_obj, $action_name)) err("Err: Method '$action_name' of '$controller_name' is not exists!");

$controller_obj->$action_name();

function url($c = 'main', $a = 'index', $param = array())
{
    if(is_array($c))
    {
        $param = $c;
        if(isset($param['m'])) $m = $param['m']; unset($param['m']);
        $c = $param['c']; unset($param['c']);
        $a = $param['a']; unset($param['a']);
    }
    
    $param = array_filter($param);
    $params = empty($param) ? '' : '&'. urldecode(http_build_query($param));

    if(isset($m))
    {
        $route = "$m/$c/$a";
        $url = $_SERVER["SCRIPT_NAME"]."?m=$m&c=$c&a=$a$params";
    }
    elseif(strpos($c, '/') !== false)
    {
        list($m, $c) = explode('/', $c);
        $route = "$m/$c/$a";
        $url = $_SERVER["SCRIPT_NAME"]."?m=$m&c=$c&a=$a$params";
    }
    else
    {
        $m = '';
        $route = "$c/$a";
        $url = $_SERVER["SCRIPT_NAME"]."?c=$c&a=$a$params";
    }
    
    if($GLOBALS['rewrite_enable'] && ($m == '' || $m == 'mobile' || $m == 'api'))
    {
        static $urlArray = array();
        if(!isset($urlArray[$url]))
        {
            foreach($GLOBALS['rewrite_rule'] as $rule => $mapper)
            {
                $mapper = '/'.str_ireplace(array('/', '<a>', '<c>', '<m>'), array('\/', '(?P<a>\w+)', '(?P<c>\w+)', '(?P<m>\w+)'), $mapper).'/i';
                if(preg_match($mapper, $route, $matchs))
                {
                    $urlArray[$url] = str_ireplace(array('<a>', '<c>', '<m>'), array($a, $c, $m), $rule);
                    if(!empty($param))
                    {
                        $_args = array();
                        foreach($param as $argkey => $arg)
                        {
                            $count = 0;
                            $urlArray[$url] = str_ireplace('<'.$argkey.'>', $arg, $urlArray[$url], $count);
                            if(!$count)$_args[$argkey] = $arg;
                        }
                        $urlArray[$url] = preg_replace('/<\w+>/', '', $urlArray[$url]).(!empty($_args) ? '?'.urldecode(http_build_query($_args)) : '');
                    }
					
                    $urlArray[$url] = $GLOBALS['cfg']['http_host'].'/'.$urlArray[$url];
                    $rule = str_ireplace(array('<m>', '<c>', '<a>'), '', $rule);
                    if(count($param) == preg_match_all('/<\w+>/is', $rule, $_match)) return $urlArray[$url];
                    break;
                }
            }
            return isset($urlArray[$url]) ? $urlArray[$url] : $url;
        }
        return $urlArray[$url];
    }
    return $url;
}

function dump($var, $exit = FALSE)
{
    $output = print_r($var, true);
    if(!$GLOBALS['cfg']['debug'])return error_log(str_replace("\n", '', $output));
    echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"></head><body><div align=left><pre>" .htmlspecialchars($output). "</pre></div></body></html>";
    if($exit) exit();
}

function is_available_classname($name)
{
    return preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', $name);
}

class Controller
{
    private $_v;
    private $_data = array();

    public function init()
    {
    }

    public function __construct()
    {
        $this->init();
    }

    public function __get($name)
    {
        return $this->_data[$name];
    }

    public function __set($name, $value)
    {
        $this->_data[$name] = $value;
    }

    public function display($tpl_name)
    {
        if (!$this->_v) $this->_v = new View(VIEW_DIR, APP_DIR . DS . 'protected' . DS . 'cache' . DS . 'template');
        $this->_v->assign($this->_data);
        echo $this->_v->render($tpl_name);
    }
}

function _err_handle($errno, $errstr, $errfile, $errline)
{
    if(0 === error_reporting()) return;
    $msg = "ERROR";
    switch($errno)
    {
        case E_WARNING: $msg = "WARNING"; break;
        case E_NOTICE: $msg = "NOTICE"; break;
        case E_STRICT: $msg = "STRICT"; break;
        case 8192: $msg = "DEPRECATED"; break;
        default : $msg = "Unknown Error Type";
    }
    err("$msg: $errstr in $errfile on line $errline");
}

function err($msg)
{
    $traces = debug_backtrace();
    if(!$GLOBALS['cfg']['debug'])
    {
        if(!empty($GLOBALS['err_handler']))
        {
            call_user_func($GLOBALS['err_handler'], $msg, $traces);
        }
        else
        {
            error_log($msg);
        }
    }
    else
    {
        if(ob_get_contents()) ob_end_clean();
        function _err_highlight_code($code){if(preg_match('/\<\?(php)?[^[:graph:]]/i', $code)){return highlight_string($code, TRUE);}else{return preg_replace('/(&lt;\?php&nbsp;)+/i', "", highlight_string("<?php ".$code, TRUE));}}
        function _err_getsource($file, $line){if(!(file_exists($file) && is_file($file))) {return '';}$data = file($file);$count = count($data) - 1;$start = $line - 5;if ($start < 1) {$start = 1;}$end = $line + 5;if ($end > $count) {$end = $count + 1;}$returns = array();for($i = $start; $i <= $end; $i++) {if($i == $line){$returns[] = "<div id='current'>".$i.".&nbsp;"._err_highlight_code($data[$i - 1], TRUE)."</div>";}else{$returns[] = $i.".&nbsp;"._err_highlight_code($data[$i - 1], TRUE);}}return $returns;
}?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head><meta name="robots" content="noindex, nofollow, noarchive" /><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><title><?php echo $msg;?></title><style>body{padding:0;margin:0;word-wrap:break-word;word-break:break-all;font-family:Courier,Arial,sans-serif;background:#EBF8FF;color:#5E5E5E;}div,h2,p,span{margin:0; padding:0;}ul{margin:0; padding:0; list-style-type:none;font-size:0;line-height:0;}#body{width:918px;margin:0 auto;}#main{width:918px;margin:13px auto 0 auto;padding:0 0 35px 0;}#contents{width:918px;float:left;margin:13px auto 0 auto;background:#FFF;padding:8px 0 0 9px;}#contents h2{display:block;background:#CFF0F3;font:bold 20px;padding:12px 0 12px 30px;margin:0 10px 22px 1px;}#contents ul{padding:0 0 0 18px;font-size:0;line-height:0;}#contents ul li{display:block;padding:0;color:#8F8F8F;background-color:inherit;font:normal 14px Arial, Helvetica, sans-serif;margin:0;}#contents ul li span{display:block;color:#408BAA;background-color:inherit;font:bold 14px Arial, Helvetica, sans-serif;padding:0 0 10px 0;margin:0;}#oneborder{width:800px;font:normal 14px Arial, Helvetica, sans-serif;border:#EBF3F5 solid 4px;margin:0 30px 20px 30px;padding:10px 20px;line-height:23px;}#oneborder span{padding:0;margin:0;}#oneborder #current{background:#CFF0F3;}</style></head><body><div id="main"><div id="contents"><h2><?php echo $msg?></h2><?php foreach($traces as $trace){if(is_array($trace)&&!empty($trace["file"])){$souceline = _err_getsource($trace["file"], $trace["line"]);if($souceline){?><ul><li><span><?php echo $trace["file"];?> on line <?php echo $trace["line"];?> </span></li></ul><div id="oneborder"><?php foreach($souceline as $singleline)echo $singleline;?></div><?php }}}?></div></div><div style="clear:both;padding-bottom:50px;" /></body></html><?php }
    exit;
}
