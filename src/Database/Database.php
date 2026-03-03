<?php

namespace App\Services;

use mysqli;

class DatabaseService
{
    private mysqli $conn;

    public function __construct()
    {
        $this->conn = new mysqli(
            $_ENV['DB_HOST'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASS'],
            $_ENV['DB_NAME'],
            intval($_ENV['DB_PORT'])
        );

        if ($this->conn->connect_error) {
            throw new \Exception('Erro de conexão com o banco de dados: ' . $this->conn->connect_error);
        }

        $this->conn->set_charset('utf8');
        $this->conn->query("SET NAMES 'utf8'");
        $this->conn->query("SET character_set_connection=utf8");
    }

    public function getConnection(): mysqli
    {
        return $this->conn;
    }

    public function __destruct()
    {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
