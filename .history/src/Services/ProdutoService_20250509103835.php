<?php

namespace App\Services;

use App\MagentoApiClient;
use App\Services\XmlParserService;

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
            $sku = $produto['ProdutoID_Int'] ?? null;

            // ✅ TESTAR SOMENTE UM SKU (comente para importar todos)
            if ($sku !== '01-1847AG') {
                continue;
            }

            if (!$sku) {
                $resultados[] = [
                    'sku' => '',
                    'status' => 'erro',
                    'mensagem' => 'SKU ausente',
                ];
                continue;
            }

            // Ignora produtos inativos
            if (
                isset($produto['Ativo']) &&
                ($produto['Ativo'] === false || strtolower($produto['Ativo']) === 'false')
            ) {
                $resultados[] = [
                    'sku' => $sku,
                    'status' => 'ignorado',
                    'mensagem' => 'Produto inativo',
                ];
                continue;
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
                        'qty' => 999, // altere conforme necessário
                        'is_in_stock' => true,
                    ],
                ],
            ];

            $resposta = $this->magentoClient->createOrUpdateProduct($payload);

            $resultados[] = [
                'sku' => $sku,
                'status' => isset($resposta['id']) ? 'importado' : 'erro',
                'mensagem' => isset($resposta['id']) ? 'Produto criado/atualizado' : ($resposta['message'] ?? 'Erro desconhecido'),
            ];
        }

        return $resultados;
    }

    public function buscarProdutoMagento(string $sku): ?array
    {
        return $this->magentoClient->getProductBySku($sku);
    }
}
