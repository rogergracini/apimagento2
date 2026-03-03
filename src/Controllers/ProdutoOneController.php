<?php

// Adicione esta linha junto com os outros 'use' statements no topo do arquivo:
use App\Utils\TokenHelper;
use Slim\App;
use App\Services\ZipExtractorService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\ProdutoService;
use App\Services\XmlParserService;

return function (App $app) {

    // Importa todos os produtos
    $app->post('/importar-produtos', function (Request $request, Response $response) {
        try {
            // Gerar/Atualizar token de acesso ANTES de qualquer operação da API
            $newToken = TokenHelper::gerarTokenAdmin();
            if (!$newToken) {
                error_log("Falha ao gerar token de admin do Magento para importação em lote.");
                $response->getBody()->write(json_encode([
                    'error' => true,
                    'message' => 'Falha crítica: Não foi possível gerar o token de acesso do Magento.'
                ]));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
            // O token é atualizado em $_ENV['MAGENTO_ACCESS_TOKEN'] pelo TokenHelper

            // ✅ Extrai o arq.zip antes de carregar XML (Assumindo que é necessário aqui também)
            // Se não for necessário para importação em lote, pode remover esta parte da extração.
             ZipExtractorService::extrairXmlDoZip(
                 '/home3/pratas31/crgr.com.br/arq.zip',
                 '/home3/pratas31/crgr.com.br'
             );

            $produtoService = new ProdutoService();
            // A linha abaixo que carregava $produtos e depois chamava $produtoService->importarProdutos($produtos)
            // parecia estar sobrescrita pela chamada sem argumentos.
            // Se você precisa carregar os produtos do XML aqui, use a primeira forma.
            // $xmlService = new XmlParserService();
            // $produtos = $xmlService->carregarProdutos(
            //     '/home3/pratas31/crgr.com.br/Produto.xml',
            //     '/home3/pratas31/crgr.com.br/Preco.xml'
            // );
            // $resultado = $produtoService->importarProdutos($produtos); // Se você passar os produtos carregados

            // Ou, se importarProdutos() em ProdutoService já lida com o carregamento do XML:
            $resultado = $produtoService->importarProdutos();


            $response->getBody()->write(json_encode([
                'status' => 'ok',
                'total_importados' => count($resultado),
                'skus' => $resultado
            ]));

            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'error' => true,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Consulta por SKU
    $app->get('/buscar-produto/{sku}', function (Request $request, Response $response, array $args) {
        try {
            // Gerar/Atualizar token de acesso ANTES de qualquer operação da API
            $newToken = TokenHelper::gerarTokenAdmin();
            if (!$newToken) {
                error_log("Falha ao gerar token de admin do Magento para buscar SKU: " . ($args['sku'] ?? 'N/A'));
                $response->getBody()->write(json_encode([
                    'error' => true,
                    'message' => 'Falha crítica: Não foi possível gerar o token de acesso do Magento.'
                ]));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }

            $produtoService = new ProdutoService();
            $produto = $produtoService->buscarProdutoMagento($args['sku']);

            $response->getBody()->write(json_encode($produto));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'error' => true,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // ✅ Importa um produto por SKU (com captura de erro)
    $app->post('/importar-produto/{sku}', function (Request $request, Response $response, array $args) {
        try {
            // ✅ Gerar/Atualizar token de acesso ANTES de qualquer operação da API
            $newToken = TokenHelper::gerarTokenAdmin();
            if (!$newToken) {
                // Log e retorna erro se o token não puder ser gerado
                error_log("Falha ao gerar token de admin do Magento para SKU: " . ($args['sku'] ?? 'N/A'));
                $response->getBody()->write(json_encode([
                    'error' => true,
                    'message' => 'Falha crítica: Não foi possível gerar o token de acesso do Magento.'
                ]));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
            // O token é atualizado em $_ENV['MAGENTO_ACCESS_TOKEN'] pelo TokenHelper

            // ✅ Extrai o arq.zip antes de carregar XML
            ZipExtractorService::extrairXmlDoZip(
                '/home3/pratas31/crgr.com.br/arq.zip',
                '/home3/pratas31/crgr.com.br'
            );
            
            // Log de chamada da rota
            // Movi o log para depois da geração do token e extração do zip, pois são etapas críticas
            file_put_contents('/home3/pratas31/crgr.com.br/apimagento2/logs/debug.log', "Rota /importar-produto/{$args['sku']} chamada. Token gerado. ZIP extraído.\n", FILE_APPEND);

            $sku = $args['sku'];
            $produtoService = new ProdutoService(); // Agora ProdutoService usará o token atualizado em $_ENV
            $xmlService = new XmlParserService();

            $produtos = $xmlService->carregarProdutos(
                '/home3/pratas31/crgr.com.br/Produto.xml',
                '/home3/pratas31/crgr.com.br/Preco.xml',
                $sku
            );

            if (empty($produtos)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'sku' => $sku,
                    'message' => 'Produto não encontrado no XML'
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $resultado = $produtoService->importarProduto($produtos[0]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'sku' => $sku,
                // Removido 'message' que era "Produto importado com sucesso",
                // pois 'detalhes' já contém status e mensagem mais específicos.
                'detalhes' => $resultado 
            ]));

            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            // Log do erro completo também no arquivo de debug
            $errorMessage = sprintf(
                "Erro na rota /importar-produto/{%s}: %s no arquivo %s linha %d\nStack trace: %s\n",
                ($args['sku'] ?? 'N/A'),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            );
            file_put_contents('/home3/pratas31/crgr.com.br/apimagento2/logs/debug.log', $errorMessage, FILE_APPEND);

            $response->getBody()->write(json_encode([
                'error' => true,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

};