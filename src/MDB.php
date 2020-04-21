<?php

namespace Mpdo;

use stdClass;
use Mpdo\DotEnv;
use Mpdo\Strings;

/**
 * MDB.php
 * by Joylton Maciel at February 8, 2020.
 * 
 * MPDO - My PDO
 * DabaBase Connection and Manipulation
 */

class MDB
{
    protected $env;

    /**
     * Open database
     *
     * @param string $dbname. Must exists a database name.
     * @param boolean $debug, when true will show the sql query command.
     * @param boolean $debug
     */
    public function __construct($dbname, $debug = false)
    {

        if (empty($dbname)) {
            throw new \Exception("Informe o nome do banco de Dados.");
        }

        try {

            /**
             * Set the environment parameters
             */
            $env = DotEnv::getDotEnvData();

            /**
             * set the Connection data
             */
            $conn = $env->DB_DRIVER
                . ":host=" . $env->DB_HOST
                . ";dbname=" . $dbname;

            /**
             * Connect to the database
             */
            $this->connection = new \PDO(
                $conn,
                $env->DB_USER,
                $env->DB_PASS
            );

            /**
             * Set PDO attributs to the connection
             */
            // $this->connectin->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        if ($debug) {
            $this->debug = true;
        }
    }

    public function insert($dados, $auditoria = '', $formulario = '')
    {
        try {
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

            $this->connection->commit();
            // return $idList;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw new \Exception("Gravar: " . $e->getMessage());
        }
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

        // show the SQL command on screen
        $this->showDebug($conn, $sql);

        // limpa parametros da consulta anterior
        $this->cleanAll();

        if ($conn->rowCount() > 0) {
            return $conn->rowCount();
        } else {
            throw new \Exception("Erro ao alterar registro.");
        }
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
    }

    public function select()
    {
        $args = func_get_args();

        if (count($args) <= 0) {
            $args[0] = '*';
        }

        foreach ($args as $conts) {

            // $opcs = explode(',', preg_replace('/\s+/', '', $conts));
            $opcs = explode(',', str_replace(', ', ',', $conts));

            foreach ($opcs as $content) {
                if (isset($this->sum)) {

                    // se o campo estiver em sum() nao sera
                    // acrescentado no select.

                    if (strpos($this->sum, $content) > 0) {
                        continue;
                    }
                }

                if (isset($this->select)) {
                    $this->select .= ', ' . $content;
                } else {
                    $this->select = $content;
                }
            }
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

    /**
     * Join tables
     * usage: ->join(<table_to_join>, <table_origem.primary_key>, '=', <table_to_join.primary_key>)
     * example: ->join('unidades', 'unidades_config.unidadeid', '=', 'unidades.unidadeid')
     *
     * @param string $table
     * @param string $afield
     * @param string $operator
     * @param string $bfield
     * @return void
     */
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
     * ->where('nome=Ana Vitoria')
     */
    public function where($field, $operator = '', $content = '')
    {
        /**
         * Separa a string field quando o campo, o operador e a content
         * estao juntos no parametro field.
         */
        if (!empty($field) && empty($operator) && empty($content)) {
            if (strpos($field, '=') > 0) {
                list($field, $content) = explode('=', $field);
                $operator = '=';
            } else {
                throw new \Exception("Parâmetros passados para método where estão incompletos.");
            }
        }

        /**
         * Corrige os campos boolean para string 't' ou 'f'
         */
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

        /**
         * corrige os campos inteiros, flutuantes ou numericos para string
         */

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

        /**
         * Corrige o operador
         */
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

        /**
         * Aplica parametros para Postgres
         */
        if (substr($field, 0, 9) == 'translate') {
            $arrayfield = substr($field, 10);
            $arrayfield = substr($arrayfield, 0, strpos($arrayfield, ','));
        } else {
            $arrayfield = $field;
        }

        /**
         * Monta o comando SQL
         */
        if (!isset($this->where)) {
            $this->where = ' WHERE ';
        } else {
            $this->where .= ' AND ';
        }

        /**
         * O while a seguir eh importante para evitar campos com o mesmo 
         * nome na pesquisa.
         */
        while (true) {
            $arrayfield = sprintf("%s", chr(rand(97, 122))) . '_'
                . str_replace('.', '__', $arrayfield);
            if (!isset($this->params[$arrayfield])) {
                $this->params[$arrayfield] = $content;
                $this->where .= $field . ' ' . $operator . ' ' . ':' . $arrayfield;
                break;
            }
        }

        return $this;
    }

    public function sum($field, $nickname = null)
    {
        if (!isset($this->sum)) {
            $this->sum = '';
        }

        $this->sum .= ', sum(' . $field . ')';

        if (!is_null($nickname)) {
            $this->sum .= ' as ' . $nickname;
        }

        if (isset($this->select)) {
            $this->select = str_replace($field, '', $this->select);
        }

        return $this;
    }

    public function groupBy()
    {
        $args = func_get_args();

        if (count($args) <= 0) {
            $args[0] = '*';
        }

        foreach ($args as $content) {
            if (isset($this->groupby)) {
                $this->groupby .= ', ' . $content;
            } else {
                $this->groupby = ' GROUP BY ' . $content;
            }
        }

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

    public function whereNotNull($whereNotNull)
    {
        if (!isset($this->where)) {
            $this->where = ' where ' . $whereNotNull . ' is not null ';
        } else {
            $this->where .= ' and ' . $whereNotNull . ' is not null ';
        }
        return $this;
    }

    public function orderBy($orderby, $desc = '')
    {
        if (!isset($this->orderby)) {
            $this->orderby = ' order by ' . $orderby;
        } else {
            $this->orderby .= ', ' . $orderby;
        }

        if ($desc == 'desc') {
            $this->orderby .= ' ' . $desc;
        }

        return $this;
    }

    public function noKey()
    {
        $this->noKey = true;
        return $this;
    }

    /**
     * Chave e regra ou valor da chave
     *
     * @param string $key
     * @return this
     */
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

    /**
     * gera um Html DataList com retorno do sql query.
     * 
     * @param array $htmldatalist
     * @return this
     */
    public function htmldatalist($htmldatalist)
    {
        $this->htmldatalist = $htmldatalist;
        return $this;
    }

    public function get($limit = 0)
    {
        // parameters for pg_query_params
        if (!isset($this->params)) $this->params = [];

        // command
        $sql = 'SELECT ';

        // fields to return
        if (!isset($this->select)) {
            $this->select();
        }
        $sql .= $this->select;

        // sum
        if (isset($this->sum)) $sql .= $this->sum;

        // table name
        if (!isset($this->table)) throw new \Exception("SQL: Tabela indefinida.");
        $sql .= $this->table;

        // join
        if (isset($this->join)) $sql .= $this->join;

        // condition
        if (isset($this->where)) $sql .= $this->where;

        // group by
        if (isset($this->groupby)) $sql .= $this->groupby;

        // order by
        if (isset($this->orderby)) $sql .= $this->orderby;

        // limit
        if ($limit > 0) $sql .= ' LIMIT ' . $limit;

        // SQL adjusts
        $sql = str_replace(', ,', ',', $sql);
        $sql = str_replace(',,', ',', $sql);

        // prepare and execute the statment
        $result = $this->connection->prepare($sql);
        $result->execute($this->params);

        // show the SQL command on screen
        $this->showDebug($result, $sql);

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
        $result = $this->connection->prepare($sql);
        $result->execute();
        $this->showDebug($result, $sql);
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
        // se nao houve retorno do banco, retorna falso
        if (!$result) {
            $this->cleanAll();
            return false;
        }

        // para criar um datalist do resultado, o key() deve ser passado
        if (isset($this->htmldatalist)) {
            return $this->ResultHtmlDataList($result);
        } else {
            return $this->ResultFetchAll($result);
        }
    }

    public function ResultHtmlDataList($result)
    {
        // retira mais de um espaco e joga o resultado em array
        $flds = explode(',', preg_replace('/\s+/', '', $this->select));

        // start datalist

        $retorno = "<datalist id=\"" . $this->htmldatalist . "\">\n";

        foreach ($result->fetchAll() as $pkey => $record) {

            $retorno .= "\n<option value=\"";

            $options = '';
            foreach ($flds as $index => $field) {
                if (!empty($options)) {
                    $options .= ' | ';
                }
                $options .= $record[$field];
            }

            $retorno .= $options;
            $retorno .= "\">";
        }

        $retorno .= "</datalist>\n";

        $retorno .= "<input type=\"text\" "
            . "name=\"" . $this->htmldatalist . "\" "
            . "list=\"" . $this->htmldatalist . "\" "
            . "class=\"form-control\" autocomplete=\"off\" "
            . "placeholder=\"Digite " . $this->select . " para busca aqui ...\"> \n";

        // limpa parametros da consulta anterior
        $this->cleanAll();

        return $retorno;
    }

    public function ResultFetchAll($result)
    {
        $count = 0;
        $eof = true;
        $fields = new stdClass();

        foreach ($result->fetchAll() as $i => $record) {
            if (isset($this->noKey)) {
                $fields = json_decode(json_encode($record));
            } else {
                if (isset($this->key)) {
                    if (empty($this->rules)) {
                        $fields->{trim($record[$this->key])} = json_decode(json_encode($record));
                    } else {
                        if ($this->rules == 'onlynumbers') {
                            $fields->{Strings::onlyNumbers(trim($record[$this->key]))} = json_decode(json_encode($record));
                        } else {
                            $fields->{trim($record[$this->key])} = json_decode(json_encode($record));
                        }
                    }
                } else {
                    $fields->{$i} = json_decode(json_encode($record));
                }
            }
            $count++;
        }

        $this->cleanAll(); // limpa parametros da consulta anterior

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

    public function showDebug($result, $sql)
    {
        if (isset($this->debug)) {
            if ($this->debug) {
                echo "\nMPDO - Dababase Manipulation Class";
                echo "\n<hr>";
                echo "\n" . $sql;
                echo "\n<hr>";
                echo "<pre>";
                echo "\nPARAMETERS:\n";
                print_r($this->params);
                echo "\n</pre>";
                echo "\nMESSAGE:\n";
                if (!$result) {
                    echo pg_last_error($this->connection);
                } else {
                    echo "OK";
                }
                echo "\n<hr>";
            }
        }
    }

    public function cleanAll()
    {
        if (isset($this->join)) unset($this->join);
        if (isset($this->key)) unset($this->key);
        if (isset($this->groupby)) unset($this->groupby);
        if (isset($this->orderby)) unset($this->orderby);
        if (isset($this->noKey)) unset($this->noKey);
        if (isset($this->where)) unset($this->where);
        if (isset($this->params)) unset($this->params);
        if (isset($this->select)) unset($this->select);
        if (isset($this->sum)) unset($this->sum);
        if (isset($this->table)) unset($this->table);
        if (isset($this->htmldatalist)) unset($this->htmldatalist);
    }
}
