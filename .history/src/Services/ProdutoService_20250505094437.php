<?php

namespace App\Services;

use App\Magento\MagentoApiClient;

class ProdutoService
{
    protected $magentoClient;

    public function __construct()
    {
        $this->magentoClient = new MagentoApiClient();
    }

    public function importarProdutos(array $produtos): array
    {
        $resultados = [];

        foreach ($produtos as $produto) {
            // Verifica se o produto está ativo
            if (
                isset($produto['Ativo']) &&
                ($produto['Ativo'] === false || strtolower($produto['Ativo']) === 'false')
            ) {
                $resultados[] = [
                    'sku' => $produto['ProdutoID_Int'] ?? '',
                    'status' => 'ignorado',
                    'mensagem' => 'Produto inativo',
                ];
                continue;
            }

            // Monta o payload para o Magento
            $sku = $produto['ProdutoID_Int'] ?? null;
            $nome = $produto['Descricao'] ?? 'Sem nome';
            $peso = (float)($produto['Peso'] ?? 0);
            $preco = (float)($produto['Preco'] ?? 0);

            if (!$sku) {
                $resultados[] = [
                    'sku' => '',
                    'status' => 'erro',
                    'mensagem' => 'SKU ausente',
                ];
                continue;
            }

            $payload = [
                'product' => [
                    'sku' => $sku,
                    'name' => $nome,
                    'price' => $preco,
                    'status' => 1,
                    'type_id' => 'simple',
                    'attribute_set_id' => 4,
                    'weight' => $peso,
                    'visibility' => 4,
                    'extension_attributes' => [
                        'stock_item' => [
                            'qty' => 999,
                            'is_in_stock' => true,
                        ],
                    ],
                ]
            ];

            try {
                $resposta = $this->magentoClient->post('/products', $payload);
                $resultados[] = [
                    'sku' => $sku,
                    'status' => 'importado',
                    'mensagem' => 'Produto criado com sucesso',
                ];
            } catch (\Exception $e) {
                $resultados[] = [
                    'sku' => $sku,
                    'status' => 'erro',
                    'mensagem' => $e->getMessage(),
                ];
            }
        }

        return $resultados;
    }
}
