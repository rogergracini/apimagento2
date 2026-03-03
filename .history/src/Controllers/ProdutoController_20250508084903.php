<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\ProdutoService;

return function (App $app) {

    // Rota de importação de produtos a partir dos XML
    $app->post('/importar-produtos', function (Request $request, Response $response) {
        $produtoService = new ProdutoService();

        // Pega os dados enviados no corpo da requisição
        $data = $request->getParsedBody();
        $produtos = $data['produtos'] ?? [];

        $resultado = $produtoService->importarProdutos($produtos);

        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'total_importados' => count($resultado),
            'skus' => $resultado
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    });

    // Rota para buscar um produto pelo SKU via API Magento
    $app->get('/buscar-produto/{sku}', function (Request $request, Response $response, array $args) {
        $produtoService = new ProdutoService();
        $produto = $produtoService->buscarProdutoMagento($args['sku']);

        $response->getBody()->write(json_encode($produto));
        return $response->withHeader('Content-Type', 'application/json');
    });

};
