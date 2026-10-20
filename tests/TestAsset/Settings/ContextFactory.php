<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\TestAsset\Settings;

use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryContext;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Api\Testing\MemoryLogger;
use Contenir\Extension\Api\Testing\ServiceMap;
use DateTimeImmutable;
use LogicException;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The add-on's context over its settings in an array, logging to $logger.
 */
final class ContextFactory
{
    /**
     * @param array<string, mixed> $values By field name
     */
    public static function make(array $values = [], MemoryLogger $logger = new MemoryLogger()): InMemoryContext
    {
        $settings = new ArraySettings($values);

        return new InMemoryContext(
            new Handle('cloudflare'),
            new ServiceMap(),
            $logger,
            new FrozenClock(new DateTimeImmutable('2026-10-21T09:00:00+00:00')),
            '/site',
            new MapUrlResolver(),
            new class implements EventDispatcherInterface {
                #[Override]
                public function dispatch(object $event): object
                {
                    throw new LogicException('The add-on fires no events');
                }
            },
            static fn(string $section): ArraySettings => $settings,
        );
    }
}
