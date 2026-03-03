<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\ProdutoService;
use App\Services\XmlParserService;

return function (App $app) {

    // Importa todos os produtos do XML
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

    // Consulta um produto no Magento por SKU
    $app->get('/buscar-produto/{sku}', function (Request $request, Response $response, array $args) {
        $produtoService = new ProdutoService();
        $produto = $produtoService->buscarProdutoMagento($args['sku']);

        $response->getBody()->write(json_encode($produto));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Importa apenas um produto específico do XML por SKU
    $app->post('/importar-produto/{sku}', function (Request $request, Response $response, array $args) {

    // ✅ Log para saber se a rota foi chamada
    file_put_contents(__DIR__ . '/../../../logs/debug.log', "Rota chamada SKU: " . $args['sku'] . "\n", FILE_APPEND);
    
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
    });

};
