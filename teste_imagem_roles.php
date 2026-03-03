<?php
// Inclui o autoloader do Composer para carregar suas classes
require_once __DIR__ . '/vendor/autoload.php';

// Ajuste o namespace abaixo conforme o que você usa no seu MagentoApiClient
use App\MagentoApiClient; 

try {
    // Instancia o seu cliente da API
    $client = new MagentoApiClient();
    
    $sku = '06-1124-1';
    echo "Buscando imagens do SKU: {$sku}...\n";

    // 1. Busca as mídias (imagens) já cadastradas no produto
    $response = $client->get("products/" . urlencode($sku) . "/media");
    $imagens = json_decode($response, true);

    // Verifica se retornou erro ou se não tem imagens
    if (isset($imagens['message'])) {
        die("Erro na API: " . $imagens['message'] . "\n");
    }
    if (empty($imagens)) {
        die("Nenhuma imagem encontrada no produto para atualizar.\n");
    }

    // 2. Pega a primeira imagem cadastrada
    $imagemAlvo = $imagens[0];
    $entryId = $imagemAlvo['id'];
    $arquivoImg = $imagemAlvo['file'];

    echo "Imagem encontrada! ID: {$entryId} | Arquivo: {$arquivoImg}\n";
    echo "Aplicando as roles (Base, Small, Thumbnail)...\n";

    // 3. Monta o payload para atualizar APENAS as roles dessa imagem
    $payload = [
        'entry' => [
            'id' => $entryId,
            'media_type' => 'image',
            'label' => $imagemAlvo['label'] ?? 'Imagem do Produto',
            'position' => 1,
            'disabled' => false,
            // É aqui que a mágica acontece: informamos os tipos que queremos marcar!
            'types' => ['image', 'small_image', 'thumbnail'], 
            'file' => $arquivoImg
        ]
    ];

    // 4. Dispara o PUT para atualizar a entrada de mídia específica
    $updateEndpoint = "products/" . urlencode($sku) . "/media/{$entryId}";
    $updateResponse = $client->put($updateEndpoint, json_encode($payload));
    
    $resultado = json_decode($updateResponse, true);

    if (isset($resultado['message'])) {
        echo "Erro ao atualizar: " . $resultado['message'] . "\n";
    } else {
        echo "Sucesso! A imagem foi marcada como Base, Small e Thumbnail no Magento.\n";
    }

} catch (Exception $e) {
    echo "Erro na execução: " . $e->getMessage() . "\n";
}