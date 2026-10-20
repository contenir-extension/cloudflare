<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\Unit;

use Contenir\Cdn\PurgerInterface;
use Contenir\Extension\Api\Field\FieldTypeDeclaration;
use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Handler\HandlerDeclaration;
use Contenir\Extension\Api\Registry;
use Contenir\Extension\Api\Service\ServiceDeclaration;
use Contenir\Extension\Cloudflare\CloudflareExtension;
use Contenir\Extension\Cloudflare\CloudflareSection;
use Contenir\Extension\Cloudflare\Field\ZoneIdField;
use Contenir\Extension\Cloudflare\Handler\PurgeUrls;
use Contenir\Extension\Cloudflare\Purge\PurgerFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CloudflareExtensionTest extends TestCase
{
    #[Test]
    public function declaresItsSectionFieldTypeScreenAndPurger(): void
    {
        $registry = new Registry('cloudflare');
        (new CloudflareExtension())->register($registry);
        $registration = $registry->registration();

        static::assertEquals(
            [
                ['cloudflare:zone-id' => new FieldTypeDeclaration('cloudflare:zone-id', 'zone-id', ZoneIdField::class)],
                ['cloudflare' => CloudflareSection::define(new Handle('cloudflare'))],
                ['purge-urls' => new HandlerDeclaration(
                    'purge-urls',
                    PurgeUrls::class,
                    '/admin/extension/cloudflare/purge-urls',
                )],
                [PurgerInterface::class => new ServiceDeclaration(PurgerInterface::class, PurgerFactory::class)],
            ],
            [
                $registration->fieldTypes,
                $registration->settingsSections,
                $registration->handlers,
                $registration->services,
            ],
        );
    }
}
