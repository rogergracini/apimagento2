<?php

use DI\Container;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;
use Slim\Psr7\Factory\StreamFactory;

require __DIR__ . '/../vendor/autoload.php';

// Carrega variáveis de ambiente do .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Cria container para injeção de dependência
$container = new Container();
AppFactory::setContainer($container);

// Cria app Slim
$app = AppFactory::create();

// Middleware para leitura de corpo JSON
$app->addBodyParsingMiddleware();

// Middleware para tratamento de exceções
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Middleware de autenticação com API_TOKEN
$app->add(function ($request, $handler) {
    $apiKey = $request->getHeaderLine('X-API-Key');

    if ($apiKey !== $_ENV['API_TOKEN']) {
        $response = new Response();
        $stream = (new StreamFactory())->createStream(json_encode(['error' => 'Unauthorized']));

        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);
    }

    return $handler->handle($request);
});



// Rota de teste (ping)
$app->get('/ping', function ($request, $response) {
    $response->getBody()->write(json_encode(['pong' => true]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Carrega as rotas da API (ProdutoController)
(require __DIR__ . '/../src/Controllers/ProdutoController.php')($app);

// Executa app
$app->run();
