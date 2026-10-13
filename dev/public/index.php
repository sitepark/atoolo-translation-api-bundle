<?php

declare(strict_types=1);

use Atoolo\TranslationApi\Dev\Kernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$kernel = new Kernel('dev', true);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
