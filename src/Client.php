<?php

declare(strict_types=1);

namespace Devhammed\Byteship;

use DateTimeImmutable;
use Devhammed\Byteship\Enums\FileStatus;
use Devhammed\Byteship\Enums\UploadManyResultStatus;
use Devhammed\Byteship\Enums\Visibility;
use Devhammed\Byteship\ValueObjects\CompleteUploadResponse;
use Devhammed\Byteship\ValueObjects\CreateSignedURLResponse;
use Devhammed\Byteship\ValueObjects\CreateUploadResponse;
use Devhammed\Byteship\ValueObjects\CreateUploadTokenResponse;
use Devhammed\Byteship\ValueObjects\DeletedFile;
use Devhammed\Byteship\ValueObjects\DeleteFileResponse;
use Devhammed\Byteship\ValueObjects\File;
use Devhammed\Byteship\ValueObjects\GetFileResponse;
use Devhammed\Byteship\ValueObjects\SignedURL;
use Devhammed\Byteship\ValueObjects\UploadedFile;
use Devhammed\Byteship\ValueObjects\UploadInput;
use Devhammed\Byteship\ValueObjects\UploadManyProgress;
use Devhammed\Byteship\ValueObjects\UploadManyResult;
use Devhammed\Byteship\ValueObjects\UploadProgress;
use Devhammed\Byteship\ValueObjects\UploadToken;
use GrahamCampbell\GuzzleFactory\GuzzleFactory;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\EachPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\MimeType;
use GuzzleHttp\Psr7\PumpStream;
use GuzzleHttp\Psr7\Utils;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Throwable;

class Client
{
    public const string DEFAULT_BASE_URL = 'https://api.byteship.dev/v1';

    public const string USER_AGENT = 'byteship-php/0.1.1';

    protected string $authToken;

    protected ClientInterface $client;

    protected string $baseUrl;

    protected float $timeout;

    /**
     * Create a new Client instance.
     */
    public function __construct(
        ?string $apiKey = null,
        ?string $uploadToken = null,
        ?ClientInterface $client = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        float $timeout = 60,
    ) {
        $authToken = $apiKey ?? $uploadToken;

        if ($authToken === null || $authToken === '') {
            throw new Error(
                'missing_auth_token',
                'Byteship API key or upload token is required.',
            );
        }

        $this->authToken = $authToken;
        $this->client = $client ?? new GuzzleClient(['handler' => GuzzleFactory::handler()]);
        $this->baseUrl = mb_rtrim($baseUrl, '/').'/';
        $this->timeout = $timeout;
    }

    public function createUploadToken(
        ?int $expiresInSeconds = null,
        ?string $folder = null,
        ?int $maxUploadBytes = null,
        ?Visibility $visibility = null,
    ): CreateUploadTokenResponse {
        $payload = $this->omitNulls([
            'expiresInSeconds' => $expiresInSeconds,
            'folder' => $folder,
            'maxUploadBytes' => $maxUploadBytes,
            'visibility' => $visibility,
        ]);

        $data = $this
            ->requestJsonAsync('POST', '/upload-tokens', $payload)
            ->wait();

        $token = $data['uploadToken'];

        return new CreateUploadTokenResponse(
            new UploadToken(
                $token['token'],
                new DateTimeImmutable($token['expiresAt']),
            ),
        );
    }

    public function createUpload(
        int $byteSize,
        string $contentType,
        string $filename,
        ?string $checksumSha256 = null,
        ?string $folder = null,
        ?array $metadata = null,
        ?Visibility $visibility = null,
    ): CreateUploadResponse {
        return $this->createUploadAsync(
            $byteSize,
            $contentType,
            $filename,
            $checksumSha256,
            $folder,
            $metadata,
            $visibility,
        )->wait();
    }

    public function createFileUpload(
        string $path,
        int $byteSize,
        string $contentType,
        ?string $checksumSha256 = null,
        ?array $metadata = null,
        ?Visibility $visibility = null,
    ): CreateUploadResponse {
        return $this->createFileUploadAsync(
            $path,
            $byteSize,
            $contentType,
            $checksumSha256,
            $metadata,
            $visibility,
        )->wait();
    }

    public function completeUpload(string $uploadId, string $fileId, string $key): CompleteUploadResponse
    {
        return $this
            ->completeUploadAsync($uploadId, $fileId, $key)
            ->wait();
    }

    public function createSignedUrl(string $filePathOrId, ?int $expiresInSeconds = null): CreateSignedURLResponse
    {
        $payload = $this->omitNulls(['expiresInSeconds' => $expiresInSeconds]);

        $data = $this->requestJsonAsync(
            'POST',
            '/files/'.$this->quoteFilePath($filePathOrId).'/signed-url',
            $payload,
        )->wait();

        $signedUrl = $data['signedUrl'];

        return new CreateSignedURLResponse(
            new SignedURL(
                $signedUrl['fileId'],
                $signedUrl['path'] ?? null,
                $signedUrl['url'],
                new DateTimeImmutable($signedUrl['expiresAt']),
            ),
        );
    }

    public function getFile(string $filePathOrId): GetFileResponse
    {
        $data = $this
            ->requestJsonAsync('GET', '/files/'.$this->quoteFilePath($filePathOrId))
            ->wait();

        $file = $data['file'];

        return new GetFileResponse(
            new File(
                $file['id'],
                $file['filename'],
                $file['path'],
                $file['byteSize'],
                $file['contentType'],
                $file['metadata'] ?? [],
                FileStatus::from($file['status']),
                $file['url'] ?? null,
                Visibility::from($file['visibility']),
                new DateTimeImmutable($file['createdAt']),
            ),
        );
    }

    public function downloadFile(string $filePathOrId): StreamInterface
    {
        $headers = $this->defaultHeaders();

        unset($headers['Accept']);

        try {
            $response = $this->client->request('GET', $this->buildUrl('/files/'.$this->quoteFilePath($filePathOrId)), [
                'headers' => $headers,
                'timeout' => $this->timeout,
                'http_errors' => false,
                'stream' => true,
            ]);
        } catch (GuzzleException $error) {
            throw new Error('api_request_failed', $error->getMessage());
        }

        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            $body = $response->getBody()->getContents();

            $data = $this->readJsonOrNull($body);

            throw Error::fromResponse($data, $status);
        }

        return $response->getBody();
    }

    public function deleteFile(string $filePathOrId): DeleteFileResponse
    {
        $data = $this
            ->requestJsonAsync('DELETE', '/files/'.$this->quoteFilePath($filePathOrId))
            ->wait();

        $file = $data['file'];

        return new DeleteFileResponse(
            new DeletedFile(
                $file['id'],
                $file['path'] ?? null,
                FileStatus::from($file['status']),
            ),
        );
    }

    /**
     * @param  StreamInterface|string|resource  $file
     * @param  callable(UploadProgress): void|null  $onProgress
     */
    public function upload(
        mixed $file,
        ?string $filename = null,
        ?string $contentType = null,
        ?int $byteSize = null,
        ?string $checksumSha256 = null,
        ?string $folder = null,
        ?array $metadata = null,
        ?string $path = null,
        ?Visibility $visibility = null,
        ?callable $onProgress = null,
    ): UploadedFile {
        return $this->uploadAsync(
            $file,
            $filename,
            $contentType,
            $byteSize,
            $checksumSha256,
            $folder,
            $metadata,
            $path,
            $visibility,
            $onProgress,
        )->wait();
    }

    /**
     * @param  iterable<UploadInput>  $files
     * @param  callable(UploadManyProgress): void|null  $onFileProgress
     * @return list<UploadManyResult>
     */
    public function uploadMany(
        iterable $files,
        int $concurrency = 3,
        ?string $folder = null,
        ?array $metadata = null,
        ?string $pathPrefix = null,
        ?Visibility $visibility = null,
        ?callable $onFileProgress = null,
    ): array {
        $fileList = is_array($files) ? array_values($files) : array_values(iterator_to_array($files));

        if ($fileList === []) {
            return [];
        }

        $results = array_fill(0, count($fileList), null);

        $promises = function () use ($fileList, $folder, $metadata, $pathPrefix, $visibility, $onFileProgress) {
            foreach ($fileList as $index => $item) {
                $progress = null;

                if ($onFileProgress !== null) {
                    $progress = function (UploadProgress $progressValue) use ($item, $index, $onFileProgress): void {
                        $onFileProgress(new UploadManyProgress(
                            $index,
                            $item,
                            $progressValue->loaded,
                            $progressValue->percent,
                            $progressValue->total,
                        ));
                    };
                }

                yield $index => $this->uploadAsync(
                    $item->file,
                    $item->filename,
                    $item->contentType,
                    $item->byteSize,
                    $item->checksumSha256,
                    $item->folder ?? $folder,
                    $item->metadata ?? $metadata,
                    $this->joinFilePath($pathPrefix, $item->path ?? $item->filename),
                    $item->visibility ?? $visibility,
                    $progress,
                );
            }
        };

        $each = new EachPromise($promises(), [
            'concurrency' => max(1, min($concurrency, count($fileList))),
            'fulfilled' => function (UploadedFile $uploaded, int $index) use ($fileList, &$results): void {
                $results[$index] = new UploadManyResult(
                    $fileList[$index],
                    UploadManyResultStatus::Fulfilled,
                    null,
                    $uploaded,
                );
            },
            'rejected' => function (mixed $reason, int $index) use ($fileList, &$results): void {
                $error = $reason instanceof Throwable ? $reason : new Error('upload_failed', 'Upload failed.');

                $results[$index] = new UploadManyResult(
                    $fileList[$index],
                    UploadManyResultStatus::Rejected,
                    $error,
                    null,
                );
            },
        ]);

        $each->promise()->wait();

        return array_values(array_filter($results));
    }

    /**
     * @param  string|resource  $file
     * @param  callable(UploadProgress): void|null  $onProgress
     */
    protected function uploadAsync(
        mixed $file,
        ?string $filename,
        ?string $contentType,
        ?int $byteSize,
        ?string $checksumSha256,
        ?string $folder,
        ?array $metadata,
        ?string $path,
        ?Visibility $visibility,
        ?callable $onProgress,
    ): PromiseInterface {
        if (is_resource($file) && (fstat($file)['mode'] & 010000) != 0) {
            $stream = new PumpStream(function ($length) use ($file) {
                $data = fread($file, $length);

                if (mb_strlen($data) === 0) {
                    return false;
                }

                return $data;
            });
        } else {
            $stream = Utils::streamFor($file);
        }

        $resolvedFilename = $this->resolveFilename($file, $filename, $stream);
        $resolvedContentType = $this->resolveContentType($resolvedFilename, $contentType);
        $resolvedByteSize = $this->resolveByteSize($stream, $byteSize);

        $createPromise = $path === null
            ? $this->createUploadAsync(
                $resolvedByteSize,
                $resolvedContentType,
                $resolvedFilename,
                $checksumSha256,
                $folder,
                $metadata,
                $visibility,
            )
            : $this->createFileUploadAsync(
                $path,
                $resolvedByteSize,
                $resolvedContentType,
                $checksumSha256,
                $metadata,
                $visibility,
            );

        return $createPromise
            ->then(fn (CreateUploadResponse $created) => $this->uploadToUrlAsync(
                $created->upload->url,
                $stream,
                $resolvedByteSize,
                $created->upload->headers,
                $onProgress,
            )->then(fn () => $this->completeUploadAsync(
                $created->upload->id,
                $created->file->id,
                $created->upload->key,
            )))
            ->then(fn (CompleteUploadResponse $completed) => $completed->file);
    }

    protected function createUploadAsync(
        int $byteSize,
        string $contentType,
        string $filename,
        ?string $checksumSha256,
        ?string $folder,
        ?array $metadata,
        ?Visibility $visibility,
    ): PromiseInterface {
        $payload = $this->omitNulls([
            'byteSize' => $byteSize,
            'checksumSha256' => $checksumSha256,
            'contentType' => $contentType,
            'filename' => $filename,
            'folder' => $folder,
            'metadata' => $metadata,
            'visibility' => $visibility,
        ]);

        return $this->requestJsonAsync('POST', '/uploads', $payload)
            ->then(fn (array $data) => CreateUploadResponse::fromArray($data));
    }

    protected function createFileUploadAsync(
        string $path,
        int $byteSize,
        string $contentType,
        ?string $checksumSha256,
        ?array $metadata,
        ?Visibility $visibility,
    ): PromiseInterface {
        $payload = $this->omitNulls([
            'byteSize' => $byteSize,
            'checksumSha256' => $checksumSha256,
            'contentType' => $contentType,
            'metadata' => $metadata,
            'visibility' => $visibility,
        ]);

        return $this->requestJsonAsync('PUT', '/files/'.$this->quoteFilePath($path), $payload)
            ->then(fn (array $data) => CreateUploadResponse::fromArray($data));
    }

    protected function completeUploadAsync(string $uploadId, string $fileId, string $key): PromiseInterface
    {
        return $this->requestJsonAsync(
            'POST',
            '/uploads/'.rawurlencode($uploadId).'/complete',
            ['fileId' => $fileId, 'key' => $key],
        )->then(fn (array $data) => CompleteUploadResponse::fromArray($data));
    }

    /**
     * @param  array<string, string>  $headers
     * @param  callable(UploadProgress): void|null  $onProgress
     */
    protected function uploadToUrlAsync(
        string $uploadUrl,
        StreamInterface $stream,
        int $byteSize,
        array $headers,
        ?callable $onProgress,
    ): PromiseInterface {
        $requestHeaders = array_merge($headers, [
            'Content-Length' => (string) $byteSize,
            'User-Agent' => self::USER_AGENT,
        ]);

        $progress = null;

        if ($onProgress !== null) {
            $progress = function (int $downloadTotal, int $downloaded, int $uploadTotal, int $uploaded) use ($byteSize, $onProgress): void {
                $onProgress(new UploadProgress(
                    $uploaded,
                    $byteSize <= 0 ? 0.0 : min(100.0, ($uploaded / $byteSize) * 100.0),
                    $byteSize,
                ));
            };
        }

        return $this->client
            ->requestAsync('PUT', $uploadUrl, [
                'headers' => $requestHeaders,
                'body' => $stream,
                'timeout' => $this->timeout,
                'http_errors' => false,
                'progress' => $progress,
            ])
            ->then(function (ResponseInterface $response) use ($byteSize, $onProgress) {
                $status = $response->getStatusCode();

                $body = $response->getBody()->getContents();

                if ($status < 200 || $status >= 300) {
                    throw new Error(
                        'upload_failed',
                        'Upload failed with status '.$status,
                        $this->readJsonOrNull($body),
                        $status,
                    );
                }

                if ($onProgress !== null) {
                    $onProgress(new UploadProgress($byteSize, 100.0, $byteSize));
                }

                return true;
            });
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function requestJsonAsync(string $method, string $path, ?array $payload = null): PromiseInterface
    {
        $options = [
            'headers' => $this->defaultHeaders(),
            'timeout' => $this->timeout,
            'http_errors' => false,
        ];

        if ($payload !== null) {
            $options['json'] = $payload;
        }

        return $this->client
            ->requestAsync($method, $this->buildUrl($path), $options)
            ->then(function (ResponseInterface $response) {
                $status = $response->getStatusCode();

                $body = $response->getBody()->getContents();

                $data = $this->readJsonOrNull($body);

                if ($status < 200 || $status >= 300) {
                    throw Error::fromResponse($data, $status);
                }

                return $data ?? [];
            })
            ->otherwise(function (Throwable $error) {
                throw new Error('api_request_failed', $error->getMessage());
            });
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->authToken,
            'User-Agent' => self::USER_AGENT,
        ];
    }

    protected function resolveFilename(mixed $file, ?string $filename, StreamInterface $stream): string
    {
        if ($filename !== null && $filename !== '') {
            return $filename;
        }

        if (is_resource($file)) {
            $meta = stream_get_meta_data($file);

            if (isset($meta['uri'])) {
                return basename($meta['uri']);
            }
        }

        $uri = $stream->getMetadata('uri');

        if (is_string($uri) && $uri !== '') {
            return basename($uri);
        }

        return 'upload';
    }

    protected function resolveContentType(string $filename, ?string $contentType): string
    {
        if ($contentType !== null && $contentType !== '') {
            return $contentType;
        }

        return MimeType::fromFilename($filename) ?: 'application/octet-stream';
    }

    protected function resolveByteSize(StreamInterface $stream, ?int $byteSize): int
    {
        if ($byteSize !== null) {
            if ($byteSize < 0) {
                throw new Error('invalid_byte_size', 'Upload byte size cannot be negative.');
            }

            return $byteSize;
        }

        if (! $stream->isSeekable()) {
            throw new Error(
                'missing_byte_size',
                'Upload byte size is required for non-seekable file objects.',
            );
        }

        $current = $stream->tell();
        $size = $stream->getSize();

        if ($size !== null) {
            $remaining = $size - $current;
            if ($remaining >= 0) {
                return $remaining;
            }
        }

        $stream->seek(0, SEEK_END);
        $end = $stream->tell();
        $stream->seek($current);
        $remaining = $end - $current;

        if ($remaining < 0) {
            throw new Error(
                'missing_byte_size',
                'Upload byte size is required for non-seekable file objects.',
            );
        }

        return $remaining;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function omitNulls(array $values): array
    {
        return array_filter($values, fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    protected function quoteFilePath(string $path): string
    {
        $segments = array_filter(explode('/', mb_trim($path, '/')));

        $encoded = array_map('rawurlencode', $segments);

        return implode('/', $encoded);
    }

    protected function joinFilePath(?string $prefix, ?string $filename): ?string
    {
        if ($prefix === null || $prefix === '') {
            return null;
        }

        $name = $filename ?? 'upload';

        $segments = [];

        foreach ([$prefix, $name] as $value) {
            foreach (array_filter(explode('/', mb_trim($value, '/'))) as $segment) {
                $segments[] = $segment;
            }
        }

        return implode('/', $segments);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readJsonOrNull(string $body): ?array
    {
        if ($body === '') {
            return null;
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    protected function buildUrl(string $path): string
    {
        return $this->baseUrl.mb_ltrim($path, '/');
    }
}
