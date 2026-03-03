<?php

require __DIR__ . '/vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Psr7\Response;

$app = AppFactory::create();

$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// ✅ Rota de teste mínima
$app->get('/ping', function ($request, $response) {
    $response->getBody()->write(json_encode(['pong' => true]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
