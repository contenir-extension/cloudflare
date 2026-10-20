<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\TestAsset\Factory;

/**
 * The Cloudflare section's values for a site that is set up, by field name.
 */
final class SettingsFactory
{
    public const string ZONE_ID = '023e105f4ecef8ad9ca31a8372d0c353';

    /**
     * @mago-expect lint:no-literal-password A made-up token for tests, which the fake HTTP client answers.
     */
    public const string TOKEN = 'Y2xvdWRmbGFyZS10b2tlbi1zZWNyZXQ';

    public const string KEY = 'c2547eb745079dac9320b638f5e225cf483cc5cfdda41';

    /**
     * A zone and the deprecated email and Global API Key, and anything else in $more.
     *
     * @param array<string, mixed> $more
     *
     * @return array<string, mixed>
     */
    public static function withGlobalKey(array $more = []): array
    {
        return ['zone_id' => self::ZONE_ID, 'email' => 'ops@example.com', 'api_key' => self::KEY, ...$more];
    }

    /**
     * A zone and an API token, and anything else in $more.
     *
     * @param array<string, mixed> $more
     *
     * @return array<string, mixed>
     */
    public static function withToken(array $more = []): array
    {
        return ['zone_id' => self::ZONE_ID, 'api_token' => self::TOKEN, ...$more];
    }
}
