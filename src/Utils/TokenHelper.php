<?php

namespace App\Utils;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException; // Adicionado para capturar exceções Guzzle

class TokenHelper
{
    public static function gerarTokenAdmin(): ?string
    {
        try {
            // Verificar se as variáveis de ambiente essenciais estão carregadas
            if (empty($_ENV['MAGENTO_API_URL'])) {
                error_log('❌ TokenHelper: Variável de ambiente MAGENTO_API_URL não está definida.');
                return null;
            }
            if (empty($_ENV['MAGENTO_ADMIN_USER'])) {
                error_log('❌ TokenHelper: Variável de ambiente MAGENTO_ADMIN_USER não está definida.');
                return null;
            }
            if (empty($_ENV['MAGENTO_ADMIN_PASS'])) {
                error_log('❌ TokenHelper: Variável de ambiente MAGENTO_ADMIN_PASS não está definida.');
                return null;
            }

            $client = new Client([
                'verify' => false, // Desabilita a verificação SSL. Mantenha apenas se necessário e ciente dos riscos.
                'timeout' => 10, // Timeout para a requisição em segundos
            ]);

            // CORREÇÃO DA URL:
            // Se MAGENTO_API_URL = https://crgr.com.br/rest
            // A URL correta para o token é MAGENTO_API_URL + /V1/integration/admin/token
            $url = rtrim($_ENV['MAGENTO_API_URL'], '/') . '/V1/integration/admin/token';

            error_log("TokenHelper: Tentando gerar token na URL: " . $url);
            error_log("TokenHelper: Usuario: " . $_ENV['MAGENTO_ADMIN_USER']);

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

            if (empty($token)) {
                error_log('❌ TokenHelper: Token recebido do Magento está vazio após requisição bem-sucedida (Status: ' . $response->getStatusCode() . ').');
                return null;
            }

            // Atualiza a variável de ambiente na execução
            $_ENV['MAGENTO_ACCESS_TOKEN'] = $token;

            // Salvar no .env.token é opcional e pode ser problemático em ambientes concorrentes ou sem permissão.
            // Considere se realmente precisa persistir o token dessa forma a cada geração.
            // file_put_contents(__DIR__ . '/../../.env.token', $token);

            error_log("✅ TokenHelper: Token gerado com sucesso (início): " . substr($token, 0, 10) . "...");
            return $token;

        } catch (RequestException $e) {
            error_log('❌ TokenHelper: RequestException ao gerar token: ' . $e->getMessage());
            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()->getBody()->getContents();
                error_log('❌ TokenHelper: Response Status Code: ' . $e->getResponse()->getStatusCode());
                error_log('❌ TokenHelper: Response Body: ' . $responseBody);
                // Tentar decodificar se for JSON para melhor leitura do erro do Magento
                $decodedBody = json_decode($responseBody, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decodedBody['message'])) {
                    error_log('❌ TokenHelper: Mensagem de erro Magento: ' . $decodedBody['message']);
                    if (isset($decodedBody['parameters'])) {
                         error_log('❌ TokenHelper: Parâmetros do erro Magento: ' . print_r($decodedBody['parameters'], true));
                    }
                }
            } else {
                error_log('❌ TokenHelper: Nenhuma resposta recebida do servidor.');
            }
            return null;
        } catch (\Exception $e) {
            error_log('❌ TokenHelper: Exception geral ao gerar token: ' . $e->getMessage());
            error_log('❌ TokenHelper: Tipo da Exceção: ' . get_class($e));
            error_log('❌ TokenHelper: Arquivo: ' . $e->getFile() . ' Linha: ' . $e->getLine());
            error_log('❌ TokenHelper: Stack Trace: ' . $e->getTraceAsString());
            return null;
        }
    }
}