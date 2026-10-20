<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Operation;

use Contenir\Cdn\Cloudflare\Credentials;
use Contenir\Cdn\Cloudflare\ZoneId;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Operation\OperationResult;
use Contenir\Extension\Api\Operation\OperationResultInterface;
use Contenir\Extension\Cloudflare\Trigger;
use Override;

use function implode;

/**
 * Purge everything: drops everything Cloudflare caches for the zone, after
 * the button's confirm step. The purge is logged as started by a person.
 *
 * @api
 */
final class PurgeEverything extends AbstractCloudflareOperation
{
    #[Override]
    protected function run(
        ZoneId $zone,
        Credentials $credentials,
        ExtensionContextInterface $context,
    ): OperationResultInterface {
        $report = $this->connections->purgerFor($zone, $credentials, $context->logger(), Trigger::Person)->purgeAll();

        return $report->succeeded()
            ? OperationResult::message(
                'success',
                'Cloudflare purged everything it held for the site. Pages are a little slower until it caches them again.',
            )
            : OperationResult::message('danger', implode(' ', $report->problems));
    }
}
