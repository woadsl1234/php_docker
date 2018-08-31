<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/11
 * Time: 下午7:20
 */
namespace tasks;

abstract class task
{
    /**
     * @var \PDO
     */
    protected $dbh;

    protected $task_name = "";

    public function __construct(\PDO $dbh)
    {
        $this->dbh = $dbh;
    }

    public function run(){}

    public function get_task_name()
    {
        return $this->task_name;
    }
}