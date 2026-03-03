<?php

namespace App\Services;

class PrecoService
{
    public function calcularPrecos(array $grupos, string $tipoProduto = 'OURO', float $peso = 1, bool $porGrama = false): array
    {
        $tierPrices = [];
        $precoMaisAlto = 0;

        $pesoFinal = $porGrama ? $peso : 1;

        foreach ($grupos as $grupo) {
            [$grupoId, $valorBase] = $grupo;

            $precoX4 = floatval($valorBase) * 4 * $pesoFinal;
            $precoX5 = floatval($valorBase) * 5 * $pesoFinal;

            if ($precoMaisAlto < $precoX4) {
                $precoMaisAlto = $precoX4;
            }

            if ($precoMaisAlto < $precoX5) {
                $precoMaisAlto = $precoX5;
            }

            // Define qual preço usar conforme o tipo
            $precoFinal = ($tipoProduto === 'AG') ? $precoX4 : $precoX5;

            $tierPrices[] = [
                'website_id' => 0,
                'cust_group' => (int)$grupoId,
                'price' => round($precoFinal, 2),
                'price_qty' => 1,
                'all_groups' => false
            ];
        }

        return [
            'tier_prices' => $tierPrices,
            'preco_base' => round($precoMaisAlto, 2)
        ];
    }
}
