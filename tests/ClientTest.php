<?php

declare(strict_types=1);

use Devhammed\Byteship\Client;
use Devhammed\Byteship\Enums\FileStatus;
use Devhammed\Byteship\Enums\UploadManyResultStatus;
use Devhammed\Byteship\Enums\UploadMethod;
use Devhammed\Byteship\Enums\UploadSessionStatus;
use Devhammed\Byteship\Enums\Visibility;
use Devhammed\Byteship\Error;
use Devhammed\Byteship\ValueObjects\UploadInput;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

function makeClient(MockHandler $mock, array &$history): Client
{
    $stack = HandlerStack::create($mock);

    $stack->push(Middleware::history($history));

    return new Client(
        apiKey: 'test-key',
        client: new GuzzleClient(['handler' => $stack]),
    );
}

it('creates an upload token', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'uploadToken' => [
                'expiresAt' => '2024-01-01T00:00:00Z',
                'token' => 'token-123',
            ],
        ])),
    ]);
    $history = [];
    $client = makeClient($mock, $history);

    $response = $client->createUploadToken(expiresInSeconds: 3600, folder: 'invoices', maxUploadBytes: 123, visibility: Visibility::Private);

    expect($response->uploadToken->token)->toBe('token-123')
        ->and($history)->toHaveCount(1);
});

it('creates an upload', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'file' => [
                'id' => 'file_1',
                'path' => 'uploads/file.txt',
                'status' => 'pending',
                'url' => null,
            ],
            'upload' => [
                'expiresAt' => '2024-01-01T00:00:00Z',
                'fileId' => 'file_1',
                'headers' => ['x-test' => '1'],
                'id' => 'upload_1',
                'key' => 'key_1',
                'method' => 'single',
                'url' => 'https://uploads.example.com/put',
            ],
        ])),
    ]);
    $history = [];
    $client = makeClient($mock, $history);

    $response = $client->createUpload(10, 'text/plain', 'file.txt', visibility: Visibility::Public);

    expect($response->file->status)->toBe(FileStatus::Pending)
        ->and($response->upload->method)->toBe(UploadMethod::Single)
        ->and($history)->toHaveCount(1);
});

it('creates a file upload', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'file' => [
                'id' => 'file_2',
                'path' => 'docs/report.pdf',
                'status' => 'pending',
                'url' => null,
            ],
            'upload' => [
                'expiresAt' => '2024-01-01T00:00:00Z',
                'fileId' => 'file_2',
                'headers' => [],
                'id' => 'upload_2',
                'key' => 'key_2',
                'method' => 'single',
                'url' => 'https://uploads.example.com/put',
            ],
        ])),
    ]);
    $history = [];
    $client = makeClient($mock, $history);

    $response = $client->createFileUpload('docs/report.pdf', 10, 'application/pdf');

    expect($history)->toHaveCount(1)
        ->and($response->file->id)->toBe('file_2');
});

it('completes an upload', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'file' => [
                'byteSize' => 10,
                'etag' => 'etag',
                'filename' => 'file.txt',
                'id' => 'file_1',
                'path' => 'uploads/file.txt',
                'status' => 'ready',
                'url' => 'https://cdn.example.com/file.txt',
                'visibility' => 'public',
            ],
            'upload' => [
                'id' => 'upload_1',
                'status' => 'completed',
            ],
        ])),
    ]);
    $history = [];
    $client = makeClient($mock, $history);

    $response = $client->completeUpload('upload_1', 'file_1', 'key_1');

    expect($response->upload->status)->toBe(UploadSessionStatus::Completed)
        ->and($history)->toHaveCount(1);
});

it('creates a signed url', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'signedUrl' => [
                'expiresAt' => '2024-01-01T00:00:00Z',
                'fileId' => 'file_1',
                'path' => 'uploads/file.txt',
                'url' => 'https://cdn.example.com/signed',
            ],
        ])),
    ]);
    $history = [];
    $client = makeClient($mock, $history);

    $response = $client->createSignedUrl('uploads/file.txt');

    expect($response->signedUrl->url)->toBe('https://cdn.example.com/signed')
        ->and($history)->toHaveCount(1);
});

it('gets a file', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'file' => [
                'byteSize' => 10,
                'contentType' => 'text/plain',
                'createdAt' => '2024-01-01T00:00:00Z',
                'filename' => 'file.txt',
                'id' => 'file_1',
                'metadata' => ['a' => 'b'],
                'path' => 'uploads/file.txt',
                'status' => 'ready',
                'url' => 'https://cdn.example.com/file.txt',
                'visibility' => 'public',
            ],
        ])),
    ]);
    $history = [];
    $client = makeClient($mock, $history);

    $response = $client->getFile('uploads/file.txt');

    expect($response->file->status)->toBe(FileStatus::Ready)
        ->and($history)->toHaveCount(1);
});

it('downloads a file', function (): void {
    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'application/pdf'], 'file-bytes'),
    ]);
    $history = [];
    $client = makeClient($mock, $history);

    $stream = $client->downloadFile('invoices/2026/invoice.pdf');

    expect($stream)->toBeInstanceOf(StreamInterface::class)
        ->and($stream->getContents())->toBe('file-bytes');

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('GET')
        ->and($request->getHeaderLine('Accept'))->toBeEmpty();
});

it('deletes a file', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'file' => [
                'id' => 'file_1',
                'path' => 'uploads/file.txt',
                'status' => 'deleted',
            ],
        ])),
    ]);
    $history = [];
    $client = makeClient($mock, $history);

    $response = $client->deleteFile('uploads/file.txt');

    expect($response->file->status)->toBe(FileStatus::Deleted)
        ->and($history)->toHaveCount(1);
});

it('uploads a file', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'file' => [
                'id' => 'file_1',
                'path' => 'uploads/file.txt',
                'status' => 'pending',
                'url' => null,
            ],
            'upload' => [
                'expiresAt' => '2024-01-01T00:00:00Z',
                'fileId' => 'file_1',
                'headers' => [],
                'id' => 'upload_1',
                'key' => 'key_1',
                'method' => 'single',
                'url' => 'https://uploads.example.com/put',
            ],
        ])),
        new Response(200, [], ''),
        new Response(200, [], json_encode([
            'file' => [
                'byteSize' => 5,
                'etag' => 'etag',
                'filename' => 'file.txt',
                'id' => 'file_1',
                'path' => 'uploads/file.txt',
                'status' => 'ready',
                'url' => 'https://cdn.example.com/file.txt',
                'visibility' => 'public',
            ],
            'upload' => [
                'id' => 'upload_1',
                'status' => 'completed',
            ],
        ])),
    ]);
    $history = [];
    $client = makeClient($mock, $history);

    $file = $client->upload('Hello World', filename: 'file.txt');

    expect($file->status)->toBe(FileStatus::Ready)
        ->and($history)->toHaveCount(3);
});

it('uploads many files', function (): void {
    $responder = function (RequestInterface $request) {
        $method = $request->getMethod();
        $uri = $request->getUri();
        $path = $uri->getPath();
        $host = $uri->getHost();

        if ($method === 'POST' && str_ends_with($path, '/uploads')) {
            $body = $request->getBody()->getContents();
            $payload = json_decode($body, true);
            $filename = is_array($payload) ? ($payload['filename'] ?? null) : null;
            $index = (int) explode('-', $filename)[1];

            return new Response(200, [], json_encode([
                'file' => [
                    'id' => 'file_'.$index,
                    'path' => 'uploads/file-'.$index.'.txt',
                    'status' => 'pending',
                    'url' => null,
                ],
                'upload' => [
                    'expiresAt' => '2024-01-01T00:00:00Z',
                    'fileId' => 'file_'.$index,
                    'headers' => [],
                    'id' => 'upload_'.$index,
                    'key' => 'key_'.$index,
                    'method' => 'single',
                    'url' => 'https://uploads.example.com/put'.$index,
                ],
            ]));
        }

        if ($method === 'PUT' && $host === 'uploads.example.com') {
            if (str_contains((string) $uri, 'put2')) {
                return new Response(500, ['Content-Type' => 'application/json'], json_encode([
                    'error' => 'upload_failed',
                    'detail' => 'Upload failed.',
                ]));
            }

            return new Response(200, [], '');
        }

        if ($method === 'POST' && str_ends_with($path, '/uploads/upload_1/complete')) {
            return new Response(200, [], json_encode([
                'file' => [
                    'byteSize' => 5,
                    'etag' => 'etag-1',
                    'filename' => 'file-1.txt',
                    'id' => 'file_1',
                    'path' => 'uploads/file-1.txt',
                    'status' => 'ready',
                    'url' => 'https://cdn.example.com/file-1.txt',
                    'visibility' => 'public',
                ],
                'upload' => [
                    'id' => 'upload_1',
                    'status' => 'completed',
                ],
            ]));
        }

        return new Response(500, ['Content-Type' => 'application/json'], json_encode([
            'error' => 'unexpected_request',
            'detail' => 'Unexpected request in test.',
        ]));
    };
    $mock = new MockHandler(array_fill(0, 10, $responder));
    $history = [];
    $client = makeClient($mock, $history);

    $results = $client->uploadMany([
        new UploadInput('hello', filename: 'file-1.txt'),
        new UploadInput('world', filename: 'file-2.txt'),
    ]);

    expect($results)->toHaveCount(2)
        ->and($results[0]->status)->toBe(UploadManyResultStatus::Fulfilled)
        ->and($results[0]->file?->status)->toBe(FileStatus::Ready)
        ->and($results[1]->status)->toBe(UploadManyResultStatus::Rejected)
        ->and($results[1]->error)->toBeInstanceOf(Error::class)
        ->and($history)->toHaveCount(5);
});
