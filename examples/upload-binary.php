<?php

declare(strict_types=1);

use Devhammed\Byteship\Client;
use Devhammed\Byteship\Enums\Visibility;
use Devhammed\Byteship\ValueObjects\UploadProgress;
use Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$client = new Client($_ENV['BYTESHIP_API_KEY']);

$file = fopen(__DIR__.'/devhammed.png', 'rb');

$uploaded = $client->upload(
    $file,
    filename: 'devhammed.png',
    contentType: 'image/png',
    path: 'devhammed.png',
    visibility: Visibility::Public,
    onProgress: function (UploadProgress $progress) {
        echo round($progress->percent, 2).'% uploaded'.PHP_EOL;
    },
);

echo "#{$uploaded->id} - {$uploaded->filename} - {$uploaded->byteSize} - {$uploaded->url}\n";
