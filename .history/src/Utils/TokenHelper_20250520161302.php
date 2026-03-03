<?php

namespace App\Utils;

use GuzzleHttp\Client;

class TokenHelper
{
    public static function gerarToken(): ?string
    {
        $client = new Client();

        try {
            $response = $client->post($_ENV['MAGENTO_API_URL'] . '/integration/admin/token', [
                'json' => [
                    'username' => 'integracao',
                    'password' => 'Ewdfh1k7@2025'
                ],
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'verify' => false
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            error_log('Erro ao gerar token Magento: ' . $e->getMessage());
            return null;
        }
    }
}
