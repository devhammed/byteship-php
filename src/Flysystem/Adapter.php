<?php

declare(strict_types=1);

namespace Devhammed\Byteship\Flysystem;

use DateTimeImmutable;
use DateTimeInterface;
use Devhammed\Byteship\Client;
use Devhammed\Byteship\Enums\Visibility;
use Devhammed\Byteship\Error;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use League\Flysystem\UnableToListContents;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use RuntimeException;
use Throwable;

class Adapter implements FilesystemAdapter, PublicUrlGenerator, TemporaryUrlGenerator
{
    protected Client $client;

    protected string $visibility;

    protected PathPrefixer $prefixer;

    /**
     * Construct the ByteShip filesystem adapter.
     */
    public function __construct(
        Client $client,
        string $visibility,
        string $prefix = '',
        string $separator = '/',
    ) {
        $this->client = $client;
        $this->visibility = $visibility;
        $this->prefixer = new PathPrefixer($prefix, $separator);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * {@inheritDoc}
     */
    public function fileExists(string $path): bool
    {
        try {
            $this->getFileAttributes($path);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function directoryExists(string $path): bool
    {
        throw UnableToCheckDirectoryExistence::forLocation($path, new RuntimeException('Not supported.'));
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $this->client->upload(
                $contents,
                path: $this->prefixer->prefixPath($path),
                visibility: Visibility::from($config->get(Config::OPTION_VISIBILITY, $this->visibility)),
            );
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            $this->client->upload(
                $contents,
                path: $this->prefixer->prefixPath($path),
                visibility: Visibility::from($config->get(Config::OPTION_VISIBILITY, $this->visibility)),
            );
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function read(string $path): string
    {
        try {
            $contents = $this->client->downloadFile($this->prefixer->prefixPath($path));

            return $contents->getContents();
        } catch (Throwable $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function readStream(string $path)
    {
        try {
            $contents = $this->client->downloadFile($this->prefixer->prefixPath($path));

            return $contents->detach();
        } catch (Throwable $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $path): void
    {
        try {
            $this->client->deleteFile($this->prefixer->prefixPath($path));
        } catch (Throwable $exception) {
            throw UnableToDeleteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deleteDirectory(string $path): void
    {
        throw UnableToDeleteDirectory::atLocation($path, 'Not supported.');
    }

    /**
     * {@inheritDoc}
     */
    public function createDirectory(string $path, Config $config): void
    {
        throw UnableToCreateDirectory::atLocation($path, 'Not supported.');
    }

    /**
     * {@inheritDoc}
     */
    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Not supported.');
    }

    /**
     * {@inheritDoc}
     */
    public function visibility(string $path): FileAttributes
    {
        try {
            return $this->getFileAttributes($path);
        } catch (Throwable $exception) {
            throw UnableToRetrieveMetadata::visibility($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function mimeType(string $path): FileAttributes
    {
        try {
            return $this->getFileAttributes($path);
        } catch (Throwable $exception) {
            throw UnableToRetrieveMetadata::mimeType($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function lastModified(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::lastModified($path, 'Not supported.');
    }

    /**
     * {@inheritDoc}
     */
    public function fileSize(string $path): FileAttributes
    {
        try {
            return $this->getFileAttributes($path);
        } catch (Throwable $exception) {
            throw UnableToRetrieveMetadata::fileSize($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function listContents(string $path, bool $deep): iterable
    {
        throw UnableToListContents::atLocation($path, $deep, new RuntimeException('Not supported.'));
    }

    /**
     * {@inheritDoc}
     */
    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $stream = $this->readStream($source);

            $this->writeStream($destination, $stream, $config);

            $this->delete($source);
        } catch (Throwable $exception) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $stream = $this->readStream($source);

            $this->writeStream($destination, $stream, $config);
        } catch (Throwable $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    public function publicUrl(string $path, Config $config): string
    {
        try {
            $response = $this->client->getFile(
                $this->prefixer->prefixPath($path),
            );

            if ($response->file->visibility !== Visibility::Public) {
                throw UnableToGeneratePublicUrl::dueToError($path, new Error('private_file', 'File is not public.'));
            }

            if ($response->file->url === null) {
                throw UnableToGeneratePublicUrl::dueToError($path, new Error('missing_url', 'File URL is missing.'));
            }

            return $response->file->url;
        } catch (Throwable $exception) {
            throw UnableToGeneratePublicUrl::dueToError($path, $exception);
        }
    }

    public function getUrl(string $path): string
    {
        return $this->publicUrl($this->prefixer->stripPrefix($path), new Config);
    }

    public function temporaryUrl(string $path, DateTimeInterface $expiresAt, Config $config): string
    {
        try {
            $now = new DateTimeImmutable;

            $response = $this->client->createSignedUrl(
                $this->prefixer->prefixPath($path),
                $expiresAt->getTimestamp() - $now->getTimestamp(),
            );

            return $response->signedUrl->url;
        } catch (Throwable $exception) {
            throw UnableToGenerateTemporaryUrl::dueToError($path, $exception);
        }
    }

    public function getTemporaryUrl(string $path, DateTimeInterface $expiresAt, array $options = []): string
    {
        return $this->temporaryUrl($path, $expiresAt, new Config($options));
    }

    public function temporaryUploadUrl(string $path, DateTimeInterface $expiresAt, array $options = []): array
    {
        try {
            $now = new DateTimeImmutable;

            $absolutePath = $this->prefixer->prefixPath($path);

            $visibility = Visibility::from($options['visibility'] ?? $this->visibility);

            $byteSize = $options['byte_size'] ?? 0;

            $folder = dirname($absolutePath);

            $token = $this->client->createUploadToken(
                folder: $folder !== '.' ? $folder : null,
                visibility: $visibility,
                maxUploadBytes: $options['max_upload_bytes'] ?? null,
                expiresInSeconds: $expiresAt->getTimestamp() - $now->getTimestamp(),
            );

            $fileUpload = $this->client->createFileUpload(
                path: $absolutePath,
                contentType: $options['content_type'] ?? 'application/octet-stream',
                byteSize: $byteSize,
                visibility: $visibility,
            );

            return [
                'url' => $fileUpload->upload->url,
                'headers' => $fileUpload->upload->headers,
                'complete' => [
                    'url' => $this->client->buildCompletionUrl($fileUpload->upload->id),
                    'file_id' => $fileUpload->file->id,
                    'key' => $fileUpload->upload->key,
                    'token' => $token->uploadToken->token,
                    'expires_at' => $token->uploadToken->expiresAt,
                ],
            ];
        } catch (Throwable $exception) {
            throw UnableToGenerateTemporaryUrl::dueToError($path, $exception);
        }
    }

    protected function getFileAttributes(string $path): FileAttributes
    {
        $response = $this->client->getFile(
            $this->prefixer->prefixPath($path),
        );

        return new FileAttributes(
            $path,
            $response->file->byteSize,
            $response->file->visibility->value,
            null,
            $response->file->contentType,
            $response->file->metadata,
        );
    }
}
