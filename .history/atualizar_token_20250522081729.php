<?php

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

$envPath = __DIR__ . '/.env';
$env = parse_ini_file($envPath);

$client = new Client([
    'verify' => false
]);

try {
    $response = $client->post($env['MAGENTO_API_URL'] . '/V1/integration/admin/token', [
        'json' => [
            'username' => $env['MAGENTO_ADMIN_USER'],
            'password' => $env['MAGENTO_ADMIN_PASS']
        ],
        'headers' => [
            'Content-Type' => 'application/json'
        ]
    ]);

    $token = trim((string) $response->getBody(), "\"\r\n");

    // Substitui ou atualiza MAGENTO_ACCESS_TOKEN no .env
    $envContents = file_get_contents($envPath);
    $envContents = preg_replace('/^MAGENTO_ACCESS_TOKEN=.*$/m', 'MAGENTO_ACCESS_TOKEN=' . $token, $envContents);
    file_put_contents($envPath, $envContents);

    echo "✅ Novo token salvo com sucesso: {$token}\n";

} catch (Exception $e) {
    echo "❌ Erro ao gerar token: " . $e->getMessage() . "\n";
}
