<?php

declare(strict_types=1);

use Devhammed\Byteship\Client;
use Devhammed\Byteship\Enums\FileStatus;
use Devhammed\Byteship\Enums\Visibility;
use Devhammed\Byteship\Error;
use Devhammed\Byteship\Flysystem\Adapter;
use Devhammed\Byteship\ValueObjects\CreateSignedURLResponse;
use Devhammed\Byteship\ValueObjects\DeletedFile;
use Devhammed\Byteship\ValueObjects\DeleteFileResponse;
use Devhammed\Byteship\ValueObjects\File;
use Devhammed\Byteship\ValueObjects\GetFileResponse;
use Devhammed\Byteship\ValueObjects\SignedURL;
use Devhammed\Byteship\ValueObjects\UploadedFile;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;

beforeEach(function () {
    $client = Mockery::mock(Client::class);

    Storage::extend('byteship', function (Application $app, array $config) use ($client) {
        $adapter = new Adapter(
            $client,
            $config['visibility'] ?? 'public',
            $config['prefix'] ?? '',
            $config['directory_separator'] ?? '/',
        );

        return new FilesystemAdapter(
            new Filesystem($adapter, $config),
            $adapter,
            $config,
        );
    });

    $this->client = $client;
});

it('can write', function () {
    $this->client
        ->shouldReceive('upload')
        ->once()
        ->andReturn(new UploadedFile(
            id: '1234567890',
            filename: 'test.txt',
            path: 'test.txt',
            byteSize: 42,
            etag: null,
            status: FileStatus::Ready,
            url: null,
            visibility: Visibility::Public,
        ));

    expect(Storage::put('test.txt', 'test content'))->toBeTrue();
});

it('can write a stream', function () {
    $this->client
        ->shouldReceive('upload')
        ->once()
        ->andReturn(new UploadedFile(
            id: '1234567890',
            filename: 'test.txt',
            path: 'test.txt',
            byteSize: 42,
            etag: null,
            status: FileStatus::Ready,
            url: null,
            visibility: Visibility::Public,
        ));

    expect(Storage::put('test.txt', tmpfile()))->toBeTrue();
});

it('can handle a failing write', function () {
    Config::set('filesystems.disks.byteship.throw', true);

    $this->client
        ->shouldReceive('upload')
        ->andThrow(new Error('upload_error', 'Unable to upload.'));

    Storage::write('test.txt', 'Hello Byteship!');
})->throws(UnableToWriteFile::class);

it('can work with attributes', function (string $attribute, mixed $expected) {
    $this->client
        ->shouldReceive('getFile')
        ->once()
        ->andReturn(new GetFileResponse(
            new File(
                id: '1234567890',
                filename: 'test.txt',
                path: 'test.txt',
                byteSize: 42,
                contentType: 'text/plain',
                metadata: [],
                status: FileStatus::Ready,
                url: null,
                visibility: Visibility::Public,
                createdAt: new DateTimeImmutable,
            ),
        ));

    expect(Storage::$attribute('test.txt'))->toBe($expected);
})->with([
    ['visibility', 'public'],
    ['mimeType', 'text/plain'],
    ['size', 42],
    ['fileSize', 42],
]);

it('can handle a failing attribute', function (string $attribute) {
    Config::set('filesystems.disks.byteship.throw', true);

    $this->client
        ->shouldReceive('getFile')
        ->andThrow(new Error('not_found', 'File does not exist.'));

    Storage::$attribute('test.txt', 'Hello Byteship!');
})
    ->with(['visibility', 'mimeType', 'size', 'fileSize'])
    ->throws(UnableToRetrieveMetadata::class);

it('can read', function () {
    $stream = tmpfile();
    fwrite($stream, 'returndata');
    rewind($stream);

    $this->client
        ->shouldReceive('downloadFile')
        ->once()
        ->andReturn(Utils::streamFor($stream));

    expect(Storage::read('test.txt'))->toContain('returndata');
});

it('can read a stream', function () {
    $stream = tmpfile();
    fwrite($stream, 'returndata');
    rewind($stream);

    $this->client
        ->shouldReceive('downloadFile')
        ->once()
        ->andReturn(Utils::streamFor($stream));

    $result = Storage::readStream('test.txt');

    expect($result)->toBeResource()
        ->and(stream_get_contents($result))->toBe('returndata');

    fclose($result);
});

it('can handle a failing read', function () {
    Config::set('filesystems.disks.byteship.throw', true);

    $this->client
        ->shouldReceive('downloadFile')
        ->andThrow(new Error('download_error', 'Unable to download.'));

    Storage::read('test.txt');
})->throws(UnableToReadFile::class);

it('can delete', function () {
    $this->client
        ->shouldReceive('deleteFile')
        ->once()
        ->andReturn(new DeleteFileResponse(
            new DeletedFile(
                id: '1234567890',
                path: 'test.txt',
                status: FileStatus::Deleted,
            ),
        ));

    expect(Storage::delete('test.txt'))->toBeTrue();
});

it('can handle a failing delete', function () {
    Config::set('filesystems.disks.byteship.throw', true);

    $this->client
        ->shouldReceive('deleteFile')
        ->andThrow(new Error('delete_error', 'Unable to delete.'));

    Storage::delete('test.txt');
})->throws(UnableToDeleteFile::class);

it('can move', function () {
    $stream = tmpfile();
    fwrite($stream, 'returndata');
    rewind($stream);

    $this->client
        ->shouldReceive('downloadFile')
        ->once()
        ->andReturn(Utils::streamFor($stream));

    $this->client
        ->shouldReceive('upload')
        ->once()
        ->andReturn(new UploadedFile(
            id: '1234567890',
            filename: 'hello.txt',
            path: 'hello.txt',
            byteSize: 42,
            etag: null,
            status: FileStatus::Ready,
            url: null,
            visibility: Visibility::Public,
        ));

    $this->client
        ->shouldReceive('deleteFile')
        ->once()
        ->andReturn(new DeleteFileResponse(
            new DeletedFile(
                id: '1234567890',
                path: 'test.txt',
                status: FileStatus::Deleted,
            ),
        ));

    expect(Storage::move('test.txt', 'hello.txt'))->toBeTrue();
});

it('can handle a failing move', function () {
    Config::set('filesystems.disks.byteship.throw', true);

    $this->client
        ->shouldReceive('downloadFile')
        ->andThrow(new Error('download_error', 'Unable to download.'));

    Storage::move('test.txt', 'hello.txt');
})->throws(UnableToMoveFile::class);

it('can copy', function () {
    $stream = tmpfile();
    fwrite($stream, 'returndata');
    rewind($stream);

    $this->client
        ->shouldReceive('downloadFile')
        ->once()
        ->andReturn(Utils::streamFor($stream));

    $this->client
        ->shouldReceive('upload')
        ->once()
        ->andReturn(new UploadedFile(
            id: '1234567890',
            filename: 'hello.txt',
            path: 'hello.txt',
            byteSize: 42,
            etag: null,
            status: FileStatus::Ready,
            url: null,
            visibility: Visibility::Public,
        ));

    expect(Storage::copy('test.txt', 'hello.txt'))->toBeTrue();
});

it('can handle a failing copy', function () {
    Config::set('filesystems.disks.byteship.throw', true);

    $this->client
        ->shouldReceive('downloadFile')
        ->andThrow(new Error('download_error', 'Unable to download.'));

    Storage::copy('test.txt', 'hello.txt');
})->throws(UnableToCopyFile::class);

it('can get public URL', function () {
    $this->client
        ->shouldReceive('getFile')
        ->once()
        ->andReturn(new GetFileResponse(
            new File(
                id: '1234567890',
                filename: 'test.txt',
                path: 'test.txt',
                byteSize: 42,
                contentType: 'text/plain',
                metadata: [],
                status: FileStatus::Ready,
                url: 'https://cdn.byteship.dev/f/12345/test.txt',
                visibility: Visibility::Public,
                createdAt: new DateTimeImmutable,
            ),
        ));

    expect(Storage::url('test.txt'))->toBe('https://cdn.byteship.dev/f/12345/test.txt');
});

it('can get private URL', function () {
    $this->client
        ->shouldReceive('createSignedUrl')
        ->once()
        ->andReturn(new CreateSignedURLResponse(
            new SignedURL(
                '1234567',
                'test.txt',
                'https://cdn.byteship.dev/f/12345/test.txt?token=1234567',
                new DateTimeImmutable,
            )
        ));

    expect(Storage::temporaryUrl('test.txt', now()->addHour()))->toBe('https://cdn.byteship.dev/f/12345/test.txt?token=1234567');
});
