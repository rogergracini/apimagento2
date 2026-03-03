<?php

require __DIR__ . '/vendor/autoload.php';

use DI\Container;
use Slim\Factory\AppFactory;

$container = new Container();
AppFactory::setContainer($container);

$app = AppFactory::create();

// ✅ Fundamental no seu caso
$app->setBasePath('/apimagento2');

// Middlewares
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Middleware de autenticação (se ainda estiver usando)
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

// ✅ Aqui você reativa suas rotas reais
(require __DIR__ . '/src/Controllers/ProdutoController.php')($app);

$app->run();
