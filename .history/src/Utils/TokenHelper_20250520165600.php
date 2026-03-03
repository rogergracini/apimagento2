<?php

namespace App\Utils;

use GuzzleHttp\Client;

class TokenHelper
{
    public static function gerarToken(): ?string
    {
        error_log('URL do Magento: ' . ($_ENV['MAGENTO_API_URL'] ?? 'não definida'));

        $client = new Client();

        try {
            // 🔒 Se quiser forçar direto para testar:
            $url = 'https://testeagencia.dev.br/rest/integration/admin/token';

            // Ou use a variável corretamente:
            // $url = rtrim($_ENV['MAGENTO_API_URL'], '/') . '/integration/admin/token';

            $response = $client->post($url, [
                'json' => [
                    'username' => 'integracao',
                    'password' => 'Ewdfh1k7@2025'
                ],
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'verify' => false
            ]);

            $body = (string) $response->getBody();
            return trim($body, "\"\r\n");

        } catch (\Exception $e) {
            error_log('Erro ao gerar token Magento: ' . $e->getMessage());
            return null;
        }
    }
}
