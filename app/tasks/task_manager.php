<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/11
 * Time: 下午7:17
 */
namespace tasks;

define('APP_DIR', __DIR__ . "/..");
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
$run_mode = get_cfg_var("erhuo.runmode") ? get_cfg_var("erhuo.runmode") : "qatest";
$conf_dir = "conf".DS.$run_mode;
$GLOBALS = require(APP_DIR.DS.'protected'.DS."conf".DS.'common.php');
$GLOBALS['mysql'] = require(APP_DIR.DS.'protected'.DS.$conf_dir.DS.'connection'.DS.'mysql.php');
$GLOBALS['cfg'] = require(APP_DIR.DS.'protected'.DS.$conf_dir.DS.'setting.php');
require(APP_DIR . '/vendor/autoload.php');

class task_manager
{
    private $db_connect;

    public function __construct()
    {
        $this->connect_to_db();
    }

    protected function connect_to_db()
    {
        if ($this->db_connect) {
            return ;
        }
        $config = $GLOBALS['mysql'];
        try {
            $this->db_connect = new \PDO("mysql:host={$config['MYSQL_HOST']};dbname={$config['MYSQL_DB']}",
                $config['MYSQL_USER'], $config['MYSQL_PASS']);
        } catch (\PDOException $e) {
            die($e->getMessage());
        }
    }

    public function get_db_connect()
    {
        return $this->db_connect;
    }

    /**
     * @var array
     */
    private $tasks = array();

    /**
     * 添加任务
     * @param task $task
     * @return $this
     * @throws \Exception
     */
    public function add_task(task $task)
    {
        if ($task instanceof task === false) {
            throw new \Exception('$task must be instance of task');
        }
        if (!in_array($task, $this->tasks)) {
            $this->tasks[] = $task;
        }

        return $this;
    }

    public function run_all()
    {
        if (empty($this->tasks)) {
            echo "task 数量为 0，请添加task\n";
            return;
        }
        foreach ($this->tasks as $task) {
            $this->run($task);
        }
        $this->output_statistics();
    }

    public function output_statistics()
    {
        self::console_log("全部任务执行完成！");
    }

    public function run(task $task)
    {
        self::console_log(str_pad("开始".$task->get_task_name(), 50, "-", STR_PAD_BOTH));
        $task->run();
        self::console_log(str_pad("结束".$task->get_task_name(), 50, "-",STR_PAD_BOTH));
    }

    protected static function console_log($content)
    {
        echo "\n" . $content . "\n";
    }
}

$task_manager = new task_manager();
$dbh = $task_manager->get_db_connect();
try {
    if (isset($argv[1])) {
        /**
         * @var task $task
         */
        $task = "\\tasks\\" . $argv[1];
        $task = (new $task($dbh));
        $task_manager->add_task($task)->run_all();
    } else {
        $task_manager->add_task(new update_sales_volume_task($dbh))
            ->add_task(new auto_open_reserve($dbh))
            ->add_task(new order_delivery_expires($dbh))
            ->run_all();
    }
} catch (\Exception $e) {
    echo $e->getMessage();
}
