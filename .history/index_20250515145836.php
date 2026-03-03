<?php

require __DIR__ . '/vendor/autoload.php';

use Slim\Factory\AppFactory;

$app = AppFactory::create();

// ✅ INFORMA O BASE PATH CORRETO!
$app->setBasePath('/apimagento2');

$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Rota mínima de teste
$app->get('/ping', function ($request, $response) {
    $response->getBody()->write(json_encode(['pong' => true]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
