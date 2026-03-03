<?php

use Slim\App;
use App\Services\ZipExtractorService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\ProdutoService;
use App\Services\XmlParserService;

return function (App $app) {

    // Importa todos os produtos
    $app->post('/importar-produtos', function (Request $request, Response $response) {
        $produtoService = new ProdutoService();
        $xmlService = new XmlParserService();

        $produtos = $xmlService->carregarProdutos(
            '/home3/pratas31/testeagencia.dev.br/Produto.xml',
            '/home3/pratas31/testeagencia.dev.br/Preco.xml'
        );

        $resultado = $produtoService->importarProdutos($produtos);

        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'total_importados' => count($resultado),
            'skus' => $resultado
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    });

    // Consulta por SKU
    $app->get('/buscar-produto/{sku}', function (Request $request, Response $response, array $args) {
        $produtoService = new ProdutoService();
        $produto = $produtoService->buscarProdutoMagento($args['sku']);

        $response->getBody()->write(json_encode($produto));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // ✅ Importa um produto por SKU (com captura de erro)
    $app->post('/importar-produto/{sku}', function (Request $request, Response $response, array $args) {
        try {
            // ✅ Extrai o arq.zip antes de carregar XML
            ZipExtractorService::extrairXmlDoZip(
                '/home3/pratas31/testeagencia.dev.br/arq.zip',
                '/home3/pratas31/testeagencia.dev.br'
            );
            
            // Log de chamada da rota
            file_put_contents('/home3/pratas31/testeagencia.dev.br/apimagento2/logs/debug.log', "Rota chamada SKU: " . $args['sku'] . "\n", FILE_APPEND);

            $sku = $args['sku'];
            $produtoService = new ProdutoService();
            $xmlService = new XmlParserService();

            $produtos = $xmlService->carregarProdutos(
                '/home3/pratas31/testeagencia.dev.br/Produto.xml',
                '/home3/pratas31/testeagencia.dev.br/Preco.xml',
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
                'message' => 'Produto importado com sucesso',
                'detalhes' => $resultado
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

};
