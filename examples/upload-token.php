<?php

declare(strict_types=1);

use Devhammed\Byteship\Client;
use Devhammed\Byteship\Enums\Visibility;
use Devhammed\Byteship\ValueObjects\UploadProgress;
use Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$client = new Client($_ENV['BYTESHIP_API_KEY']);

$token = $client->createUploadToken(
    folder: 'uploads',
    maxUploadBytes: 10 * 1024 * 1024,
);

echo "Upload token: {$token->uploadToken->token}\n";

$publicClient = new Client(uploadToken: $token->uploadToken->token);

$file = str_repeat("Hello Byteship!\n", 128);

$uploaded = $publicClient->upload(
    $file,
    filename: 'upload-token.txt',
    contentType: 'text/plain',
    path: 'uploads/upload-token.txt',
    visibility: Visibility::Public,
    onProgress: function (UploadProgress $progress) {
        echo 'Upload token: '.round($progress->percent, 2).'% uploaded'.PHP_EOL;
    },
);

echo "Upload token: #{$uploaded->id} - {$uploaded->filename} - {$uploaded->byteSize} - {$uploaded->url}\n";

echo str_repeat('-', 100).PHP_EOL;

echo $client->downloadFile($uploaded->path);
