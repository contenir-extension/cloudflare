<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\Unit;

use Contenir\Extension\Cloudflare\OnChange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class OnChangeTest extends TestCase
{
    /**
     * @return array<string, array{mixed, OnChange}>
     */
    public static function settings(): array
    {
        return [
            'nothing'    => ['nothing', OnChange::Nothing],
            'everything' => ['everything', OnChange::Everything],
            'unset'      => [null, OnChange::Everything],
            'unknown'    => ['changed pages', OnChange::Everything],
        ];
    }

    #[Test]
    #[DataProvider('settings')]
    public function readsTheSettingFallingBackToEverything(mixed $value, OnChange $expected): void
    {
        static::assertSame($expected, OnChange::fromSetting($value));
    }
}
