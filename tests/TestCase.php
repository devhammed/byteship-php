<?php

declare(strict_types=1);

namespace Devhammed\Byteship\Tests;

use Devhammed\Byteship\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        $providers = [
            ServiceProvider::class,
        ];

        sort($providers);

        return $providers;
    }

    public function getEnvironmentSetUp($app): void
    {
        Model::unguard();

        Config::set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        Config::set('filesystems.default', 'byteship');

        Config::set('filesystems.disks.byteship', [
            'driver' => 'byteship',
            'visibility' => 'public',
            'api_key' => env('BYTESHIP_API_KEY'),
        ]);
    }
}
