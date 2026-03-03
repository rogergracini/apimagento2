<?php

namespace App\Database;

use mysqli;

class Database
{
    private static ?mysqli $connection = null;

    public static function getConnection(): mysqli
    {
        if (self::$connection === null) {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';
            $name = $_ENV['DB_NAME'] ?? '';

            self::$connection = new mysqli($host, $user, $pass, $name);
            self::$connection->set_charset('utf8');

            if (self::$connection->connect_error) {
                throw new \Exception("Erro na conexão com o banco de dados: " . self::$connection->connect_error);
            }
        }

        return self::$connection;
    }
}
