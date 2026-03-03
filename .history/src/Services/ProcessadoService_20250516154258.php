<?php

namespace App\Services;

use App\Database;
use PDO;

class ProcessadoService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function jaProcessado(string $sku): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM produtos_processados WHERE sku = :sku");
        $stmt->execute(['sku' => $sku]);
        return $stmt->fetchColumn() > 0;
    }

    public function marcarComoProcessado(string $sku): void
    {
        $stmt = $this->db->prepare("INSERT INTO produtos_processados (sku) VALUES (:sku)");
        $stmt->execute(['sku' => $sku]);
    }
}
