<?php

namespace Mpdo;

use stdClass;
use Mpdo\DotEnv;
use Mpdo\Strings;
use Mpdo\TypeManipulations;

/**
 * MDB.php - at February 8, 2020.
 * @author Joylton Maciel <maciel dot inbox at gmail dot com>
 *
 * MPDO - My PDO
 * DabaBase Connection and Manipulation
 */

class MDB
{
    protected $env;
    protected $database;

    /**
     * Open database
     *
     * @param string $dbname. Must exists a database name.
     * @param string $host. Which machine is installed the database.
     * @param boolean $debug, when true will show the sql query command.
     * @param boolean $debug
     */
    public function __construct($dbname, $host = '', $debug = '')
    {

        if (empty($dbname)) {
            throw new \Exception("Informe o nome do banco de Dados.");
        }

        try {

            /**
             * Get the environment parameters.
             * Return an Exception if no .env file.
             */
            $env = DotEnv::getDotEnvData();

            /**
             * Set the dbname
             */
            $dbname = self::setDbname($dbname, $env);

            /**
             * Set the Host
             */
            $host = self::setHost($host, $env);

            /**
             * set the Connection data
             */
            $conn = $env->DB_DRIVER
                . ":host=" . $host
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
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            /**
             * set the database name
             */
            $this->database = $dbname;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        if ($debug) {
            $this->debug = true;
        }
    }

    /**
     * BeginTrans() start transaction features manually.
     * In this case, the insert will not start or commit 
     * a transaction.
     *
     * @return void
     */
    public function BeginTrans()
    {
        if (!isset($this->intransaction)) {
            $this->intransaction = $this->connection->beginTransaction();
        }
        return $this;
    }

    /**
     * Roll back a transaction
     *
     * @return void
     */
    public function RollbackTrans()
    {
        if (isset($this->intransaction)) {
            $this->connection->rollBack();
            unset($this->intransaction);
        }
        return $this;
    }

    /**
     * Commit Transaction
     *
     * @return void
     */
    public function CommitTrans()
    {
        if (isset($this->intransaction)) {
            $this->connection->commit();
            unset($this->intransaction);
        }
        return $this;
    }

    public function insert($dados, $serial = null)
    {
        try {

            if (!isset($this->intransaction)) {
                $this->connection->beginTransaction();
            }

            $fillIdList = true;
            $idList = [];

            foreach ($dados as $table => $records) {

                // compose the sql statement
                $fields = '';
                $values = '';
                foreach ($records as $campo => $content) {

                    if (!empty($fields)) {
                        $fields .= ', ';
                        $values .= ', ';
                    }

                    // If the serial filder must receive a value, the idList
                    // will not return the lastid (autoincrement).
                    if (!is_null($serial)) {
                        if ($serial == $campo) {
                            $fillIdList = false;
                        }
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

                if ($conn->rowCount() <= 0) {
                    throw new \Exception("Erro ao gravar o registro");
                }

                if ($fillIdList) {
                    $idList[$table] = $this->connection->lastInsertId();
                }
            }

            // $intransaction is set manually
            if (!isset($this->intransaction)) {
                $this->connection->commit();
            }

            // limpa parametros da consulta anterior
            $this->cleanAll();

            // return last Id
            return $idList;
        } catch (\Exception $e) {
            if (!isset($this->intransaction)) {
                $this->connection->rollBack();
            }
            $this->cleanAll();
            throw new \Exception("Gravar: " . $e->getMessage());
        }
    }

    public function update($dados, $auditoria = '', $formulario = '')
    {
        // set variables used in this method and start the sql command
        $sql = 'UPDATE ';
        $update = '';

        // generate an Exception if the name of the table is nos passed.
        if (!isset($this->table)) {
            throw new \Exception("SQL: Tabela indefinida.");
        }

        // set table name
        $sql .= str_replace(' FROM ', '', $this->table);

        // command
        $sql .= ' SET ';

        // monta
        foreach ($dados as $campo => $content) {
            if (empty($update)) {
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
        if (isset($this->where)) {
            $sql .= $this->where;
        }

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
        $sql = 'DELETE ';

        // table name
        if (!isset($this->table)) {
            throw new \Exception("SQL: Tabela indefinida.");
        }

        $sql .= $this->table;

        // condition
        if (isset($this->where)) {
            $sql .= $this->where;
        }

        // prepare and execute the statment
        $conn = $this->connection->prepare($sql);
        $conn->execute($this->params);

        // show the SQL command on screen
        $this->showDebug($conn, $sql);

        // limpa parametros da consulta anterior
        $this->cleanAll();

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
     * @return this 
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
     * Set true or false as string: 't' or 'f'
     */
    private function setTrueFalse($value)
    {
        if (is_bool($value)) {
            if ($value === true) {
                $value = 't';
            } elseif ($value === false) {
                $value = 'f';
            }
        }
        return $value;
    }

    /**
     * Integer, Float, Numeric are changed to string
     */
    private function setIntFloatNumeric($value)
    {
        if (
            is_int($value) ||
            is_float($value) ||
            is_numeric($value)
        ) {
            $value = strval($value);
        }
        return $value;
    }

    private function setFillOperatorAndContent($field, $operator, $content)
    {
        if (
            !empty($field) &&
            strlen($operator) <= 0 &&
            strlen($content) <= 0
        ) {
            if (strpos($field, '=') > 0) {
                list($field, $content) = explode('=', $field);
                $operator = '=';
            } elseif (strpos($field, '>') > 0) {
                list($field, $content) = explode('>', $field);
                $operator = '>';
            } elseif (strpos($field, '<') > 0) {
                list($field, $content) = explode('<', $field);
                $operator = '<';
            } elseif (strpos($field, '>=') > 0) {
                list($field, $content) = explode('>=', $field);
                $operator = '>=';
            } elseif (strpos($field, '<=') > 0) {
                list($field, $content) = explode('<=', $field);
                $operator = '<=';
            } elseif (strpos($field, '!=') > 0) {
                list($field, $content) = explode('!=', $field);
                $operator = '!=';
            } else {
                $operator = '=';
                $content = '';
                //                throw new \Exception("Parâmetros passados para método where estão incompletos. ($field, $operator, $content)");
            }
        }

        return json_decode(json_encode([
            'field' => $field,
            'operator' => $operator,
            'content' => $content,
        ]));
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
         * Corrige os campos boolean para string 't' ou 'f'
         */
        $operator = $this->setTrueFalse($operator);
        $content = $this->setTrueFalse($content);

        /**
         * corrige os campos inteiros, flutuantes ou numericos para string
         */
        $operator = $this->setIntFloatNumeric($operator);
        $content = $this->setIntFloatNumeric($content);

        /**
         * Separa a string field quando o campo, o operador e a content
         * estao juntos no parametro field.
         */
        $separatestring = $this->setFillOperatorAndContent($field, $operator, $content);
        $field = $separatestring->field;
        $operator = $separatestring->operator;
        $content = $separatestring->content;

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


    public function orWhere($field, $operator = '', $content = '')
    {
        /**
         * Corrige os campos boolean para string 't' ou 'f'
         */
        $operator = $this->setTrueFalse($operator);
        $content = $this->setTrueFalse($content);

        /**
         * corrige os campos inteiros, flutuantes ou numericos para string
         */
        $operator = $this->setIntFloatNumeric($operator);
        $content = $this->setIntFloatNumeric($content);

        /**
         * Separa a string field quando o campo, o operador e a content
         * estao juntos no parametro field.
         */
        $separatestring = $this->setFillOperatorAndContent($field, $operator, $content);
        $field = $separatestring->field;
        $operator = $separatestring->operator;
        $content = $separatestring->content;

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

        if (!isset($this->where)) {
            throw new \Exception("Where condition deve ser informado subsequentemente anterior à orWhere()");
        } else {
            $this->where .= ' OR ';
        }

        /**
         * O while a seguir eh importante para evitar campos com o mesmo
         * nome na pesquisa.
         */
        $arrayfield = $field;
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

    public function whereIn($field, $array)
    {
        if (!is_array($array)) {
            throw new \Exception("Value passed to the method whereIn must be an array.");
        }

        if (!isset($this->whereIn)) {
            $this->whereIn = $field . ' IN (';
        } else {
            $this->whereIn .= ' AND ' . $field . ' IN (';
        }

        $values = '';
        foreach ($array as $vle) {
            if (strlen($values) > 0) {
                $values .= ', ';
            }
            $values .= $vle;
        }

        $this->whereIn .= $values . ')';
        return $this;
    }

    public function whereNotIn($field, $array)
    {
        if (!is_array($array)) {
            throw new \Exception("Value passed to the method whereIn must be an array.");
        }

        if (!isset($this->whereIn)) {
            $this->whereIn = $field . ' NOT IN (';
        } else {
            $this->whereIn .= ' AND ' . $field . ' NOT IN (';
        }

        $values = '';
        foreach ($array as $vle) {
            if (!empty($values)) {
                $values .= ', ';
            }
            $values .= $vle;
        }

        $this->whereIn .= $values . ')';
        return $this;
    }

    public function orWhereRaw($orWhereRaw)
    {
        if (!isset($this->orWhereRaw)) {
            $this->orWhereRaw = $orWhereRaw;
        } else {
            $this->orWhereRaw .= ' OR ' . $orWhereRaw;
        }
        return $this;
    }

    public function whereRaw($whereRaw)
    {
        if (!isset($this->whereRaw)) {
            $this->whereRaw = $whereRaw;
        } else {
            $this->whereRaw .= ' AND ' . $whereRaw;
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
            if (isset($this->groupBy)) {
                $this->groupBy .= ', ' . $content;
            } else {
                $this->groupBy = ' GROUP BY ' . $content;
            }
        }

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

    public function orderBy($orderBy, $desc = '')
    {
        if (!isset($this->orderBy)) {
            $this->orderBy = ' order by ' . $orderBy;
        } else {
            $this->orderBy .= ', ' . $orderBy;
        }

        if ($desc == 'desc') {
            $this->orderBy .= ' ' . $desc;
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
        if (!isset($this->params)) {
            $this->params = [];
        }

        // command
        $sql = 'SELECT ';

        // fields to return
        if (!isset($this->select)) {
            $this->select();
        }
        $sql .= $this->select;

        // sum
        if (isset($this->sum)) {
            $sql .= $this->sum;
        }

        // table name
        if (!isset($this->table)) {
            throw new \Exception("SQL: Tabela indefinida.");
        }
        $sql .= $this->table;

        // join
        if (isset($this->join)) {
            $sql .= $this->join;
        }

        // condition
        if (isset($this->where)) {
            $sql .= $this->where;
        }

        // condition : whereIn
        if (isset($this->whereIn)) {
            if (!isset($this->where)) {
                $sql .= ' WHERE ' . $this->whereIn;
            } else {
                $sql .= ' AND ' . $this->whereIn;
            }
        }

        // condition : whereRaw
        if (isset($this->whereRaw)) {
            if (!isset($this->where)) {
                $sql .= ' WHERE ' . $this->whereRaw;
            } else {
                $sql .= ' AND ' . $this->whereRaw;
            }
        }

        // condition : or where
        if (isset($this->orWhereRaw)) {
            if (!isset($this->where)) {
                $sql .= ' WHERE ' . $this->orWhereRaw;
            } else {
                $sql .= ' AND ' . $this->orWhereRaw;
            }
        }

        // group by
        if (isset($this->groupBy)) {
            $sql .= $this->groupBy;
        }

        // order by
        if (isset($this->orderBy)) {
            $sql .= $this->orderBy;
        }

        // limit
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        // SQL adjusts
        $sql = str_replace(', ,', ',', $sql);
        $sql = str_replace(',,', ',', $sql);

        // show the SQL query
        if (isset($this->debug)) {
            if ($this->debug) {
                echo "<hr>$sql<hr>";
            }
        }

        // prepare and execute the statment
        try {
            $result = $this->connection->prepare($sql);
            $result->execute($this->params);
        } catch (\Exception $e) {
            $this->cleanAll();
            throw new \Exception($e->getMessage());
        }

        // show the SQL command on screen
        $this->showDebug($result, $sql);

        return $this->Result($result);
    }

    /**
     * Execute a SQL statment (command/query)
     *
     * @param string $statment: use: dbname@sql_statment or just sql_statment
     * @return object
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

    private function setKey()
    {
        if (isset($this->key)) {
            $auxkey = $this->key;
            $this->key = [];
            if (strpos($auxkey, '|') > 0) {
                $this->key = explode('|', $auxkey);
            } else {
                $this->key[0] = $auxkey;
            }
        }
    }

    private function add_keys_dynamic($main_array, $value)
    {
        $keys = $this->key;
        $tmp_array = &$main_array;
        while (count($keys) > 0) {
            $k = trim(array_shift($keys));
            if (!is_array($tmp_array)) {
                $tmp_array = [];
            }
            if ($this->rules == 'onlynumbers') {
                $k = Strings::onlyNumbers($value[$k]);
            } else {
                $k = $value[$k];
            }
            $tmp_array = &$tmp_array[$k];
        }
        $tmp_array = $value;
        return $main_array;
    }

    public function ResultFetchAll($result)
    {
        $count = 0;
        $eof = true;
        $fields = [];
        $this->setKey();

        foreach ($result->fetchAll() as $i => $record) {
            if (isset($this->noKey)) {
                $fields = $record;
                $count = 1;
            } else {
                if (isset($this->key)) {
                    $fields = $this->add_keys_dynamic($fields, $record);
                } else {
                    $fields[$i] = $record;
                }
                $count++;
            }
        }

        // limpa parametros da consulta anterior
        $this->cleanAll();

        $eof = $count > 0 ? false : true;
        $fields = TypeManipulations::array2object($fields);
        return json_decode(json_encode([
            'count' => $count,
            'EOF' => $eof,
            'fields' => $fields,
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

                if (isset($this->params)) {
                    print_r($this->params);
                } else {
                    echo "No parameters set.";
                }

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

    /**
     * A debug features to show all loaded parameters.
     *
     * @return void
     */
    public function loadedup()
    {
        echo "<br>MPDO - Dababase Manipulation Class";
        echo "<hr>";
        echo "<b>Loaded UP parameters and settings:</b>";

        if (isset($this->join)) {
            echo "<br><b>join:</b> " . $this->join;
        }

        if (isset($this->key)) {
            echo "<br><b>key:</b> " . $this->key;
        }

        if (isset($this->groupBy)) {
            echo "<br><b>groupBy:</b> " . $this->groupBy;
        }

        if (isset($this->orderBy)) {
            echo "<br><b>orderBy:</b> " . $this->orderBy;
        }

        if (isset($this->noKey)) {
            echo "<br><b>noKey:</b> " . $this->noKey;
        }

        if (isset($this->where)) {
            echo "<br><b>where:</b> " . $this->where;
        }

        if (isset($this->whereRaw)) {
            echo "<br><b>whereRaw:</b> " . $this->whereRaw;
        }

        if (isset($this->whereIn)) {
            echo "<br><b>whereIn:</b> " . $this->whereIn;
        }

        if (isset($this->whereNotIn)) {
            echo "<br><b>whereNotIn:</b> " . $this->whereNotIn;
        }

        if (isset($this->orWhere)) {
            echo "<br><b>orWhere:</b> " . $this->orWhere;
        }

        if (isset($this->orWhereRaw)) {
            echo "<br><b>orWhereRaw:</b> " . $this->orWhereRaw;
        }

        if (isset($this->whereNotNull)) {
            echo "<br><b>whereNotNull:</b> " . $this->whereNotNull;
        }

        if (isset($this->params)) {
            echo "<br><b>params:</b> <pre>" . print_r($this->params, true) . "</pre>";
        }

        if (isset($this->select)) {
            echo "<br><b>select:</b> " . $this->select;
        }

        if (isset($this->sum)) {
            echo "<br><b>sum:</b> " . $this->sum;
        }

        if (isset($this->table)) {
            echo "<br><b>table:</b> " . $this->table;
        }

        if (isset($this->htmldatalist)) {
            echo "<br><b>htmldtalist:</b> " . $this->htmldatalist;
        }

        echo "<hr>";
    }

    public function cleanAll()
    {
        if (isset($this->debug)) {
            if ($this->debug) {
                echo "<br>Start to clean all parameters.";
                echo "<br>table => " . $this->table;
            }
        }

        if (isset($this->join)) {
            unset($this->join);
        }

        if (isset($this->key)) {
            unset($this->key);
        }

        if (isset($this->groupBy)) {
            unset($this->groupBy);
        }

        if (isset($this->orderBy)) {
            unset($this->orderBy);
        }

        if (isset($this->noKey)) {
            unset($this->noKey);
        }

        if (isset($this->where)) {
            unset($this->where);
        }

        if (isset($this->whereRaw)) {
            unset($this->whereRaw);
        }

        if (isset($this->whereIn)) {
            unset($this->whereIn);
        }

        if (isset($this->whereNotIn)) {
            unset($this->whereNotIn);
        }

        if (isset($this->whereNotNull)) {
            unset($this->whereNotNull);
        }

        if (isset($this->orWhere)) {
            unset($this->orWhere);
        }

        if (isset($this->orWhereRaw)) {
            unset($this->orWhereRaw);
        }

        if (isset($this->params)) {
            unset($this->params);
        }

        if (isset($this->select)) {
            unset($this->select);
        }

        if (isset($this->sum)) {
            unset($this->sum);
        }

        if (isset($this->table)) {
            unset($this->table);
        }

        if (isset($this->htmldatalist)) {
            unset($this->htmldatalist);
        }

        if (isset($this->debug)) {
            if ($this->debug) {
                echo "<br>Finished to clean all parameters...";
            }
        }
    }

    private static function setDbname($dbname, $env)
    {
        if ($dbname == 'admin') {
            return $env->DB_ADMIN;
        }

        if (
            $dbname == 'parameters' ||
            $dbname == 'parameter' ||
            $dbname == 'param'
        ) {
            return $env->DB_PARAM;
        }

        return $dbname;
    }

    private static function setHost($host, $env)
    {
        if (empty($host)) {
            return $env->DB_HOST;
        }

        if (isset($env->{'DB_HOST' . '_' . strtoupper($host)})) {
            return $env->{'DB_HOST' . '_' . strtoupper($host)};
        } else {
            return $env->DB_HOST;
        }
    }
}

