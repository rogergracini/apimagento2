<?php

namespace App\Utils;

use GuzzleHttp\Client;

class TokenHelper
{
    public static function gerarTokenAdmin(): ?string
    {
        try {
            $client = new Client(['verify' => false]);

            $url = rtrim($_ENV['MAGENTO_API_URL'], '/') . '/integration/admin/token';
            $response = $client->post($url, [
                'json' => [
                    'username' => $_ENV['MAGENTO_ADMIN_USER'],
                    'password' => $_ENV['MAGENTO_ADMIN_PASS']
                ],
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);

            $token = trim((string) $response->getBody(), "\"\r\n");

            // Atualiza a variável de ambiente na execução
            $_ENV['MAGENTO_ACCESS_TOKEN'] = $token;

            // Salvar no .env se quiser persistência (opcional)
            file_put_contents(__DIR__ . '/../../.env.token', $token);

            return $token;
        } catch (\Exception $e) {
            error_log('❌ Erro ao gerar token: ' . $e->getMessage());
            return null;
        }
    }
}
