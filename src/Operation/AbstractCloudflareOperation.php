<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Operation;

use Contenir\Cdn\Cloudflare\Credentials;
use Contenir\Cdn\Cloudflare\ZoneId;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Operation\OperationContextInterface;
use Contenir\Extension\Api\Operation\OperationHandlerInterface;
use Contenir\Extension\Api\Operation\OperationResult;
use Contenir\Extension\Api\Operation\OperationResultInterface;
use Contenir\Extension\Cloudflare\CloudflareSettings;
use Contenir\Extension\Cloudflare\Connections;
use Override;

/**
 * An operation on the Cloudflare section that needs to reach Cloudflare: it
 * runs once the zone and a token are saved, and otherwise says what to enter.
 *
 * @api
 */
abstract class AbstractCloudflareOperation implements OperationHandlerInterface
{
    public function __construct(
        protected readonly Connections $connections = new Connections(),
    ) {}

    #[Override]
    final public function handle(OperationContextInterface $context): OperationResultInterface
    {
        $extension = $context->extension();
        if (null === $extension) {
            return OperationResult::message('danger', 'The CMS ran this without the Cloudflare add-on\'s settings.');
        }

        $settings = CloudflareSettings::from($extension->settings());
        if (null === $settings->zone || null === $settings->credentials) {
            return OperationResult::message('warning', (string) $settings->missing());
        }

        return $this->run($settings->zone, $settings->credentials, $extension);
    }

    /**
     * Does the operation's work with the saved zone and credentials.
     */
    abstract protected function run(
        ZoneId $zone,
        Credentials $credentials,
        ExtensionContextInterface $context,
    ): OperationResultInterface;
}
