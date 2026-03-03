<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class MagentoApiClient
{
    private Client $client;
    private string $accessToken;

    public function __construct()
    {
        // Garante que o token de acesso do ambiente está carregado.
        if (empty($_ENV['MAGENTO_ACCESS_TOKEN'])) {
            throw new \Exception("❌ Token de acesso do Magento não encontrado ou vazio. Verifique se foi gerado antes de instanciar o client.");
        }
        $this->accessToken = $_ENV['MAGENTO_ACCESS_TOKEN'];

        $this->client = new Client([
            'base_uri' => rtrim($_ENV['MAGENTO_API_URL'], '/') . '/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'verify' => false, // ATENÇÃO: Desabilitado para dev, mas considere os riscos em produção.
            'timeout' => 45,   // Timeout aumentado para operações mais longas como criação de produtos.
        ]);
    }

    public function getProductBySku(string $sku): ?array
    {
        try {
            $response = $this->client->get("V1/products/{$sku}");
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() == 404) {
                return null; // Produto não encontrado, não é um erro do script.
            }
            error_log("MagentoApiClient::getProductBySku - Erro ao buscar SKU {$sku}: " . $e->getMessage());
            if ($e->hasResponse()) {
                error_log("MagentoApiClient::getProductBySku - Response Body: " . $e->getResponse()->getBody()->getContents());
            }
            return null; // Retorna null em caso de outros erros.
        }
    }

    public function createOrUpdateProduct(array $payload): array
    {
        try {
            $response = $this->client->post('V1/products', [
                'json' => ['product' => $payload]
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'Sem corpo de resposta.';
            error_log("MagentoApiClient::createOrUpdateProduct - Erro: " . $e->getMessage() . " | Detalhes: " . $errorBody);
            
            $decodedBody = json_decode($errorBody, true);
            $errorMessage = $decodedBody['message'] ?? $e->getMessage();

            return [
                'error' => true,
                'message' => $errorMessage,
                'parameters' => $decodedBody['parameters'] ?? [],
                'response' => $errorBody,
            ];
        }
    }

    /**
     * ATUALIZA O ESTOQUE USANDO O ENDPOINT CORRETO DO MSI (Multi-Source Inventory)
     *
     * @param string $sku O SKU do produto.
     * @param float $quantity A quantidade em estoque.
     * @param int $status 1 para "Em Estoque", 0 para "Fora de Estoque".
     * @param string $sourceCode O código da fonte de estoque (geralmente 'default').
     * @return array
     */
    public function updateStockStatus(string $sku, float $quantity, int $status, string $sourceCode = 'default'): array
    {
        try {
            $payload = [
                'sourceItems' => [
                    [
                        'sku' => $sku,
                        'source_code' => $sourceCode,
                        'quantity' => $quantity,
                        'status' => $status,
                    ]
                ]
            ];

            // Este é o endpoint correto para o MSI
            $response = $this->client->post('V1/inventory/source-items', [
                'json' => $payload
            ]);

            return ['success' => true, 'message' => 'Estoque atualizado via MSI.'];

        } catch (RequestException $e) {
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'Sem corpo de resposta.';
            error_log("MagentoApiClient::updateStockStatus - Erro ao atualizar estoque para SKU {$sku}: " . $e->getMessage() . " | Detalhes: " . $errorBody);
            return [
                'error' => true,
                'message' => 'Erro na API de Estoque (MSI): ' . $e->getMessage(),
                'response' => $errorBody,
            ];
        }
    }

    /**
     * NOVO MÉTODO: Atualiza um ou mais atributos de um produto existente usando PUT.
     */
    public function updateProductAttributes(string $sku, array $attributes): array
    {
        try {
            $payload = [
                'product' => [
                    'custom_attributes' => $attributes
                ]
            ];

            // Usar PUT é mais específico para atualizações e pode contornar observers problemáticos.
            $response = $this->client->put("V1/products/{$sku}", [
                'json' => $payload
            ]);
            return ['success' => true, 'message' => 'Atributos atualizados.'];
        } catch (RequestException $e) {
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'Sem corpo de resposta.';
            error_log("MagentoApiClient::updateProductAttributes - Erro para SKU {$sku}: " . $e->getMessage() . " | Detalhes: " . $errorBody);
            return ['error' => true, 'message' => 'Erro na API ao atualizar atributos: ' . $e->getMessage()];
        }
    }


    public function uploadImageToProduct(string $sku, array $imagePayload): array
    {
        try {
            $response = $this->client->post("V1/products/{$sku}/media", [
                'json' => ['entry' => $imagePayload]
            ]);

            $responseBody = $response->getBody()->getContents();
            $decodedResponse = json_decode($responseBody, true);

            if (is_array($decodedResponse) && !empty($decodedResponse)) {
                return ['success' => true, 'media_id' => $decodedResponse['id'] ?? $decodedResponse, 'message' => 'Imagem enviada.'];
            }

            $trimmedBody = trim($responseBody, '"');
            if (is_numeric($trimmedBody) && $trimmedBody !== '') {
                return ['success' => true, 'media_id' => (int)$trimmedBody, 'message' => 'Imagem enviada (ID da string).'];
            }

            return [
                'error' => true,
                'message' => 'Resposta inesperada da API de mídia do Magento.',
                'response_body' => $responseBody
            ];
        } catch (RequestException $e) {
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'N/A';
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'response' => $errorBody,
            ];
        }
    }

    public function getProductImages(string $sku): ?array
    {
        try {
            $response = $this->client->get("V1/products/{$sku}/media");
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            error_log("MagentoApiClient::getProductImages - Erro ao obter imagens para SKU {$sku}: " . $e->getMessage());
            if ($e->hasResponse()) {
                error_log("MagentoApiClient::getProductImages - Response Body: " . $e->getResponse()->getBody()->getContents());
            }
            return null;
        }
    }

    public function deleteProductImage(string $sku, int $mediaEntryId): bool
    {
        try {
            $response = $this->client->delete("V1/products/{$sku}/media/{$mediaEntryId}");
            $responseBody = $response->getBody()->getContents();
            if ($responseBody === 'true' || (is_array(json_decode($responseBody, true)) && (json_decode($responseBody, true)['result'] ?? false) === true) ) {
                return true;
            }
            error_log("MagentoApiClient::deleteProductImage - Resposta inesperada ao deletar imagem ID {$mediaEntryId} para SKU {$sku}: " . $responseBody);
            return false;
        } catch (RequestException $e) {
            error_log("MagentoApiClient::deleteProductImage - Erro ao deletar imagem ID {$mediaEntryId} para SKU {$sku}: " . $e->getMessage());
            if ($e->hasResponse()) {
                error_log("MagentoApiClient::deleteProductImage - Response Body: " . $e->getResponse()->getBody()->getContents());
            }
            return false;
        }
    }


        public function updateProductImageRoles(string $sku, int $mediaEntryId, array $imagePayload): array
            {
                try {
                    $response = $this->client->put("V1/products/{$sku}/media/{$mediaEntryId}", [
                        'json' => ['entry' => $imagePayload]
                    ]);
                    return ['success' => true, 'message' => 'Roles da imagem atualizadas com sucesso.'];
                } catch (RequestException $e) {
                    $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'N/A';
                    return [
                        'error' => true,
                        'message' => $e->getMessage(),
                        'response' => $errorBody,
                    ];
                }
            }



    
    // Adicionei logs de erro também ao setPrecoPorGrupo para consistência
    public function setPrecoPorGrupo(string $sku, int $grupoId, float $preco): array
    {
        try {
            $payload = [
                [
                    'price' => $preco,
                    'website_id' => 0, // Considere se o website_id deve ser dinâmico ou 0 (all websites) é sempre correto
                    'cust_group' => $grupoId,
                    'price_qty' => 1 // Magento chama de 'qty' em tier prices, mas 'price_qty' em group-prices via API
                ]
            ];

            // O endpoint correto geralmente é /V1/products/tier-prices para group prices também,
            // ou um endpoint customizado/extension para "group-prices".
            // Verifique a documentação do Magento para o endpoint exato de "group-prices" se /V1/products/tier-prices não for.
            // Se /V1/products/group-prices for um endpoint específico, mantenha-o.
            // Para tier prices (que é o que parece estar sendo setado):
            // $endpoint = "V1/products/tier-prices";
            // $payloadFinal = ['prices' => $payload];
            // POST para adicionar/atualizar, DELETE para remover.

            // Mantendo seu endpoint original, mas atento à estrutura do payload e endpoint.
            $response = $this->client->post("V1/products/{$sku}/group-prices", [ // VERIFIQUE ESTE ENDPOINT
                'json' => $payload // O Magento espera um array de group prices aqui, mesmo que seja um só.
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'Sem corpo de resposta.';
            error_log("MagentoApiClient::setPrecoPorGrupo - Erro para SKU {$sku}, Grupo {$grupoId}: " . $e->getMessage() . " | Detalhes: " . $errorBody);
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'response' => $errorBody,
            ];
        }
    }


    /**
     * Força a marcação Base, Small e Thumbnail na primeira imagem do produto
     * no escopo global (All), resolvendo o bug do painel do Magento.
     */
    public function forceGlobalImageRoles(string $sku): array
    {
        try {
            // 1. Força a URL para o escopo Global (All)
            $apiUrl = rtrim($_ENV['MAGENTO_API_URL'], '/');
            if (strpos($apiUrl, '/rest/all') === false) {
                $apiUrl = str_replace('/rest', '/rest/all', $apiUrl);
                if (strpos($apiUrl, '/rest/all') === false) {
                     $apiUrl .= '/rest/all/';
                }
            }
            $apiUrl = rtrim($apiUrl, '/') . '/';

            // Cria um client Guzzle dedicado para a URL Global
            $globalClient = new \GuzzleHttp\Client([
                'base_uri' => $apiUrl,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'verify' => false
            ]);

            // 2. Busca o produto completo para ler as imagens atuais
            $response = $globalClient->get("V1/products/" . urlencode($sku));
            $produto = json_decode($response->getBody()->getContents(), true);

            if (empty($produto['media_gallery_entries'])) {
                 return ['error' => true, 'message' => 'O produto não possui imagens para aplicar as roles.'];
            }

            // 3. Pega a PRIMEIRA imagem da galeria (índice 0) e aplica as marcações
            $imagem = $produto['media_gallery_entries'][0];
            $imagem['types'] = ['image', 'small_image', 'thumbnail'];

            // 4. Monta o payload forçando os types
            $payload = [
                'product' => [
                    'sku' => $sku,
                    'media_gallery_entries' => [ $imagem ]
                ]
            ];

            // 5. Salva o produto via PUT forçando a atualização global
            $globalClient->put("V1/products/" . urlencode($sku), [
                'json' => $payload
            ]);

            return ['success' => true, 'message' => 'Roles (Base, Small, Thumbnail) marcadas no escopo global.'];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'N/A';
            error_log("MagentoApiClient::forceGlobalImageRoles - Erro no SKU {$sku}: " . $errorBody);
            return ['error' => true, 'message' => $e->getMessage(), 'response' => $errorBody];
        }
    }

}



