# Byteship PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/devhammed/byteship.svg?style=flat-square)](https://packagist.org/packages/devhammed/byteship)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/devhammed/byteship/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/devhammed/byteship/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/devhammed/byteship/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/devhammed/byteship/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/devhammed/byteship.svg?style=flat-square)](https://packagist.org/packages/devhammed/byteship)

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

```php
use Devhammed\Byteship\Client;

$client = new Client($_ENV['BYTESHIP_API_KEY']);

$token = $client->createUploadToken(
    folder: "uploads",
    maxUploadBytes: 10 * 1024 * 1024,
);

echo $token->uploadToken->token;
```

### Upload a File

```php
use Devhammed\Byteship\Client;
use Devhammed\Byteship\Enums\Visibility;
use Devhammed\Byteship\ValueObjects\UploadProgress;

$client = new Client($_ENV['BYTESHIP_API_KEY']);

$file = fopen('photo.jpg', 'rb');

try {
    $uploaded = $client->upload(
        $file,
        filename: "photo.jpg",
        contentType: "image/jpeg",
        path: "uploads/photo.jpg",
        visibility: Visibility::Public,
        onProgress: function (UploadProgress $progress) {
            echo round($progress->percent) . '% uploaded';
        },
    );
} finally {
    fclose($file);
}

echo "#{$uploaded->id} - {$uploaded->url}";
```

### Errors

```php
use Devhammed\Byteship\Client;
use Devhammed\Byteship\Error;

$client = new Client($_ENV['BYTESHIP_API_KEY']);

try {
    $client->createUploadToken(
        folder: "uploads",
        maxUploadBytes: 10 * 1024 * 1024,
    );
} catch (Error $error) {
    echo "Error creating upload token: {$error->getError()} - {$error->getStatus()} - {$error->getMessage()}";
}
```

You can check this [folder](./examples) for more usage examples.

### Laravel

This package ships with a service provider for Laravel that will automatically setup the client for your application.

To get started, create an environment variable named `BYTESHIP_API_KEY` with your Byteship API key:

```bash
// .env
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
