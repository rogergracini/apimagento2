<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\ProdutoService;
use App\Services\XmlParserService;

return function (App $app) {

    $app->post('/importar-produtos', function (Request $request, Response $response) {
        $produtoService = new ProdutoService();
        $xmlService = new XmlParserService();

        // Carrega produtos do XML
        $produtos = $xmlService->carregarProdutos('/home3/pratas31/testeagencia.dev.br/Produto.xml', '/home3/pratas31/testeagencia.dev.br/Preco.xml');

        // Processa importação
        $resultado = $produtoService->importarProdutos($produtos);

        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'total_importados' => count($resultado),
            'skus' => $resultado
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('/buscar-produto/{sku}', function (Request $request, Response $response, array $args) {
        $produtoService = new ProdutoService();
        $produto = $produtoService->buscarProdutoMagento($args['sku']);

        $response->getBody()->write(json_encode($produto));
        return $response->withHeader('Content-Type', 'application/json');
    });

};
