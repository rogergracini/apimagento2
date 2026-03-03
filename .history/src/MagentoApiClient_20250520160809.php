<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Utils\TokenHelper;

class MagentoApiClient
{
    private Client $client;
    private string $accessToken;

    public function __construct()
    {
        $this->accessToken = TokenHelper::gerarToken();

        $this->client = new Client([
            'base_uri' => rtrim($_ENV['MAGENTO_API_URL'], '/') . '/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'verify' => false
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

    public function setPrecoPorGrupo(string $sku, int $grupoId, float $preco): array
    {
        try {
            $payload = [
                [
                    'price' => $preco,
                    'website_id' => 0,
                    'cust_group' => $grupoId,
                    'price_qty' => 1
                ]
            ];

            $response = $this->client->post("V1/products/{$sku}/group-prices", [
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
