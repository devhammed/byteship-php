<?php

declare(strict_types=1);

use Devhammed\Byteship\Client;
use Devhammed\Byteship\Enums\UploadManyResultStatus;
use Devhammed\Byteship\Enums\Visibility;
use Devhammed\Byteship\ValueObjects\UploadInput;
use Devhammed\Byteship\ValueObjects\UploadManyProgress;
use Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$client = new Client(apiKey: $_ENV['BYTESHIP_API_KEY']);

$files = [
    new UploadInput(
        str_repeat("Hello Byteship 1!\n", 256),
        path: 'upload-many-1.txt',
    ),
    new UploadInput(
        str_repeat("Hello Byteship 2!\n", 256),
        path: 'upload-many-2.txt',
    ),
];

$results = $client->uploadMany(
    $files,
    visibility: Visibility::Public,
    onFileProgress: function (UploadManyProgress $progress) {
        echo '#'.($progress->index + 1).' is now '.round($progress->percent, 2).'% uploaded'.PHP_EOL;
    },
);

foreach ($results as $result) {
    if ($result->status === UploadManyResultStatus::Fulfilled) {
        echo "#{$result->file->id} - {$result->file->filename} - {$result->file->byteSize} - {$result->file->url}\n";
    } else {
        echo "{$result->input->filename} failed: {$result->error->getMessage()}";
    }
}
