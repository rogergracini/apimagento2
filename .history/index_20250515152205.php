<?php

// Mostra erros detalhados
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

use DI\Container;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;

// ✅ Carrega variáveis do .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$container = new Container();
AppFactory::setContainer($container);

$app = AppFactory::create();

// ✅ Necessário para projetos em subpasta
$app->setBasePath('/apimagento2');

// Middlewares
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Middleware de autenticação
$app->add(function ($request, $handler) {
    $apiKey = $request->getHeaderLine('X-API-Key');
    if ($apiKey !== $_ENV['API_TOKEN']) {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }
    return $handler->handle($request);
});

// Rota de teste
$app->get('/ping', function ($request, $response) {
    $response->getBody()->write(json_encode(['pong' => true]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Rotas reais
(require __DIR__ . '/src/Controllers/ProdutoOneController.php')($app);

$app->run();
