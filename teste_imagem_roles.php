<?php
// Inclui o autoloader do Composer
require_once __DIR__ . '/vendor/autoload.php';

use App\MagentoApiClient; 

try {
    $client = new MagentoApiClient();
    
    $sku = '06-1124-1';
    echo "Buscando imagens do SKU: {$sku}...\n";

    // 1. Busca as mídias usando a função que já existe no seu arquivo
    $imagens = $client->getProductImages($sku);

    // Verifica se retornou vazio
    if (empty($imagens) || isset($imagens['message'])) {
        die("Nenhuma imagem encontrada no produto para atualizar ou erro de API.\n");
    }

    // 2. Pega a primeira imagem cadastrada
    $imagemAlvo = $imagens[0];
    $entryId = $imagemAlvo['id'];
    $arquivoImg = $imagemAlvo['file'];

    echo "Imagem encontrada! ID: {$entryId} | Arquivo: {$arquivoImg}\n";
    echo "Aplicando as roles (Base, Small, Thumbnail)...\n";

    // 3. Monta o payload para atualizar as roles
    $payload = [
        'id' => $entryId,
        'media_type' => 'image',
        'label' => $imagemAlvo['label'] ?? 'Imagem do Produto',
        'position' => 1,
        'disabled' => false,
        'types' => ['image', 'small_image', 'thumbnail'], // Tipos que queremos forçar!
        'file' => $arquivoImg
    ];

    // 4. Chama a função nova que acabamos de adicionar
    $resultado = $client->updateProductImageRoles($sku, $entryId, $payload);

    if (isset($resultado['error'])) {
        echo "Erro ao atualizar: " . $resultado['message'] . "\n";
        echo "Detalhes: " . $resultado['response'] . "\n";
    } else {
        echo "Sucesso! Vá no painel do Magento e verifique se as 3 bolinhas estão marcadas.\n";
    }

} catch (Exception $e) {
    echo "Erro na execução: " . $e->getMessage() . "\n";
}