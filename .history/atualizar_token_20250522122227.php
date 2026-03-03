<?php

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$client = new Client(['verify' => false]);

try {
    $url = rtrim($_ENV['MAGENTO_API_URL'], '/') . '/V1/integration/admin/token';

    echo "🔍 Gerando token com URL: $url\n";

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

    // Atualiza o .env com o novo token
    $envPath = __DIR__ . '/.env';
    $envContents = file_get_contents($envPath);

    // Substitui ou adiciona a linha do token
    if (preg_match('/^MAGENTO_ACCESS_TOKEN=.*$/m', $envContents)) {
        $envContents = preg_replace('/^MAGENTO_ACCESS_TOKEN=.*$/m', 'MAGENTO_ACCESS_TOKEN=' . $token, $envContents);
    } else {
        $envContents .= "\nMAGENTO_ACCESS_TOKEN={$token}\n";
    }

    file_put_contents($envPath, $envContents);

    echo "✅ Novo token salvo com sucesso no .env: {$token}\n";

} catch (\Exception $e) {
    echo "❌ Erro ao gerar token: " . $e->getMessage() . "\n";
}
