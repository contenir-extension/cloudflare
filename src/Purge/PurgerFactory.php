<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Purge;

use Contenir\Cdn\PurgerInterface;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Service\ServiceFactoryInterface;
use Contenir\Extension\Cloudflare\Connections;
use Override;

/**
 * Makes the purger the add-on provides to the CMS.
 *
 * @api
 */
final readonly class PurgerFactory implements ServiceFactoryInterface
{
    public function __construct(
        private Connections $connections = new Connections(),
    ) {}

    #[Override]
    public function create(ExtensionContextInterface $context): PurgerInterface
    {
        return new SitePurger($context, $this->connections);
    }
}
