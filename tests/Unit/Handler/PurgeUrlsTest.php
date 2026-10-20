<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\Unit\Handler;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Api\Form\Form;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\Screen;
use Contenir\Extension\Api\Testing\MemoryLogger;
use Contenir\Extension\Cloudflare\Handler\PurgeUrls;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\CloudflareResponses;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\ConnectionsFactory;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\SettingsFactory;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Http\NetworkError;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Settings\ContextFactory;
use Nyholm\Psr7\ServerRequest;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

#[Group('unit')]
final class PurgeUrlsTest extends TestCase
{
    private MemoryLogger $log;

    #[Test]
    public function asksForUrlsWhenTheBoxIsEmpty(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::zone('example.com'));

        static::assertSame(
            [['level' => 'warning', 'text' => 'Enter the URLs to purge, one to a line.']],
            $this->post($http, " \n ")->messages,
        );
    }

    #[Test]
    public function asksForWhatIsMissingAndKeepsTheBox(): void
    {
        $http    = new FakeHttpClient();
        $request = (new ServerRequest('POST', '/admin/extension/cloudflare/purge-urls'))->withParsedBody([
            'urls' => 'https://example.com/news/',
        ]);
        $result = (new PurgeUrls(ConnectionsFactory::over($http)))->handle($request, ContextFactory::make());

        static::assertEquals(
            [
                PurgeUrls::screen('https://example.com/news/'),
                [['level' => 'warning', 'text' => 'Enter the zone ID and an API token, and save, first.']],
                [],
            ],
            [$result->screen, $result->messages, $http->requests],
        );
    }

    #[Test]
    public function countsOneUrlAsOne(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::zone('example.com'), CloudflareResponses::ok());

        static::assertSame(
            [['level' => 'success', 'text' => 'Cloudflare purged 1 URL.']],
            $this->post($http, 'https://example.com/news/')->messages,
        );
    }

    #[Test]
    public function describesTheScreen(): void
    {
        static::assertEquals(
            new Screen(
                title: 'Purge URLs',
                intro: 'Drop pages and files from Cloudflare\'s cache, so the next visitor gets them fresh from the'
                    . ' site. Cloudflare purges up to 100 at a time, and longer lists are sent in batches.',
                form: new Form([
                    new Field(
                        name: 'urls',
                        label: 'URLs',
                        type: FieldType::Textarea,
                        description: 'Full addresses on the site\'s domain, one to a line, such as'
                            . ' https://www.example.com/news/.',
                        default: 'https://example.com/a',
                        attributes: ['rows' => 10],
                    ),
                ]),
            ),
            PurgeUrls::screen('https://example.com/a'),
        );
    }

    #[Test]
    public function purgesNothingWhenEveryUrlIsRefused(): void
    {
        $http   = new FakeHttpClient(CloudflareResponses::zone('example.com'));
        $result = $this->post($http, 'https://example.org/');

        static::assertSame([1, 'warning'], [count($http->requests), $result->messages[0]['level'] ?? null]);
    }

    #[Test]
    public function purgesTheUrlsOnTheZonesDomainAsAPerson(): void
    {
        $http   = new FakeHttpClient(CloudflareResponses::zone('example.com'), CloudflareResponses::ok());
        $result = $this->post($http, "https://example.com/news/\nhttps://www.example.com/a.jpg");

        static::assertEquals(
            [
                PurgeUrls::screen(),
                [['level' => 'success', 'text' => 'Cloudflare purged 2 URLs.']],
                '{"files":["https://example.com/news/","https://www.example.com/a.jpg"]}',
                'person',
            ],
            [
                $result->screen,
                $result->messages,
                (string) $http->requests[1]->getBody(),
                $this->log->entries()[0]['context']['trigger'] ?? null,
            ],
        );
    }

    #[Test]
    public function readsAMissingBoxAsEmpty(): void
    {
        $http    = new FakeHttpClient(CloudflareResponses::zone('example.com'));
        $request = (new ServerRequest('POST', '/admin/extension/cloudflare/purge-urls'))->withParsedBody(['urls' => [
            'x',
        ]]);

        static::assertSame(
            'Enter the URLs to purge, one to a line.',
            $this->answer($http, $request)->messages[0]['text'] ?? null,
        );
    }

    #[Test]
    public function refusesUrlsOffTheDomainAndLeavesThemInTheBox(): void
    {
        $http   = new FakeHttpClient(CloudflareResponses::zone('example.com'), CloudflareResponses::ok());
        $result = $this->post($http, "https://example.com/news/\nhttps://example.org/news/\n/about/");

        static::assertEquals(
            [
                PurgeUrls::screen("https://example.org/news/\n/about/"),
                [
                    ['level' => 'success', 'text' => 'Cloudflare purged 1 URL.'],
                    [
                        'level' => 'warning',
                        'text'  => 'Not purged: https://example.org/news/. example.org is not one of this site\'s'
                            . ' addresses.',
                    ],
                    [
                        'level' => 'warning',
                        'text'  => 'Not purged: /about/. Enter the full address, starting https://.',
                    ],
                ],
            ],
            [$result->screen, $result->messages],
        );
    }

    #[Test]
    public function saysWhatCloudflareRefused(): void
    {
        $http = new FakeHttpClient(
            CloudflareResponses::zone('example.com'),
            CloudflareResponses::refused(1012, 'Too many files', 400),
        );

        static::assertSame(
            [['level' => 'danger', 'text' => 'Cloudflare refused: 1012 Too many files']],
            $this->post($http, 'https://example.com/news/')->messages,
        );
    }

    #[Test]
    public function saysWhenTheDomainCantBeReadAndKeepsTheBox(): void
    {
        $http   = new FakeHttpClient(new NetworkError('Connection timed out'));
        $result = $this->post($http, 'https://example.com/news/');

        static::assertEquals(
            [
                PurgeUrls::screen('https://example.com/news/'),
                [['level' => 'danger', 'text' => 'Could not reach Cloudflare: Connection timed out']],
            ],
            [$result->screen, $result->messages],
        );
    }

    #[Test]
    public function showsAnEmptyBoxToStart(): void
    {
        $http   = new FakeHttpClient();
        $result = $this->answer($http, new ServerRequest('GET', '/admin/extension/cloudflare/purge-urls'));

        static::assertEquals([PurgeUrls::screen(), [], []], [$result->screen, $result->messages, $http->requests]);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->log = new MemoryLogger();
    }

    private function answer(FakeHttpClient $http, ServerRequest $request): HandlerResult
    {
        return (new PurgeUrls(ConnectionsFactory::over($http)))->handle(
            $request,
            ContextFactory::make(SettingsFactory::withToken(), $this->log),
        );
    }

    private function post(FakeHttpClient $http, string $urls): HandlerResult
    {
        return $this->answer(
            $http,
            (new ServerRequest('POST', '/admin/extension/cloudflare/purge-urls'))->withParsedBody(['urls' => $urls]),
        );
    }
}
