<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/10
 * Time: 下午1:49
 */

namespace app\model;

use verifier;
use PDO;

class model
{
    public $page;
    public $table_name;
    protected $sql = array();

    public function __construct($table_name = null)
    {
        $this->table_name = $GLOBALS['mysql']['MYSQL_DB_TABLE_PRE']. ($table_name ? $table_name : $this->table_name);
    }

    public function find_all($conditions = array(), $sort = null, $fields = '*', $limit = null)
    {
        $sort = !empty($sort) ? ' ORDER BY '.$sort : '';
        $conditions = $this->_where($conditions);

        $sql = ' FROM '.$this->table_name.$conditions["_where"];
        $total = $this->query('SELECT COUNT(*) as M_COUNTER '.$sql, $conditions["_bindParams"]);
        if($total[0]['M_COUNTER'] > 0)
        {
            $limit = $this->set_limit($limit, $total[0]['M_COUNTER']);
            return $this->query('SELECT '. $fields . $sql . $sort . $limit, $conditions["_bindParams"]);
        }
        return null;
    }

    public function find($conditions = array(), $sort = null, $fields = '*')
    {
        $conditions = $this->_where($conditions);
        $sql = ' FROM '.$this->table_name.$conditions["_where"];
        $sort = !empty($sort) ? ' ORDER BY '.$sort : '';
        $res = $this->query('SELECT '. $fields . $sql . $sort . ' LIMIT 1', $conditions["_bindParams"]);
        return !empty($res) ? array_pop($res) : false;
    }

    public function update($conditions, $row)
    {
        $values = array();
        foreach($row as $k => $v)
        {
            $values[":M_UPDATE_".$k] = $v;
            $setstr[] = "`{$k}` = ".":M_UPDATE_".$k;
        }
        $conditions = $this->_where( $conditions );
        return $this->execute("UPDATE ".$this->table_name." SET ".implode(', ', $setstr).$conditions["_where"], $conditions["_bindParams"] + $values);
    }

    public function incr($conditions, $field, $optval = 1)
    {
        $conditions = $this->_where( $conditions );
        return $this->execute("UPDATE ".$this->table_name." SET `{$field}` = `{$field}` + :M_INCR_VAL ".$conditions["_where"], $conditions["_bindParams"] + array(":M_INCR_VAL" => $optval));
    }

    public function decr($conditions, $field, $optval = 1){return $this->incr($conditions, $field, - $optval);}

    public function delete($conditions)
    {
        $conditions = $this->_where( $conditions );
        return $this->execute("DELETE FROM ".$this->table_name.$conditions["_where"], $conditions["_bindParams"]);
    }

    public function create($row, $return_field = null)
    {
        $values = array();
        foreach($row as $k => $v)
        {
            $keys[] = "`{$k}`";
            $values[":".$k] = $v;
            $marks[] = ":".$k;
        }
        $this->execute("INSERT INTO ".$this->table_name." (".implode(', ', $keys).") VALUES (".implode(', ', $marks).")", $values);
        return $this->db_instance($GLOBALS['mysql'], 'master')->lastInsertId($return_field);
    }

    public function find_count($conditions = array())
    {
        $conditions = $this->_where( $conditions );
        $count = $this->query("SELECT COUNT(*) AS M_COUNTER FROM ".$this->table_name.$conditions["_where"], $conditions["_bindParams"]);
        return $count[0]['M_COUNTER'];
    }

    public function dump_sql(){return $this->sql;}

    public function pager($page, $pernum = 10, $scope = 10, $total)
    {
        $this->page = null;
        if($total > $pernum)
        {
            $total_page = ceil($total / $pernum);
            $page = min(intval(max($page, 1)), $total);
            $this->page = array
            (
                'total_count' => $total,
                'page_size'   => $pernum,
                'total_page'  => $total_page,
                'first_page'  => 1,
                'prev_page'   => ( ( 1 == $page ) ? 1 : ($page - 1) ),
                'next_page'   => ( ( $page == $total_page ) ? $total_page : ($page + 1)),
                'last_page'   => $total_page,
                'current_page'=> $page,
                'all_pages'   => array(),
                'scope'       => $scope,
                'offset'      => ($page - 1) * $pernum,
                'limit'       => $pernum,
            );
            $scope = (int)$scope;
            if($total_page <= $scope)
            {
                $this->page['all_pages'] = range(1, $total_page);
            }
            elseif($page <= $scope/2)
            {
                $this->page['all_pages'] = range(1, $scope);
            }
            elseif($page <= $total_page - $scope/2)
            {
                $right = $page + (int)($scope/2);
                $this->page['all_pages'] = range($right-$scope+1, $right);
            }
            else
            {
                $this->page['all_pages'] = range($total_page-$scope+1, $total_page);
            }
        }
        return $this->page;
    }

    public function start_transaction()
    {
        $this->query("START TRANSACTION");
    }

    public function commit()
    {
        $this->query("COMMIT");
    }

    public function roll_back()
    {
        $this->query("ROLLBACK");
    }

    public function set_limit($limit = null, $total)
    {
        if(is_array($limit))
        {
            $limit = $limit + array(1, 10, 10);
            foreach($limit as &$v) $v = (int)$v;
            $this->pager($limit[0], $limit[1], $limit[2], $total);
            return empty($this->page) ? '' : " LIMIT {$this->page['offset']},{$this->page['limit']}";
        }
        return $limit ? ' LIMIT '.$limit : '';
    }

    public function query($sql, $params = array()){return $this->execute($sql, $params, true);}

    public function execute($sql, $params = array(), $readonly = FALSE)
    {
        $this->sql[] = $sql;
        if($readonly && !empty($GLOBALS['mysql']['MYSQL_SLAVE']))
        {
            $slave_key = array_rand($GLOBALS['mysql']['MYSQL_SLAVE']);
            $sth = $this->db_instance($GLOBALS['mysql']['MYSQL_SLAVE'][$slave_key], 'slave_'.$slave_key)->prepare($sql);
        }
        else
        {
            $sth = $this->db_instance($GLOBALS['mysql'], 'master')->prepare($sql);
        }

        if(is_array($params) && !empty($params))
        {
            foreach($params as $k=>&$v) $sth->bindParam($k, $v);
        }
        if($sth->execute()) return $readonly ? $sth->fetchAll(PDO::FETCH_ASSOC) : $sth->rowCount();
        $err = $sth->errorInfo();
        err('Database SQL: "' . $sql. '", ErrorInfo: '. $err[2], 1);
    }

    public function db_instance($db_config, $db_config_key, $force_replace = FALSE)
    {
        if($force_replace || empty($GLOBALS['instance']['mysql'][$db_config_key]))
        {
            try{
                $GLOBALS['instance']['mysql'][$db_config_key] = new PDO('mysql:dbname='.$db_config['MYSQL_DB'].';host='.$db_config['MYSQL_HOST'].';port='.$db_config['MYSQL_PORT'], $db_config['MYSQL_USER'], $db_config['MYSQL_PASS'], array(PDO::MYSQL_ATTR_INIT_COMMAND=>'SET NAMES \''.$db_config['MYSQL_CHARSET'].'\''));
            }catch(\PDOException $e){err('Database Err: '.$e->getMessage());}
        }
        return $GLOBALS['instance']['mysql'][$db_config_key];
    }

    private function _where($conditions)
    {
        $result = array( "_where" => " ","_bindParams" => array());
        if(is_array($conditions) && !empty($conditions))
        {
            $fieldss = array(); $sql = null; $join = array();
            if(isset($conditions[0]) && $sql = $conditions[0]) unset($conditions[0]);
            foreach($conditions as $key => $condition)
            {
                if(substr($key, 0, 1) != ":")
                {
                    unset($conditions[$key]);
                    $conditions[":".$key] = $condition;
                }
                $join[] = "`{$key}` = :{$key}";
            }
            if(!$sql) $sql = join(" AND ",$join);

            $result["_where"] = " WHERE ". $sql;
            $result["_bindParams"] = $conditions;
        }
        return $result;
    }

    public function verifier($data, $slices = array())
    {
        if(!isset($this->rules)) $this->rules = array();
        if(!empty($this->addrules))
        {
            foreach($this->addrules as $k => $v)
            {
                foreach($v as $kk => $vv)
                {
                    $add = array($kk => array($this->$kk(isset($data[$k])? $data[$k] : null), $vv));
                    if(isset($this->rules[$k])) $this->rules[$k] = $this->rules[$k] + $add; else $this->rules[$k] = $add;
                }
            }
        }

        if(!empty($this->rules))
        {
            $verifier = new verifier($data, $this->rules);
            if(!empty($slices)) $verifier->rules_slices($slices);
            return $verifier->checking();
        }
        return array('Undefined validation rules');
    }
}