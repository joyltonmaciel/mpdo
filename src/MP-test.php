<?php

namespace Mpdo;

class FakeMDB
{

    protected $pdo;

    public function __construct($dbname = '')
    {
        // $mbd = new PDO('mysql:host=localhost;dbname=prueba', $usuario, $contraseña);
        try {
            $usuario = 'postgres';
            $contrasenia = 'root';
            $this->pdo = new \PDO('pgsql:dbname=postgresql_pdo;host=localhost;', $usuario, $contrasenia);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function insert()
    {
        try {
            $this->pdo->beginTransaction();
            $this->execute("insert into users (name, lastname, email, password) values ('Lau', 'V.','lau@correo.com','123445')");
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw new \Exception("Gravar: " . $e->getMessage());
        }
    }

    public function select()
    {
        $sql = "SELECT * FROM users WHERE name = :name ";
        $valor = "LEON";

        $statement = $this->pdo->prepare($sql);
        $statement->bindParam(':name', $valor, \PDO::PARAM_STR);
        $statement->execute();

        foreach ($statement->fetchAll() as $row) {
            echo $row["id"];
        }

        // foreach($this->pdo->query($sql) as $row){
        //     echo $row["id"];
        //     echo $row["name"];
        // }
    }

    public function update()
    {
        $sql = "UPDATE users SET name = 'Juan Leonel' WHERE Id = :Id ";

        $statement = $this->pdo->prepare($sql);
        $statement->execute(array('Id' => 5));

        if ($statement->rowCount()) {
            echo "Dato actualizado! " . $statement->rowCount();
        } else {
            echo "ningun dato actualizado! " . $statement->rowCount();
        }
    }

    public function delete()
    {
        $sql = "DELETE FROM users WHERE Id = :Id";

        $stm = $this->pdo->prepare($sql);
        $stm->execute(array(':Id' => 4));

        if ($stm->rowCount() > 0) {
            echo "Registro eliminado";
        } else {
            echo "Ningun elemento eliminado";
        }
    }

    public function execute($sql)
    {
        $result = $this->pdo->exec($sql);
        if (!$result) {
            throw new \Exception("Erro ao executar comando.");
        }
    }
}
