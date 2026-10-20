<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\TestAsset\Factory;

use Contenir\Extension\Cloudflare\Connections;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Http\FakeHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * Connections that send through a FakeHttpClient, so nothing reaches Cloudflare.
 */
final class ConnectionsFactory
{
    public static function over(FakeHttpClient $http): Connections
    {
        $factory = new Psr17Factory();

        return new Connections($http, $factory, $factory);
    }
}
