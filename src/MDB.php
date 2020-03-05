<?php

namespace Mpdo;

use stdClass;

/**
 * MDB.php
 * by Joylton Maciel at November 8th, 2020.
 * 
 * DabaBase connection and manipulation.
 * 
 */

class MDB
{
    protected $env;

    public function __construct($dbname)
    {
        if (empty($dbname)) {
            throw new \Exception("Informe o nome do banco de Dados.");
        }

        $env = self::getDotEnvData();
        try {
            $conn = $env->DB_DRIVER
                . ":host=" . $env->DB_HOST
                . ";dbname=" . $dbname;
            $this->connection = new \PDO(
                $conn,
                $env->DB_USER,
                $env->DB_PASS
            );
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function insert($dados, $auditoria = '', $formulario = '')
    {
        $this->connection->beginTransaction();
        // $idList = [];

        foreach ($dados as $table => $records) {

            // compose the sql statement
            $fields = '';
            $values = '';
            foreach ($records as $campo => $content) {
                if (!empty($fields)) {
                    $fields .= ', ';
                    $values .= ', ';
                }
                $fields .= $campo;
                $values .= ':' . $campo;
                $assoc_array[$campo] = $content;
            }

            // prepare and execute the statement
            $sql = 'INSERT INTO ' . $table . ' (' . $fields . ') VALUES (' . $values . ')';
            $conn = $this->connection->prepare($sql);
            foreach ($assoc_array as $campo => $content) {
                $conn->bindValue(':' . $campo, $content);
            }
            $conn->execute();
            // $idList[] = $this->connection->lastInsertId('stocks_id_seq');
        }

        // Registra o acontecimento na auditoria
        // if (empty($auditoria)) {
        //     $auditoria = "Novo registro.";
        // }
        // if (empty($formulario)) {
        //     $formulario = $_SESSION['PARAMETERS']->form->viewtitle;
        // }

        // Auditoria::save($this->connection, $auditoria, $formulario);

        $this->connection->commit();
        // return $idList;

    }

    public function update($dados, $auditoria = '', $formulario = '')
    {
        // command
        $sql = 'UPDATE ';

        // table name
        if (!isset($this->table)) throw new \Exception("SQL: Tabela indefinida.");
        $sql .= str_replace(' FROM ', '', $this->table);

        // command
        $sql .= ' SET ';

        // monta 
        foreach ($dados as $campo => $content) {
            if (!isset($update)) {
                $update = $campo . " = :" . $campo;
            } else {
                $update .= ", " . $campo . " = :" . $campo;
            }
            if (!isset($this->params[$campo])) {
                $this->params[$campo] = $content;
            }
        }

        // command
        $sql .= $update;

        // conditions
        if (isset($this->where)) $sql .= $this->where;

        // prepare and execute the statment
        $conn = $this->connection->prepare($sql);
        $conn->execute($this->params);

        // echo "<pre>";
        // echo "SQL: $sql \n";
        // echo "WHERE: " . $this->where . "\n";
        // print_r($dados);
        // print_r($this->params);
        // die;

        if ($conn->rowCount() > 0) {
            return $conn->rowCount();
        } else {
            throw new \Exception("Erro ao alterar registro.");
        }

        // show the SQL command on screen
        // $this->showDebug($result);

        // Registra o acontecimento na // Auditoria
        // if (empty($auditoria)) {
        //     $auditoria = "Registro alterado.";
        // }
        // if (empty($formulario)) {
        //     $formulario = $_SESSION['PARAMETERS']->form->viewtitle;
        // }
        // Auditoria::save($this->connection, $auditoria, $formulario);
    }

    public function delete()
    {
        // command
        $sql = 'DELETE';

        // table name
        if (!isset($this->table)) throw new \Exception("SQL: Tabela indefinida.");
        $sql .= $this->table;

        // condition
        if (isset($this->where)) $sql .= $this->where;

        // prepare and execute the statment
        $conn = $this->connection->prepare($sql);
        $conn->execute($this->params);

        if ($conn->rowCount() <= 0) {
            throw new \Exception("Erro ao excluir registro.");
        }

        // Registra o acontecimento na auditoria
        // if (empty($auditoria)) {
        //     $auditoria = "Registro excluido.";
        // }
        // if (empty($formulario)) {
        //     $formulario = $_SESSION['PARAMETERS']->form->viewtitle;
        // }
        // Auditoria::save($this->connection, $auditoria, $formulario);
    }

    public function select($select = '*')
    {
        if (!isset($this->select)) {
            $this->select = $select;
        } else {
            $this->select .= ', ' . $select;
        }
        return $this;
    }

    public function all()
    {
        // ToDo Here
        return $this;
    }

    public function table($table)
    {
        if (!isset($this->table)) {
            $this->table = ' FROM ' . $table;
        } else {
            $this->table .= ', ' . $table;
        }
        return $this;
    }

    // ->join('unidades', 'unidades_config.unidadeid', '=', 'unidades.unidadeid')
    public function join($table, $afield, $operator, $bfield)
    {
        if (!isset($this->join)) {
            $this->join = ' join ' . $table . ' on ' . $afield . ' ' . $operator . ' ' . $bfield;
        } else {
            $this->join .= ' join ' . $table . ' on ' . $afield . ' ' . $operator . ' ' . $bfield;
        }
        return $this;
    }

    /**
     * usage:
     * ->where('nome', 'Maria') ... nome='Maria'
     * ->where('graduated', true) ... graduated='t'
     * ->where('date', '>=', '2020-01-09') ... date>='2020-01-09'
     */
    public function where($field, $operator, $content = '')
    {
        if (is_bool($operator)) {
            if ($operator === true) {
                $operator = 't';
            } elseif ($operator === false) {
                $operator = 'f';
            }
        }

        if (is_bool($content)) {
            if ($content === true) {
                $content = 't';
            } elseif ($content === false) {
                $content = 'f';
            }
        }

        if (
            is_int($operator) ||
            is_float($operator) ||
            is_numeric($operator)
        ) {
            $operator = strval($operator);
        }

        if (
            is_int($content) ||
            is_float($content) ||
            is_numeric($operator)
        ) {
            $content = strval($content);
        }

        if (
            is_string($operator) &&
            is_string($content)
        ) {
            if (
                (strlen($operator) <= 0 && strlen($content) <= 0) ||
                (strlen($operator) <= 0 && strlen($content) >= 1)
            ) {
                throw new \Exception("Parâmetros incompletos para consulta (Field: $field Operator: $operator Content: $content).");
            } elseif (strlen($operator) >= 1 && strlen($content) <= 0) {
                $content = $operator;
                $operator = '=';
            }
        }

        if (!isset($this->where)) {
            $this->where = ' where ';
        } else {
            $this->where .= ' and ';
        }

        $this->params[str_replace('.', '__', $field)] = $content;
        $this->where .= $field . ' ' . $operator . ' ' . ':' . str_replace('.', '__', $field);
        return $this;
    }

    public function orWhere($field, $operator, $content)
    {
        if (is_bool($operator)) {
            if ($operator === true) {
                $operator = 't';
            } elseif ($operator === false) {
                $operator = 'f';
            }
        }

        if (is_bool($content)) {
            if ($content === true) {
                $content = 't';
            } elseif ($content === false) {
                $content = 'f';
            }
        }

        if (is_int($operator) || is_float($operator)) {
            $operator = strval($operator);
        }

        if (is_int($content) || is_float($content)) {
            $content = strval($content);
        }

        if (!isset($this->where)) {
            throw new \Exception("Where deve ser informado subsequentemente anterior à onWhere()");
        } else {
            $this->where .= ' or ';
        }

        $this->params[$field] = $content;
        $this->where .= $field . ' ' . $operator . ' ' . ':' . count($this->params);
        return $this;
    }

    public function orderBy($orderby)
    {
        if (!isset($this->orderby)) {
            $this->orderby = ' order by ' . $orderby;
        } else {
            $this->orderby .= ', ' . $orderby;
        }
        return $this;
    }

    public function key($key)
    {
        if (strpos($key, ':') > 0) {
            list($this->key, $this->rules) = explode(":", $key);
        } else {
            $this->key = $key;
            $this->rules = '';
        }
        return $this;
    }

    public function get($limit = 0)
    {
        // parameters for pg_query_params
        if (!isset($this->params)) $this->params = [];

        // command
        $this->sql = 'select ';

        // fields to return
        if (!isset($this->select)) $this->select();
        $this->sql .= $this->select;

        // table name
        if (!isset($this->table)) throw new \Exception("SQL: Tabela indefinida.");
        $this->sql .= $this->table;

        // join
        if (isset($this->join)) $this->sql .= $this->join;

        // condition
        if (isset($this->where)) $this->sql .= $this->where;

        // order by
        if (isset($this->orderby)) $this->sql .= $this->orderby;

        // limit
        if ($limit > 0) $this->sql .= ' limit ' . $limit;

        // prepare and execute the statment
        $result = $this->connection->prepare($this->sql);
        $result->execute($this->params);

        // show the SQL command on screen
        $this->showDebug($result);

        return $this->Result($result);
    }

    /**
     * Execute a SQL statment (command/query)
     *
     * @param string $statment: use: dbname@sql_statment or just sql_statment
     * @return array 
     */
    public function execute($sql)
    {
        $this->sql = $sql;

        // run que query (command)
        // $result = pg_query_params($this->connection, $this->sql);
        $result = $this->connection->query($this->sql);

        // show the SQL command on screen
        $this->showDebug($result);

        return $this->Result($result);
    }

    public static function isconnected($connection)
    {
        // if (pg_connection_status($connection) === PGSQL_CONNECTION_OK) {
        //     return true;
        // } else {
        //     return false;
        // }
    }

    public static function close()
    {
        // Todo here
    }

    public function Result($result = '')
    {
        if (!$result) {
            $this->cleanAll();
            return false;
        }

        $count = 0;
        $eof = true;
        $fields = new stdClass();
        foreach ($result->fetchAll() as $i => $record) {
            if (isset($this->key)) {
                if (empty($this->rules)) {
                    $fields->{$record[$this->key]} = json_decode(json_encode($record));
                } else {
                    if ($this->rules == 'onlynumbers') {
                        $fields->{self::onlyNumbers(trim($record[$this->key]))} = $record;
                    } else {
                        $fields->{trim($record[$this->key])} = $record;
                    }
                }
            } else {
                $fields = json_decode(json_encode($record));
            }
            $count++;
        }

        $this->cleanAll();
        $eof = $count > 0 ? false : true;
        return json_decode(json_encode([
            'count' => $count,
            'EOF' => $eof,
            'fields' => $fields
        ]));
    }

    public function debug($active = false)
    {
        $this->debug = $active;
        return $this;
    }

    public function showDebug($result)
    {
        if (isset($this->debug)) {
            if ($this->debug) {
                echo "<pre>";
                echo "\n<hr>";
                echo "\nDb - Dababase Manipulation Class";
                echo "\nSQL:";
                echo "\n" . $this->sql;
                echo "\nPARAMETERS:\n";
                print_r($this->params);
                echo "\nMESSAGE:\n";
                if (!$result) {
                    echo pg_last_error($this->connection);
                } else {
                    echo "OK";
                }
                echo "\n<hr>";
                echo "\n</pre>";
            }
        }
    }

    /**
     * Remove all non numeric characters from string
     * @param string $string
     * @return string
     */
    public static function onlyNumbers($string)
    {
        return preg_replace("/[^0-9]/", "", $string);
    }

    /**
     * Get .env settings
     */
    public static function getDotEnvData()
    {
        $file = __DIR__ . '/../../../../.env';
        if (file_exists($file)) {
            return json_decode(json_encode(parse_ini_file($file)));
        } else {
            throw new \Exception("No Database settings.");
        }
    }

    public function cleanAll()
    {
        if (isset($this->join)) unset($this->join);
        if (isset($this->key)) unset($this->key);
        if (isset($this->orderby)) unset($this->orderby);
        if (isset($this->params)) unset($this->params);
        if (isset($this->sql)) unset($this->sql);
        if (isset($this->select)) unset($this->select);
        if (isset($this->table)) unset($this->table);
        if (isset($this->where)) unset($this->where);
    }
}
