<?php

namespace App\Services;

class ProcessadoService
{
    private \mysqli $conn;

    public function __construct()
    {
        $this->conn = (new DatabaseService())->getConnection();
    }

    public function jaProcessado(string $sku): bool
    {
        $stmt = $this->conn->prepare("SELECT 1 FROM produtos_processados WHERE sku = ? LIMIT 1");
        $stmt->bind_param("s", $sku);
        $stmt->execute();
        $stmt->store_result();

        return $stmt->num_rows > 0;
    }

    public function marcarComoProcessado(string $sku): void
    {
        $stmt = $this->conn->prepare("INSERT INTO produtos_processados (sku) VALUES (?)");
        $stmt->bind_param("s", $sku);
        $stmt->execute();
    }
}
