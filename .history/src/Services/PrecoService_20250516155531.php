<?php

namespace App\Services;

class PrecoService
{
    // 🔹 Mapeia tabelas para grupos de cliente Magento
    public function getPrecosPorGrupoMagento(array $produto): array
    {
        if (!isset($produto['groups']) || !is_array($produto['groups'])) {
            return [];
        }

        $tabelas = [];

        foreach ($produto['groups'] as [$tabelaId, $preco]) {
            $tabelas[$tabelaId] = $preco;
        }

        $grupoMagento = [];

        if (isset($tabelas['666']) && isset($tabelas['670'])) {
            $grupoMagento[4] = $tabelas['670'];
            $grupoMagento[5] = $tabelas['670'];
        }

        if (isset($tabelas['666']) && isset($tabelas['671'])) {
            $grupoMagento[6] = $tabelas['671'];
            $grupoMagento[7] = $tabelas['671'];
        }

        if (isset($tabelas['667']) && isset($tabelas['670'])) {
            $grupoMagento[8] = $tabelas['670'];
            $grupoMagento[9] = $tabelas['670'];
        }

        if (isset($tabelas['667']) && isset($tabelas['671'])) {
            $grupoMagento[10] = $tabelas['671'];
            $grupoMagento[11] = $tabelas['671'];
        }

        if (isset($tabelas['668']) && isset($tabelas['670'])) {
            $grupoMagento[12] = $tabelas['670'];
            $grupoMagento[13] = $tabelas['670'];
        }

        if (isset($tabelas['668']) && isset($tabelas['671'])) {
            $grupoMagento[14] = $tabelas['671'];
            $grupoMagento[15] = $tabelas['671'];
            $grupoMagento[16] = $tabelas['671'];
            $grupoMagento[17] = $tabelas['671'];
        }

        return $grupoMagento;
    }

    // 🔹 Calcula os preços reais por grupo
    public function calcularPrecos(array $grupos, string $tipoProduto = 'OURO', float $peso = 1, bool $porGrama = false): array
    {
        $tierPrices = [];
        $precoMaisAlto = 0;

        $pesoFinal = $porGrama ? $peso : 1;

        foreach ($grupos as $grupoId => $valorBase) {
            $precoX4 = floatval($valorBase) * 4 * $pesoFinal;
            $precoX5 = floatval($valorBase) * 5 * $pesoFinal;

            if ($precoMaisAlto < $precoX4) $precoMaisAlto = $precoX4;
            if ($precoMaisAlto < $precoX5) $precoMaisAlto = $precoX5;

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
