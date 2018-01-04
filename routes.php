<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Routes

$app->get('/', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/test/[{name}]', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->debug(var_export ($args, true));
    $this->logger->debug("name : ".$args['name']);

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

require __DIR__ . '/gantt.php';
require __DIR__ . '/plantuml.php';

