<?php

namespace App\Services;

use App\MagentoApiClient;
use App\Services\XmlParserService;
use App\Services\ProcessadoService;
use App\Services\PrecoService;
use App\Utils\SlugHelper;

class ProdutoService
{
    protected $magentoClient;
    protected $xmlService;

    public function __construct()
    {
        $this->magentoClient = new MagentoApiClient();
        $this->xmlService = new XmlParserService();
    }

    public function importarProdutos(): array
    {
        $produtoXml = __DIR__ . '/../../Produto.xml';
        $precoXml = __DIR__ . '/../../Preco.xml';
        $todosProdutos = $this->xmlService->carregarProdutos($produtoXml, $precoXml);

        $resultados = [];

        foreach ($todosProdutos as $produto) {
            $resultado = $this->importarProduto($produto);
            $resultados[] = array_merge(['sku' => $produto['ProdutoID_Int'] ?? ''], $resultado);
        }

        return $resultados;
    }

    public function importarProduto(array $produto): array
    {
        $sku = $produto['ProdutoID_Int'] ?? null;

        if (!$sku) {
            return [
                'status' => 'erro',
                'mensagem' => 'SKU ausente',
            ];
        }

        // ❌ Ignora SKUs contendo 'BR'
        if (stripos($sku, 'BR') !== false) {
            return [
                'status' => 'ignorado',
                'mensagem' => 'SKU contém "BR", ignorado'
            ];
        }

        $processadoService = new ProcessadoService();

        if ($processadoService->jaProcessado($sku)) {
            return [
                'status' => 'ignorado',
                'mensagem' => 'SKU já processado anteriormente',
            ];
        }

        if (
            isset($produto['Ativo']) &&
            ($produto['Ativo'] === false || strtolower($produto['Ativo']) === 'false')
        ) {
            return [
                'status' => 'ignorado',
                'mensagem' => 'Produto inativo',
            ];
        }

        // ✅ Monta nome com dimensões
        $descricao = $produto['Descricao'] ?? 'Produto';
        $tipo = $produto['TipoID_Int'] ?? '';
        $largura = $produto['Largura_MM'] ?? '';
        $altura = $produto['Altura_MM'] ?? '';
        $peso = $produto['Peso'] ?? '';

        $nomeFormatado = trim("{$descricao} - {$tipo} - {$largura}mm x {$altura}mm - {$peso}gr");
        $urlKey = SlugHelper::slug("{$descricao} {$sku}");

        $payload = [
            'sku' => $sku,
            'name' => $nomeFormatado,
            'url_key' => $urlKey,
            'description' => $nomeFormatado,
            'short_description' => $nomeFormatado,
            'price' => (float)($produto['Preco'] ?? 0),
            'status' => 1,
            'type_id' => 'simple',
            'attribute_set_id' => 4,
            'weight' => (float)($produto['Peso'] ?? 0),
            'visibility' => 4,
            'extension_attributes' => [
                'stock_item' => [
                    'qty' => 999,
                    'is_in_stock' => true,
                ],
            ],
        ];

        $resposta = $this->magentoClient->createOrUpdateProduct($payload);

        if (isset($resposta['id'])) {
            $processadoService->marcarComoProcessado($sku);

            // Aplica preços por grupo
            $precoService = new PrecoService();
            $grupos = $precoService->getPrecosPorGrupoMagento($produto);

            $calculo = $precoService->calcularPrecos(
                $grupos,
                'AG',
                (float)($produto['Peso'] ?? 1),
                false
            );

            foreach ($calculo['tier_prices'] as $tier) {
                $this->magentoClient->setPrecoPorGrupo($sku, $tier['cust_group'], $tier['price']);
            }

            return [
                'status' => 'importado',
                'mensagem' => 'Produto criado/atualizado',
                'grupos_aplicados' => $calculo['tier_prices'],
                'preco_base' => $calculo['preco_base']
            ];
        } else {
            return [
                'status' => 'erro',
                'mensagem' => $resposta['message'] ?? 'Erro desconhecido',
            ];
        }
    }

    public function buscarProdutoMagento(string $sku): ?array
    {
        return $this->magentoClient->getProductBySku($sku);
    }
}
