<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\Integration;

use Contenir\Extension\Api\Conformance\AbstractAddOnConformanceTestCase;
use Contenir\Extension\Api\ExtensionInterface;
use Contenir\Extension\Cloudflare\CloudflareExtension;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Group;

use function dirname;

/**
 * The API's own checks for an add-on: it registers cleanly, its boundary
 * file is current, and it has no webhook that would answer an unverified
 * request.
 */
#[Group('integration')]
final class CloudflareExtensionTest extends AbstractAddOnConformanceTestCase
{
    #[Override]
    protected function extension(): ExtensionInterface
    {
        return new CloudflareExtension();
    }

    #[Override]
    protected function handle(): string
    {
        return 'cloudflare';
    }

    #[Override]
    protected function http(): Psr17Factory
    {
        return new Psr17Factory();
    }

    #[Override]
    protected function root(): string
    {
        return dirname(__DIR__, levels: 2);
    }
}
