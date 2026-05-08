<?php

declare(strict_types=1);

use Devhammed\Byteship\Client;
use Devhammed\Byteship\Enums\Visibility;
use Devhammed\Byteship\ValueObjects\UploadProgress;
use Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$client = new Client(apiKey: $_ENV['BYTESHIP_API_KEY']);

$file = str_repeat("Hello Byteship!\n", 128);

$uploaded = $client->upload(
    $file,
    path: 'upload-text.txt',
    visibility: Visibility::Public,
    onProgress: function (UploadProgress $progress) {
        echo 'Upload text: '.round($progress->percent, 2).'% uploaded'.PHP_EOL;
    },
);

echo "Upload text: #{$uploaded->id} - {$uploaded->filename} - {$uploaded->byteSize} - {$uploaded->url}\n";

echo str_repeat('-', 100).PHP_EOL;

echo $client->downloadFile($uploaded->path);
