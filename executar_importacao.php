<?php

// Script para executar a importação de todos os produtos via Linha de Comando (CLI)

// Mostra todos os erros no terminal
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define um limite de tempo de execução mais alto (0 = sem limite)
set_time_limit(0);

// Aumenta o limite de memória para processos pesados.
ini_set('memory_limit', '512M');

// #################################################
// ###       FUNÇÃO DE ALERTA POR E-MAIL         ###
// #################################################
function enviarNotificacaoEmail($sku, $mensagemErro)
{
    // !!!!!!!!!!!!!  COLOQUE SEU E-MAIL AQUI  !!!!!!!!!!!!!
    $destinatario = "seu-email@dominio.com";
    // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

    $assunto = "ERRO FATAL na Importação do Magento - SKU: $sku";
    $corpo = "O script de importação encontrou um erro fatal ao processar o SKU: $sku\n\n";
    $corpo .= "Erro:\n$mensagemErro\n\n";
    $corpo .= "Data/Hora: " . date('Y-m-d H:i:s');
    $headers = 'From: noreply@crgr.com.br';
    try {
        @mail($destinatario, $assunto, $corpo, $headers);
    } catch (\Exception $e) {
        error_log("Falha ao enviar e-mail de notificação: " . $e->getMessage());
    }
}
// #################################################

echo "=================================================\n";
echo "INICIANDO SCRIPT DE IMPORTACAO EM MASSA\n";
echo "Data e Hora: " . date('Y-m-d H:i:s') . "\n";
echo "=================================================\n\n";

require __DIR__ . '/vendor/autoload.php';

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    echo "[OK] Variáveis de ambiente (.env) carregadas.\n";
} catch (\Throwable $e) {
    echo "[ERRO] Não foi possível carregar o arquivo .env. Abortando.\n";
    echo "Detalhes: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    echo "Extraindo arquivos do arq.zip...\n";
    App\Services\ZipExtractorService::extrairXmlDoZip('/home3/pratas31/crgr.com.br/arq.zip', __DIR__ . '/..');
    echo "[OK] Arquivos extraídos com sucesso.\n\n";
} catch (\Throwable $e) {
    echo "[ERRO] Falha ao extrair o arquivo ZIP. Abortando.\n";
    echo "Detalhes: " . $e->getMessage() . "\n";
    exit(1);
}

// --- ETAPA DE PRÉ-PROCESSAMENTO: Carregar XML e Token ---
$todosOsProdutos = [];
$totalDeProdutos = 0;
$produtoService = null; 

try {
    echo "Iniciando o servico de produtos e gerando o token de acesso...\n";
    $token = App\Utils\TokenHelper::gerarTokenAdmin();
    if (!$token) {
        throw new Exception("Falha crítica ao gerar o token de admin do Magento.");
    }
    echo "[OK] Token de acesso gerado com sucesso.\n";

    $produtoService = new App\Services\ProdutoService();
    echo "[OK] Servico de produtos instanciado.\n\n";
    
    $todosOsProdutos = $produtoService->carregarProdutosDoXml();
    $totalDeProdutos = count($todosOsProdutos);

    if ($totalDeProdutos === 0) {
        echo "Nenhum produto novo para processar. Encerrando.\n";
        exit(0);
    }

    echo "[INFO] {$totalDeProdutos} produtos prontos para processar.\n";

} catch (\Throwable $e) {
    $errorMsg = "Mensagem: " . $e->getMessage() . "\nArquivo: " . $e->getFile() . " Linha: " . $e->getLine();
    echo "\n!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
    echo "[ERRO FATAL NA PREPARAÇÃO] O script foi interrompido:\n";
    echo $errorMsg . "\n";
    echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
    enviarNotificacaoEmail("PREPARAÇÃO", $errorMsg);
    exit(1);
}

// --- ETAPA DE IMPORTAÇÃO E MANUTENÇÃO ---

define('MAGENTO_ROOT', '/home3/pratas31/crgr.com.br');
$flagFile = MAGENTO_ROOT . '/manutencao.flag';
$magentoCli = 'php ' . MAGENTO_ROOT . '/bin/magento';

// O Bloco `finally` garante que tudo será executado
try {
    // 1. ATIVA O MODO DE MANUTENÇÃO
    // Escreve o progresso inicial (0 de Total) no arquivo
    file_put_contents($flagFile, "0|{$totalDeProdutos}");

    echo "\n=================================================\n";
    echo "[POPUP ATIVADO] Site em modo de manutenção.\n";
    echo "PROCESSANDO $totalDeProdutos PRODUTOS...\n";
    echo "=================================================\n";

    $sucessos = 0;
    $erros = 0;
    $ignorados = 0;
    $contador = 0;

    // 2. Loop principal
    foreach ($todosOsProdutos as $produto) {
        $contador++;
        $sku = $produto['ProdutoID_Int'] ?? 'SKU_DESCONHECIDO';

        // **** MUDANÇA PRINCIPAL AQUI ****
        // Atualiza o arquivo de flag com o progresso atual
        // Isso permite que o popup (no refresh) mostre o progresso
        file_put_contents($flagFile, "{$contador}|{$totalDeProdutos}");
        // ******************************

        echo "Processando {$contador}/{$totalDeProdutos} - SKU: {$sku}... ";

        if (!is_array($produto) || !isset($produto['ProdutoID_Int'])) {
            $errorMsg = "[ERRO] Dados do produto inválidos do XML Parser.";
            echo $errorMsg . "\n";
            enviarNotificacaoEmail($sku, $errorMsg);
            $erros++;
            continue;
        }

        try {
            $resultado = $produtoService->importarProduto($produto);
            
            $status = strtoupper($resultado['status'] ?? 'ERRO');
            $mensagem = $resultado['mensagem'] ?? 'Sem mensagem.';
            $msgImagem = isset($resultado['mensagem_imagem']) ? ' | Imagem: ' . $resultado['mensagem_imagem'] : '';
            $mensagemCompleta = $mensagem . $msgImagem;

            echo "[{$status}] {$mensagemCompleta}\n";

            if ($status === 'IMPORTADO') {
                $sucessos++;
            } elseif ($status === 'IGNORADO') {
                $ignorados++;
            } else {
                enviarNotificacaoEmail($sku, $mensagemCompleta);
                $erros++;
            }

        } catch (\Throwable $eProduto) {
            $mensagemErroCompleta = "Exceção não capturada: " . $eProduto->getMessage();
            echo "[ERRO FATAL NO SKU] {$sku}: " . $mensagemErroCompleta . "\n";
            enviarNotificacaoEmail($sku, $mensagemErroCompleta);
            $erros++;
        }
    }

    echo "\n=================================================\n";
    echo "IMPORTACAO CONCLUIDA!\n";
    echo "Total de Produtos na Lista: " . $totalDeProdutos . "\n";
    echo "Sucessos: {$sucessos}\n";
    echo "Ignorados: {$ignorados}\n";
    echo "Erros: {$erros}\n";
    echo "=================================================\n";

} catch (\Throwable $eLoop) {
    // ... (resto do log de erro) ...
    $errorMsg = "Mensagem: " . $eLoop->getMessage() . "\nArquivo: " . $eLoop->getFile() . " Linha: " . $eLoop->getLine();
    echo "\n!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
    echo "[ERRO FATAL NO LOOP] O script foi interrompido:\n";
    echo $errorMsg . "\n";
    echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";    
    enviarNotificacaoEmail("LOOP PRINCIPAL", $errorMsg);

} finally {
    // 3. DESATIVA O MODO DE MANUTENÇÃO
    if (file_exists($flagFile)) {
        unlink($flagFile);
        echo "\n[POPUP DESATIVADO] Site fora do modo de manutenção.\n";
    }

    // 4. EXECUTA A LIMPEZA DE CACHE E REINDEXAÇÃO
    echo "-------------------------------------------------\n";
    echo "Iniciando reindexação de preços (catalog_product_price)...\n";
    
    $outputIndex = shell_exec($magentoCli . ' indexer:reindex catalog_product_price');
    echo $outputIndex;
    echo "[OK] Reindexação concluída.\n";
    
    echo "Iniciando limpeza de cache (cache:flush)...\n";
    $outputCache = shell_exec($magentoCli . ' cache:flush');
    echo $outputCache;
    echo "[OK] Limpeza de cache concluída.\n";
    
    echo "=================================================\n";
    echo "PROCESSO TOTALMENTE FINALIZADO.\n";
    echo "=================================================\n";
}

exit(0);