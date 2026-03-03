<?php

namespace App\Services;

use App\MagentoApiClient;
use App\Services\XmlParserService;
use App\Services\ProcessadoService;
use App\Services\PrecoService;

class ProdutoService
{
    protected $magentoClient;
    protected $xmlService;

    public function __construct()
    {
        $this->magentoClient = new MagentoApiClient();
        $this->xmlService = new XmlParserService();
    }

    // Importa TODOS os produtos do XML
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

    // Importa UM produto específico
    public function importarProduto(array $produto): array
    {
        $sku = $produto['ProdutoID_Int'] ?? null;

        if (!$sku) {
            return [
                'status' => 'erro',
                'mensagem' => 'SKU ausente',
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

        $payload = [
            'sku' => $sku,
            'name' => $produto['Descricao'] ?? 'Sem nome',
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
            // Marca como processado
            $processadoService->marcarComoProcessado($sku);

            // Calcula e aplica preços por grupo
            $precoService = new PrecoService();
            $grupos = $precoService->getPrecosPorGrupoMagento($produto);

            $calculo = $precoService->calcularPrecos(
                $grupos,
                'AG', // Tipo de produto ('AG', 'OURO' etc.)
                (float)($produto['Peso'] ?? 1),
                false // Cálculo por grama?
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

    // Consulta produto existente via Magento API
    public function buscarProdutoMagento(string $sku): ?array
    {
        return $this->magentoClient->getProductBySku($sku);
    }
}
