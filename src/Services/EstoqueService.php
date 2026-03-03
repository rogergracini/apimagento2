<?php

namespace App\Services;

class EstoqueService
{
    public function gerarDadosEstoque(int $quantidade = 999): array
    {
        return [
            'use_config_manage_stock' => 0,
            'use_config_min_qty' => 1,
            'use_config_min_sale_qty' => 1,
            'use_config_max_sale_qty' => 1,
            'use_config_backorders' => 1,
            'use_config_notify_stock_qty' => 1,
            'manage_stock' => 1,
            'min_sale_qty' => 0,
            'min_qty' => 0,
            'max_sale_qty' => 100000,
            'is_qty_decimal' => 0,
            'backorders' => 0,
            'notify_stock_qty' => 5000,
            'is_in_stock' => 1,
            'qty' => $quantidade
        ];
    }
}
