<?php

declare(strict_types=1);

namespace Devhammed\Byteship;

use Devhammed\Byteship\Flysystem\Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('byteship');
    }

    public function packageBooted(): void
    {
        Storage::extend('byteship', function (Application $app, array $config) {
            $client = new Client($config['api_key']);

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
    }
}
