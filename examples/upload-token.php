<?php

declare(strict_types=1);

use Devhammed\Byteship\Client;
use Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$client = new Client($_ENV['BYTESHIP_API_KEY']);

$token = $client->createUploadToken(
    folder: 'uploads',
    maxUploadBytes: 10 * 1024 * 1024,
);

echo $token->uploadToken->token;
