<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Operation;

use Contenir\Cdn\Cloudflare\ConnectionCheck;
use Contenir\Cdn\Cloudflare\Credentials;
use Contenir\Cdn\Cloudflare\PurgeMethod;
use Contenir\Cdn\Cloudflare\ZoneId;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Operation\OperationResult;
use Contenir\Extension\Api\Operation\OperationResultInterface;
use Override;

use function array_map;
use function implode;
use function sprintf;

/**
 * Test connection: checks the saved credentials and reads the zone, without
 * purging anything, and says which domain and plan it found and how
 * Cloudflare can purge it.
 *
 * @api
 */
final class TestConnection extends AbstractCloudflareOperation
{
    #[Override]
    protected function run(
        ZoneId $zone,
        Credentials $credentials,
        ExtensionContextInterface $context,
    ): OperationResultInterface {
        $connection = (new ConnectionCheck($this->connections->client($credentials)))->run($zone);
        if (null === $connection->zone) {
            return OperationResult::message('danger', (string) $connection->problem);
        }

        $result = OperationResult::message('success', sprintf(
            'Connected to %s (%s). Purge methods: %s.',
            $connection->zone->name,
            $connection->zone->plan,
            implode(', ', array_map(
                static fn(PurgeMethod $method): string => $method->label(),
                $connection->zone->purgeMethods(),
            )),
        ));

        return $connection->deprecatedKey
            ? $result->withMessage(
                'warning',
                'This signs in with the Global API Key, which can do anything your Cloudflare account can. Make an'
                    . ' API token with Cache Purge and Zone Read for this zone, and enter it instead.',
            )
            : $result;
    }
}
