<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\TestAsset\Factory;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Answers from Cloudflare's API, as its documentation shows them.
 */
final class CloudflareResponses
{
    public const string RAY = '8d3f2a1b4c5e6f70-SYD';

    /**
     * @param array<string, mixed> $result
     */
    public static function ok(array $result = ['id' => SettingsFactory::ZONE_ID]): ResponseInterface
    {
        return self::json(200, ['success' => true, 'errors' => [], 'messages' => [], 'result' => $result]);
    }

    public static function refused(int $code, string $message, int $status = 403): ResponseInterface
    {
        return self::json($status, [
            'success'  => false,
            'errors'   => [['code' => $code, 'message' => $message]],
            'messages' => [],
            'result'   => null,
        ]);
    }

    /**
     * A token Cloudflare says is active.
     */
    public static function token(): ResponseInterface
    {
        return self::ok(['id' => 'ed17574386854bf78a67040be0a770b0', 'status' => 'active']);
    }

    /**
     * The zone, as GET /zones/{id} gives it.
     */
    public static function zone(string $name = 'example.com', string $plan = 'Pro Plan'): ResponseInterface
    {
        return self::ok([
            'id'     => SettingsFactory::ZONE_ID,
            'name'   => $name,
            'status' => 'active',
            'plan'   => ['id' => 'abc', 'name' => $plan],
        ]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function json(int $status, array $body): ResponseInterface
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json', 'cf-ray' => self::RAY],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }
}
