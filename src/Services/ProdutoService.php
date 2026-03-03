<?php

namespace App\Services;

use App\MagentoApiClient;
use App\Services\XmlParserService;
use App\Services\ProcessadoService;
use App\Services\PrecoService;
use App\Services\CategoriaService;
use App\Services\ImagemService;
use App\Utils\SlugHelper;

class ProdutoService
{
    protected MagentoApiClient $magentoClient;
    protected XmlParserService $xmlService;
    protected ImagemService $imagemService;

    public function __construct()
    {
        $this->magentoClient = new MagentoApiClient();
        $this->xmlService = new XmlParserService();
        $this->imagemService = new ImagemService();
    }

    public function carregarProdutosDoXml(): array
    {
        // Esta função está correta e não precisa de alterações.
        $produtoXml = __DIR__ . '/../../../Produto.xml';
        $precoXml = __DIR__ . '/../../../Preco.xml';
        $imagemXml = __DIR__ . '/../../../Imagem.xml';
        $timestamps = $this->xmlService->carregarTimestampsDasImagens($imagemXml);
        echo "-------------------------------------------------\n";
        echo "VERIFICANDO ARQUIVOS XML...\n";
        $caminhoAbsolutoProduto = @realpath(dirname($produtoXml)) . DIRECTORY_SEPARATOR . basename($produtoXml);
        echo "Procurando por Produto.xml em: " . $caminhoAbsolutoProduto . "\n";
        if (file_exists($produtoXml) && is_readable($produtoXml)) {
            echo "[OK] Arquivo Produto.xml ENCONTRADO e legível. Tamanho: " . filesize($produtoXml) . " bytes.\n";
        } else {
            echo "[ERRO] Arquivo Produto.xml NÃO ENCONTRADO ou sem permissão de leitura.\n";
        }
        echo "\n";
        $caminhoAbsolutoPreco = @realpath(dirname($precoXml)) . DIRECTORY_SEPARATOR . basename($precoXml);
        echo "Procurando por Preco.xml em: " . $caminhoAbsolutoPreco . "\n";
        if (file_exists($precoXml) && is_readable($precoXml)) {
            echo "[OK] Arquivo Preco.xml ENCONTRADO e legível. Tamanho: " . filesize($precoXml) . " bytes.\n";
        } else {
            echo "[ERRO] Arquivo Preco.xml NÃO ENCONTRADO ou sem permissão de leitura.\n";
        }
        echo "-------------------------------------------------\n";
        $produtos = $this->xmlService->carregarProdutos($produtoXml, $precoXml, $timestamps);
        if (empty($produtos)) {
            echo "\nAVISO: Nenhum produto foi carregado pelo XmlParserService. A lista de produtos está vazia.\n\n";
        } else {
            echo "\nINFO: " . count($produtos) . " produtos carregados do XML. Iniciando processamento individual...\n\n";
        }
        return $produtos;
    }

    public function importarProduto(array $produto): array
{
    $sku = $produto['ProdutoID_Int'] ?? null;
    
    $resultadoFinal = [
        'status' => 'erro_desconhecido',
        'mensagem' => 'Ocorreu uma falha inesperada no processamento do SKU: ' . ($sku ?? 'N/A'),
        'status_imagem' => 'N/A',
        'mensagem_imagem' => 'N/A'
    ];

    try {
        if (!$sku) {
            $resultadoFinal['status'] = 'erro';
            $resultadoFinal['mensagem'] = 'SKU ausente no array do produto.';
            return $resultadoFinal;
        }
        
        // Lista de SKUs específicos que devem ser sempre ignorados
        $skusParaIgnorar = [
            '05-004', '05-004AG', '05-007', '05-007AG', '05-2560DAG',
            '05-161A', '05-1176TAG', '05-539', '05-558', '05-558AG'
        ];

        // Condição para ignorar:
        // 1. Se o SKU estiver na lista $skusParaIgnorar
        // OU
        // 2. Se o SKU contiver "BR"
        if (in_array($sku, $skusParaIgnorar) || stripos($sku, 'BR') !== false) {
            $resultadoFinal['status'] = 'ignorado';
            $resultadoFinal['mensagem'] = 'SKU está na lista para ignorar ou contém "BR"';
            return $resultadoFinal;
        }
        
        

            $isAtivo = !(isset($produto['Ativo']) && strtolower((string)$produto['Ativo']) === 'false');
            $statusInterno = $isAtivo ? 1 : 2;

            $precoService = new PrecoService();
            //$tipoProduto = (stripos($sku, 'AG') !== false) ? 'AG' : 'OURO'; essa lógica não estava funcionando corretamente
            
            // LÓGICA CORRIGIDA:
            // MaterialID_Int == 2 significa 'AG' (Prata), qualquer outra coisa é 'OURO'
            $materialID = $produto['MaterialID_Int'] ?? null;
            $tipoProduto = ($materialID === '2') ? 'AG' : 'OURO';
            
            $porGrama = isset($produto['PrecoGrama']) && (string)$produto['PrecoGrama'] === '1';
            $pesoFinal = (float)($produto['Peso'] ?? 1);
            $gruposBrutosParaCalculo = $produto['groups_for_m1_logic'] ?? [];
            $calculo = $precoService->calcularPrecosComLogicaM1($gruposBrutosParaCalculo, $tipoProduto, $pesoFinal, $porGrama);
            $precoAtual = $calculo['preco_base'];

            $processadoService = new ProcessadoService();
            if ($processadoService->jaProcessado($sku, $precoAtual, $statusInterno)) {
                $resultadoFinal['status'] = 'ignorado';
                $resultadoFinal['mensagem'] = 'SKU já processado sem alterações de preço ou status';
                return $resultadoFinal;
            }

            // PASSO 0: Processa a imagem (UMA ÚNICA VEZ) e descobre se é válida
            $resultadoImagem = $this->imagemService->gerenciarImagemProduto($sku, $produto['ImagemID'] ?? null, true, $produto['imagem_timestamp'] ?? null);
            $temFotoValue = $resultadoImagem['foto_valida'] ? 1 : 0; // 1 para Sim, 0 para Não
            
            $nomeFormatado = trim("{$produto['Descricao']} - {$produto['TipoID_Int']} - {$produto['Largura_MM']}mm x {$produto['Altura_MM']}mm - {$produto['Peso']}gr");

            $categoriasArray = CategoriaService::obterCategorias($produto);
            $categoryLinks = [];
            foreach ($categoriasArray as $index => $categoryId) {
                $categoryLinks[] = ['position' => $index, 'category_id' => (string)$categoryId];
            }

            // Atributos para a criação principal (SEM o 'tem_foto')
            $customAttributes = [
                ['attribute_code' => 'url_key', 'value' => SlugHelper::gerarSlug("{$produto['Descricao']} {$sku}")],
                ['attribute_code' => 'description', 'value' => $nomeFormatado],
                ['attribute_code' => 'short_description', 'value' => $nomeFormatado],
                ['attribute_code' => 'tax_class_id', 'value' => 0]
            ];
            
            $payload = [
                'sku' => $sku,
                'name' => $nomeFormatado,
                'price' => $precoAtual,
                'status' => 1,
                'type_id' => 'simple',
                'attribute_set_id' => 16,
                'weight' => (float)($produto['Peso'] ?? 0),
                'visibility' => 4,
                'custom_attributes' => $customAttributes,
                'extension_attributes' => [
                    'category_links' => $categoryLinks,
                    'website_ids' => [1],

                    // ### ADICIONE ESTE BLOCO ABAIXO ###
                    'stock_item' => [
                        'is_qty_decimal' => 0, // <-- A CORREÇÃO ESTÁ AQUI
                        'manage_stock' => 1,
                        'use_config_manage_stock' => 0,
                        'is_in_stock' => $isAtivo ? 1 : 0
                    ]
                    // ### FIM DO BLOCO ADICIONADO ###

                ],
                'tier_prices' => $calculo['tier_prices']
            ];

            // PASSO 1: Salva o produto principal
            $respostaProduto = $this->magentoClient->createOrUpdateProduct($payload);
            if (isset($respostaProduto['error']) && $respostaProduto['error']) {
                throw new \Exception('API do Magento retornou erro ao salvar produto: ' . ($respostaProduto['message'] ?? 'Erro desconhecido'));
            }

            // PASSO 2: Atualiza o estoque
            $stockQty = $isAtivo ? 500 : 0;
            $stockStatus = $isAtivo ? 1 : 0;
            $respostaEstoque = $this->magentoClient->updateStockStatus($sku, $stockQty, $stockStatus);

            // PASSO 3: Atualiza o atributo 'tem_foto' em uma chamada separada e dedicada
            $atributosParaAtualizar = [['attribute_code' => 'tem_foto', 'value' => $temFotoValue]];
            $respostaAtributo = $this->magentoClient->updateProductAttributes($sku, $atributosParaAtualizar);

            // Monta a mensagem final com o feedback de todas as etapas
            $mensagemFinal = 'Produto OK. ';

            if (isset($respostaEstoque['success']) && $respostaEstoque['success']) {
                // Mensagem específica para o status do estoque
                $mensagemFinal .= ($isAtivo ? 'Estoque OK (500). ' : 'Estoque ZERADO (0). ');
            } else {
                $mensagemFinal .= 'Estoque FALHOU. ';
            }

            $mensagemFinal .= (isset($respostaAtributo['success']) && $respostaAtributo['success']) ? 'Tem Foto OK.' : 'Tem Foto FALHOU.';
            
            $processadoService->marcarComoProcessado($sku, $precoAtual, $statusInterno);

            $resultadoFinal['status'] = 'importado';
            $resultadoFinal['mensagem'] = $mensagemFinal;
            // Usa o resultado da imagem que já foi processado no início
            $resultadoFinal['status_imagem'] = $resultadoImagem['status'] ?? 'N/A';
            $resultadoFinal['mensagem_imagem'] = $resultadoImagem['mensagem'] ?? 'N/A';

        } catch (\Throwable $e) {
            $resultadoFinal['status'] = 'erro_fatal';
            $resultadoFinal['mensagem'] = 'Exceção: ' . $e->getMessage();
            error_log("ERRO FATAL no processamento do SKU {$sku}: " . $e->getMessage() . " no arquivo " . $e->getFile() . " na linha " . $e->getLine());
        }

        return $resultadoFinal;
    }
    
    public function buscarProdutoMagento(string $sku): ?array
    {
        return $this->magentoClient->getProductBySku($sku);
    }
}