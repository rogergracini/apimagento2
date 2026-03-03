<?php
// Remove o limite de tempo do PHP para permitir rodar todos os 12 mil SKUs
set_time_limit(0);

// Inclui o autoloader do Composer
require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Carrega as variáveis de ambiente do .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ========================================================
// SISTEMA DE CHECKPOINT (RETOMAR DE ONDE PAROU)
// ========================================================
$arquivoLog = __DIR__ . '/skus_processados.txt';
$skusJaProcessados = [];

if (file_exists($arquivoLog)) {
    // Lê o arquivo e joga num array para busca super rápida
    $linhas = file($arquivoLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($linhas as $linha) {
        $skusJaProcessados[trim($linha)] = true;
    }
}
$totalJaFeito = count($skusJaProcessados);

echo "Iniciando verificação em massa de 12 mil SKUs...\n";
if ($totalJaFeito > 0) {
    echo "=> Encontrado log anterior. Retomando processamento (PULANDO {$totalJaFeito} SKUs já feitos).\n";
}
echo "Isso pode levar bastante tempo. Não feche o terminal!\n";
echo "=================================================\n";

// Monta a URL Global (All) para forçar o painel do Magento a ler
$apiUrl = rtrim($_ENV['MAGENTO_API_URL'], '/');
if (strpos($apiUrl, '/rest/all') === false) {
    $apiUrl = str_replace('/rest', '/rest/all', $apiUrl);
    if (strpos($apiUrl, '/rest/all') === false) {
         $apiUrl .= '/rest/all/';
    }
}
$apiUrl = rtrim($apiUrl, '/') . '/';
$token = $_ENV['MAGENTO_ACCESS_TOKEN'];

// Cria o Client HTTP independente
$client = new Client([
    'base_uri' => $apiUrl,
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
    ],
    'verify' => false,
    'timeout' => 30
]);

$pageSize = 100; // Processa de 100 em 100 produtos
$currentPage = 1;

$metricas = [
    'verificados' => 0,
    'corrigidos' => 0,
    'ignorados' => 0,
    'erros' => 0
];

while (true) {
    echo "\n>>> Buscando Lote {$currentPage}...\n";
    
    // Busca na API apenas o SKU dos produtos dessa página
    $searchEndpoint = "V1/products?searchCriteria[pageSize]={$pageSize}&searchCriteria[currentPage]={$currentPage}&fields=items[sku]";
    
    try {
        $response = $client->get($searchEndpoint);
        $dados = json_decode($response->getBody()->getContents(), true);
        
        if (empty($dados['items'])) {
            break; // Acabaram os produtos do Magento
        }

        foreach ($dados['items'] as $item) {
            $sku = $item['sku'];
            
            // VERIFICA SE JÁ FOI PROCESSADO EM UMA EXECUÇÃO ANTERIOR
            if (isset($skusJaProcessados[$sku])) {
                // Nem imprime na tela para não poluir, apenas passa rápido
                continue;
            }

            $metricas['verificados']++;
            echo str_pad("-> SKU: {$sku}", 30, " ");

            try {
                // Busca o produto completo para ver a imagem
                $prodResponse = $client->get("V1/products/" . urlencode($sku));
                $produto = json_decode($prodResponse->getBody()->getContents(), true);

                if (empty($produto['media_gallery_entries'])) {
                    echo "[Sem fotos]\n";
                    $metricas['ignorados']++;
                    // Salva no log para não verificar de novo
                    file_put_contents($arquivoLog, $sku . PHP_EOL, FILE_APPEND);
                    continue;
                }

                $imagem = $produto['media_gallery_entries'][0];
                $rolesAtuais = $imagem['types'] ?? [];

                // Verifica se já tem as 3 roles
                if (in_array('image', $rolesAtuais) && in_array('small_image', $rolesAtuais) && in_array('thumbnail', $rolesAtuais)) {
                    echo "[OK, já estava certo]\n";
                    $metricas['ignorados']++;
                    // Salva no log para não verificar de novo
                    file_put_contents($arquivoLog, $sku . PHP_EOL, FILE_APPEND);
                    continue;
                }

                // Se chegou aqui, precisa corrigir!
                $imagem['types'] = ['image', 'small_image', 'thumbnail'];
                $payload = [
                    'product' => [
                        'sku' => $sku,
                        'media_gallery_entries' => [ $imagem ]
                    ]
                ];

                // Salva forçando no escopo global
                $client->put("V1/products/" . urlencode($sku), [
                    'json' => $payload
                ]);

                echo "[CORRIGIDO COM SUCESSO!]\n";
                $metricas['corrigidos']++;
                
                // Salva no log como concluído
                file_put_contents($arquivoLog, $sku . PHP_EOL, FILE_APPEND);

            } catch (RequestException $e) {
                echo "[ERRO NA API - Será tentado novamente depois]\n";
                $metricas['erros']++;
                // NOTA: Se der erro, NÃO adicionamos no arquivo. Assim ele tenta de novo da próxima vez.
            }
        }
        
        $currentPage++; 

    } catch (RequestException $e) {
         echo "Erro fatal ao buscar a página de produtos. Parando o script.\n";
         break;
    }
}

echo "\n=================================================\n";
echo "PROCESSO FINALIZADO NESSA EXECUÇÃO!\n";
echo "Lidos agora: {$metricas['verificados']}\n";
echo "Corrigidos agora: {$metricas['corrigidos']}\n";
echo "Já corretos / Sem imagem: {$metricas['ignorados']}\n";
echo "Erros (não salvos no log): {$metricas['erros']}\n";
echo "=================================================\n";