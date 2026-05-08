# Byteship PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/devhammed/byteship-php.svg?style=flat-square)](https://packagist.org/packages/devhammed/byteship-php)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/devhammed/byteship-php/run-tests.yml?branch=develop&label=tests&style=flat-square)](https://github.com/devhammed/byteship-php/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/devhammed/byteship-php.svg?style=flat-square)](https://packagist.org/packages/devhammed/byteship-php)
[![Laravel Compatibility](https://badge.laravel.cloud/badge/devhammed/byteship-php)](https://packagist.org/packages/devhammed/byteship-php)

PHP client for the Byteship Upload API.

## Installation

You can install the package via composer:

```bash
composer require devhammed/byteship-php
```

## Usage

The first thing you need to do is to get an API key at [Byteship](https://byteship.dev/console). You'll find more info
at the [Byteship Docs](https://byteship.dev/docs).

### Create a Client

Create the client with a full project API key only on trusted server code. Browser code should use a short-lived upload
token minted by your backend.

#### Server client

```php
use Devhammed\Byteship\Client;

$byteship = new Client(apiKey: $_ENV['BYTESHIP_API_KEY']);
```

#### Upload client

```php
use Devhammed\Byteship\Client;

$byteship = new Client(uploadToken: 'bsut_...');
```

> Keep API keys server-side!
>
> Never ship a `bship_...` project API key to the browser. Use `createUploadToken` on your server and pass the returned
> `bsut_...` token to frontend code.

### Upload a File

Use `upload` when you want the SDK to create the upload session, send the bytes to storage, complete the upload, and
return the ready file.

```php
use Devhammed\Byteship\Client;
use Devhammed\Byteship\Enums\Visibility;
use Devhammed\Byteship\ValueObjects\UploadProgress;

$byteship = new Client(apiKey: $_ENV['BYTESHIP_API_KEY']);

$file = fopen('photo.jpg', 'rb');

$uploaded = $byteship->upload(
    $file,
    path: 'uploads/photo.jpg',
    visibility: Visibility::Public,
    metadata: [
        'user_id' => '123',
    ],
    onProgress: function (UploadProgress $progress) {
        echo round($progress->percent) . '% uploaded';
    },
);

echo "#{$uploaded->id} - {$uploaded->url}";
```

### Multiple Files

Use `uploadMany` for batches. Each result keeps the original file, a status, and either the uploaded file or a
`Byteship\Error`.

```php
use Devhammed\Byteship\Client;
use Devhammed\Byteship\Enums\Visibility;
use Devhammed\Byteship\Enums\UploadManyResultStatus;
use Devhammed\Byteship\ValueObjects\UploadManyProgress;
use Devhammed\Byteship\ValueObjects\UploadInput;

$byteship = new Client(apiKey: $_ENV['BYTESHIP_API_KEY']);

$files = [
    new UploadInput(fopen('photo-1.jpg', 'rb')),
    new UploadInput(fopen('photo-2.jpg', 'rb')),
    new UploadInput(fopen('photo-3.jpg', 'rb')),
    new UploadInput(fopen('photo-4.jpg', 'rb')),
    new UploadInput(fopen('photo-5.jpg', 'rb')),
    new UploadInput(fopen('photo-6.jpg', 'rb')),
];

$results = $byteship->uploadMany(
    $files,
    concurrency: 3,
    pathPrefix: 'gallery',
    visibility: Visibility::Public,
    metadata: [
        'user_id' => '123',
    ],
    onFileProgress: function (UploadManyProgress $progress) {
        echo '#'. $progress->index .': ' .round($progress->percent) . '% uploaded';
    },
);

$uploaded = array_map(
    fn($item) => $item->result,
    array_filter($results, fn($item) => $item->status === UploadManyResultStatus::Fulfilled),
);
```

### File Methods

Server clients can read file metadata, create temporary URLs for private files, download file, and delete stored files
when the credential has the required scope.

```php
use Devhammed\Byteship\Client;

$byteship = new Client(apiKey: $_ENV['BYTESHIP_API_KEY']);

$fileResponse = $byteship->getFile('uploads/photo.jpg');

echo 'File Status: ' . $fileResponse->file->status;

$signedResponse = $byteship->createSignedUrl(
    $fileResponse->file->path,
    expiresInSeconds: 10 * 60,
);

echo 'Signed URL: ' . $signedResponse->signedUrl->url;

$stream = $byteship->downloadFile($fileResponse->file->path);

file_put_contents('photo.jpg', $stream);

echo 'Download Size: ' . filesize('photo.jpg');

$deleted = $byteship->deleteFile($fileResponse->file->path);

echo 'File Status: ' . $deleted->file->status;
```

### Errors

Every SDK API request throws `Byteship\Error` for non-2xx responses.

```php
use Devhammed\Byteship\Client;
use Devhammed\Byteship\Error;

$byteship = new Client(apiKey: $_ENV['BYTESHIP_API_KEY']);

try {
    $byteship->createUploadToken(
        folder: 'uploads',
        maxUploadBytes: 10 * 1024 * 1024,
    );
} catch (Error $error) {
    echo "Error creating upload token: {$error->getError()} - {$error->getStatus()} - {$error->getMessage()}";
}
```

### Laravel

This package ships with a service provider for Laravel that will automatically setup the client for your application.

To get started, create an environment variable named `BYTESHIP_API_KEY` in your `.env` file with your Byteship API key:

```bash
BYTESHIP_API_KEY=your-api-key
```

Then, open `config/filesystems.php` and add the `byteship` disk configuration:

```php
return  [
    // ...

    'disks' => [
        'byteship' => [
            'driver' => 'byteship',
            'visibility' => 'public',
            'api_key' => env('BYTESHIP_API_KEY'),
        ],
    ],

    // ...
];
```

You should now be able to use the Byteship disk in your Laravel application just like any other storage drivers:

```php
use Illuminate\Support\Facades\Storage;

Storage::disk('byteship')->put('hello.txt', 'Hello, Byteship!'); // true/false
Storage::disk('byteship')->get('hello.txt'); // "Hello, Byteship!"
Storage::disk('byteship')->url('hello.txt'); // "https://cdn.byteship.dev/f/12345/hello.txt" (only for public files)
Storage::disk('byteship')->temporaryUrl('hello.txt', now()->addHour()); // "https://cdn.byteship.dev/f/12345/hello.txt?token=secret-token" (only for private files)
Storage::disk('byteship')->temporaryUploadUrl('hello.txt', now()->addHour(), ['byte_size' => 1024]) // ['file_id' => '123', 'upload_id' => '456', 'upload_token' => 'd34db33f', 'url' => 'https://...', 'complete_url' => 'https://...', 'headers' => ['content-type' => 'text/plain']] (use the complete URL + upload ID + upload_token to complete the upload after sending the file to the url + headers)
Storage::disk('byteship')->delete('hello.txt'); // true/false
Storage::disk('byteship')->exists('hello.txt'); // true/false
Storage::disk('byteship')->mimeType('hello.txt'); // "text/plain"
Storage::disk('byteship')->visibility('hello.txt'); // "public" / "private"
Storage::disk('byteship')->size('hello.txt'); // 16
```

> RECOMMENDED: You should set the default disk to `byteship` inside `config/filesystems.php` so you won't have to
> specify the disk name everytime you work with Storage.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Hammed Oyedele](https://github.com/devhammed)
- [Byteship](https://byteship.dev)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
