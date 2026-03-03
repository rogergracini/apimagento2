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
        $this->accessToken = $_ENV['MAGENTO_ACCESS_TOKEN'];

        $this->client = new Client([
            'base_uri' => rtrim($_ENV['MAGENTO_API_URL'], '/') . '/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'verify' => false // Em produção, idealmente true com HTTPS válido
        ]);
    }

    public function getProductBySku(string $sku): ?array
    {
        try {
            $response = $this->client->get("V1/products/{$sku}");
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            return null;
        }
    }

    public function createOrUpdateProduct(array $data): array
    {
        try {
            $response = $this->client->post('V1/products', [
                'json' => ['product' => $data]
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ];
        }
    }

    public function uploadImageToProduct(string $sku, array $imagePayload): array
    {
        try {
            $response = $this->client->post("V1/products/{$sku}/media", [
                'json' => ['entry' => $imagePayload]
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ];
        }
    }

    // ✅ NOVO: Define preço por grupo de cliente
    public function setPrecoPorGrupo(string $sku, int $groupId, float $preco): array
    {
        try {
            $endpoint = "V1/products/{$sku}/group-prices/{$groupId}";
            $payload = [
                'price' => $preco,
                'website_id' => 0,
                'customer_group_id' => $groupId
            ];

            $response = $this->client->post($endpoint, [
                'json' => $payload
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ];
        }
    }
}
