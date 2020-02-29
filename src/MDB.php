<?php

namespace Mpdo;

use stdClass;

/**
 * MDB.php
 * by Joylton Maciel at November 8th, 2020.
 * 
 * DabaBase connection and manipulation.
 * 
 * usage sample:
 * 
 * $db = Db::Open();
 * $result = (new Db)
 *    ->conn($db)
 *    ->select('name', 'email')
 *    ->table('usuarios')
 *    ->where('usuarioid', '1')
 *    ->where('usuario', 'suporte', 'like')
 *    ->debug(true)
 *    ->get();
 * print_r($result);
 */

class MDB
{
    public static function Open($database = '')
    {
        $settings = self::getDotEnvData();

        print_r($settings);

        // $settings = Config::settings($database);
        $dbname = self::dbName($database, $settings);
        if (!($db = pg_connect('host=' . $settings->DBHOST . ' '
            . 'dbname=' . $dbname . ' '
            . 'user=' . $settings->DBUSER . ' '
            . 'password=' . $settings->DBPWD))) {

            if (isset($_SERVER['HTTP_HOST'])) {
                header("Location: start.php?msg=Por favor, verifique dados de acesso.");
                exit;
            } else {
                throw new \Exception("Sem conexão com o Banco de Dados. Banco de dados inválido ou inexistente.");
            }
        }

        return $db;
    }

    public function insert($dados, $auditoria = '', $formulario = '')
    {
        // pg_query_params($this->connection, 'BEGIN');
        pg_query($this->connection, 'BEGIN');

        foreach ($dados as $table => $fields) {
            foreach ($fields as $campo => $content) {
                $assoc_array[$campo] = $content;
            }
            if (!pg_insert($this->connection, $table, $assoc_array)) {
                pg_query_params($this->connection, 'ROLLBACK');
                throw new \Exception("Erro ao gravar registro.");
            }
        }

        // Registra o acontecimento na auditoria
        if (empty($auditoria)) {
            $auditoria = "Novo registro.";
        }
        if (empty($formulario)) {
            $formulario = $_SESSION['PARAMETERS']->form->viewtitle;
        }
        // Auditoria::save($this->connection, $auditoria, $formulario);

        // pg_query_params($this->connection, 'COMMIT');
        pg_query($this->connection, 'COMMIT');
    }

    public function update($dados, $auditoria = '', $formulario = '')
    {
        // valid the connection
        if (!isset($this->connection)) throw new \Exception("SQL: Conexão indefinida.");

        // command
        $this->sql = 'update ';

        // table name
        if (!isset($this->table)) {
            throw new \Exception("SQL: Tabela indefinida.");
        } else {
            $table = str_replace(' from ', '', $this->table);
        }
        $this->sql .= $table;

        // command
        $this->sql .= ' set ';

        // monta 
        foreach ($dados as $campo => $content) {
            if (!isset($update)) {
                $update = $campo . " = '" . $content . "'";
            } else {
                $update = ", " . $campo . " = '" . $content . "'";
            }
        }

        // command
        $this->sql .= $update;

        if (isset($this->where)) $this->sql .= $this->where;

        $result = pg_query_params($this->connection, $this->sql, $this->params);

        // show the SQL command on screen
        $this->showDebug($result);

        if (!$result) {
            throw new \Exception("Erro ao gravar registro.");
        }

        // Registra o acontecimento na // Auditoria
        if (empty($auditoria)) {
            $auditoria = "Registro alterado.";
        }
        if (empty($formulario)) {
            $formulario = $_SESSION['PARAMETERS']->form->viewtitle;
        }
        // Auditoria::save($this->connection, $auditoria, $formulario);
    }

    public function delete()
    {
        // valid the connection
        if (!isset($this->connection)) throw new \Exception("SQL: Conexão indefinida.");

        // command
        $this->sql = 'delete';

        // table name
        if (!isset($this->table)) throw new \Exception("SQL: Tabela indefinida.");
        $this->sql .= $this->table;

        // condition
        if (isset($this->where)) $this->sql .= $this->where;

        // run the sql statment
        // $result = pg_query_params($this->connection, $this->sql, $this->params);
        // $result = pg_query($this->connection, "delete from compart_to where recebid='12'");
        $result = pg_delete($this->connection, str_replace(' from ', '', $this->table), $this->assocarray);

        if (!$result) {
            throw new \Exception("Houve uma falha ao deletar registro.");
        }

        // Registra o acontecimento na auditoria
        if (empty($auditoria)) {
            $auditoria = "Registro excluido.";
        }
        if (empty($formulario)) {
            $formulario = $_SESSION['PARAMETERS']->form->viewtitle;
        }
        // Auditoria::save($this->connection, $auditoria, $formulario);
    }

    public function conn($connection)
    {
        $this->connection = $connection;
        return $this;
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
            $this->table = ' from ' . $table;
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

        $this->assocarray[$field] = $content;
        $this->params[] = $content;
        $this->where .= $field . ' ' . $operator . ' ' . '$' . count($this->params);
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

        $this->params[] = $content;
        $this->where .= $field . ' ' . $operator . ' ' . '$' . count($this->params);
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
        // valid the connection
        if (!isset($this->connection)) throw new \Exception("SQL: Conexão indefinida.");

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

        // run que query (command)
        $result = pg_query_params($this->connection, $this->sql, $this->params);

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
        $this->renderExecuteStatment($this->sql);

        // run que query (command)
        // $result = pg_query_params($this->connection, $this->sql);
        $result = pg_query($this->connection, $this->sql);

        // show the SQL command on screen
        $this->showDebug($result);

        return $this->Result($result);
    }

    public static function isconnected($connection)
    {
        if (pg_connection_status($connection) === PGSQL_CONNECTION_OK) {
            return true;
        } else {
            return false;
        }
    }

    public function Result($result = '')
    {
        if (!$result) {
            return false;
        }

        $count = pg_numrows($result);
        $eof = $count > 0 ? false : true;
        $result = pg_fetch_all($result);

        if ($count <= 0) {
            return json_decode(json_encode([
                'count' => $count,
                'EOF' => $eof,
                'fields' => null
            ]));
        } else {
            $fields = new stdClass();
            foreach ($result as $i => $record) {
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
            }

            return json_decode(json_encode([
                'count' => $count,
                'EOF' => $eof,
                'fields' => $fields
            ]));
        }
    }

    public static function dbName($database, $settings)
    {

        /*
        if (strtolower($database) == "interact") {
            return $settings->DBINSTI;
        } elseif (
            strtolower($database) == "compartilhamentos" ||
            strtolower($database) == "compartilhamentos" ||
            strtolower($database) == "sharetable" ||
            strtolower($database) == "compart"
        ) {
            return $settings->DBINSTI;
        }
        */

        switch ($database) {
            case 'parameters':
            case 'parameter':
            case 'param':
                $database = $settings->DBPARAM;
                break;
            case 'compartilhamentos':
            case 'share':
            case 'sharetable':
            case 'compart':
                return $settings->DBCOMPA;
                break;
            case 'interact':
                return $settings->DBADMIN;
                break;
            case 'admin':
                $database = $settings->DBADMIN;
                break;
            case '':
                if (isset($_SESSION['SERIAL'])) {
                    $database = $_SESSION['SERIAL'];
                }
                break;
            default:
                break;
        }

        if (empty($database)) {
            return '';
        }

        return "_" . str_replace("-", "_", $database);
    }

    public static function Close($database)
    {
        if (!empty($database)) {
            pg_close($database);
            $database = null;
        }
        return $database;
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

    public function renderExecuteStatment($statment)
    {
        $this->params = null;
        if (strpos($statment, '@') > 1) {
            list($this->connection, $this->sql) = explode('@', $statment);
            $this->connection = $this->Open($this->connection);
        } else {
            $this->sql = $statment;
            if (!isset($this->connection)) {
                throw new \Exception("SQL: Informe a conexão do Banco de Dados.");
            }
            if (empty($this->connection)) {
                throw new \Exception("SQL: Informe a conexão com o Banco de Dados.");
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
        if (file_exists('/.env')) {
            return parse_ini_file('/.env');
        } else {
            throw new \Exception("No Database settings.");
        }
    }
}
