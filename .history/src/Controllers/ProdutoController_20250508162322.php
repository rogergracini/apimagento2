<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\ProdutoService;
use App\Services\XmlParserService;

return function (App $app) {
    // Rota para importar produtos do XML
    $app->post('/importar-produtos', function (Request $request, Response $response) {
        $xmlService = new XmlParserService();
        $produtos = $xmlService->carregarProdutosXml();

        $produtoService = new ProdutoService();
        $resultado = $produtoService->importarProdutos($produtos);

        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'total_importados' => count($resultado),
            'skus' => $resultado
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    });

    // Rota para buscar produto no Magento por SKU
    $app->get('/buscar-produto/{sku}', function (Request $request, Response $response, array $args) {
        $produtoService = new ProdutoService();
        $produto = $produtoService->buscarProdutoMagento($args['sku']);

        $response->getBody()->write(json_encode($produto));
        return $response->withHeader('Content-Type', 'application/json');
    });
};
