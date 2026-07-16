<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Tests;

use PHPUnit\Framework\TestCase;
use ScrapeUnblocker\Client;
use ScrapeUnblocker\Exception\BlockedException;
use ScrapeUnblocker\Exception\InvalidRequestException;
use ScrapeUnblocker\Exception\RateLimitException;
use ScrapeUnblocker\Exception\ScrapeUnblockerException;
use ScrapeUnblocker\Exception\UpstreamOutageException;
use ScrapeUnblocker\ParsedPage;

final class ClientTest extends TestCase
{
    /** @var list<string> */
    private array $urls = [];

    private function client(array $queue, array $options = []): Client
    {
        $this->urls = [];
        $transport = function (string $url, array $headers) use (&$queue): array {
            $this->urls[] = $url;
            $this->lastHeaders = $headers;
            return array_shift($queue);
        };

        return new Client('test-key', ['transport' => $transport] + $options);
    }

    /** @var list<string> */
    private array $lastHeaders = [];

    public function testThrowsWithoutApiKey(): void
    {
        putenv('SCRAPEUNBLOCKER_KEY');
        $this->expectException(ScrapeUnblockerException::class);
        new Client();
    }

    public function testReadsApiKeyFromEnv(): void
    {
        putenv('SCRAPEUNBLOCKER_KEY=from-env');
        $client = new Client(null, ['transport' => fn () => ['status' => 200, 'body' => 'ok']]);
        $this->assertSame('ok', $client->getPageSource('https://example.com'));
        putenv('SCRAPEUNBLOCKER_KEY');
    }

    public function testGetPageSourceReturnsHtml(): void
    {
        $client = $this->client([['status' => 200, 'body' => '<html>hi</html>']]);
        $html = $client->getPageSource('https://example.com', ['proxy_country' => 'US']);

        $this->assertSame('<html>hi</html>', $html);
        $this->assertStringContainsString('/getPageSource', $this->urls[0]);
        $this->assertStringContainsString('proxy_country=US', $this->urls[0]);
        $this->assertContains('x-scrapeunblocker-key: test-key', $this->lastHeaders);
    }

    public function testOmitsNullParams(): void
    {
        $client = $this->client([['status' => 200, 'body' => 'ok']]);
        $client->getPageSource('https://example.com');
        $this->assertStringNotContainsString('proxy_country', $this->urls[0]);
        $this->assertStringNotContainsString('time_sleep', $this->urls[0]);
    }

    public function testGetParsedReturnsParsedPage(): void
    {
        $payload = ['data' => ['page_type' => 'product', 'source' => 'schema.org', 'data' => ['price' => 10]]];
        $client = $this->client([['status' => 200, 'body' => json_encode($payload)]]);
        $result = $client->getParsed('https://example.com/p/1', ['refresh_rules' => true, 'rules_hint' => 'price missing']);

        $this->assertInstanceOf(ParsedPage::class, $result);
        $this->assertSame('product', $result->pageType);
        $this->assertSame(['price' => 10], $result->data);
        $this->assertStringContainsString('parsed_data=true', $this->urls[0]);
        $this->assertStringContainsString('refresh_rules=true', $this->urls[0]);
    }

    public function testSerpTargetsSerpApi(): void
    {
        $client = $this->client([['status' => 200, 'body' => json_encode(['organic' => []])]]);
        $out = $client->serp('hello world', ['pages_to_check' => 2]);

        $this->assertSame(['organic' => []], $out);
        $this->assertStringContainsString('/serpApi', $this->urls[0]);
        $this->assertStringContainsString('pages_to_check=2', $this->urls[0]);
    }

    public function testGetImageReturnsBytes(): void
    {
        $client = $this->client([['status' => 200, 'body' => "\x89PNG"]]);
        $this->assertSame("\x89PNG", $client->getImage('https://example.com/x.png'));
    }

    public function testSkyscannerFlights(): void
    {
        $client = $this->client([['status' => 200, 'body' => json_encode(['itineraries' => []])]]);
        $out = $client->skyscanner->flights(['origin' => 'London', 'dest' => 'Paris']);

        $this->assertSame(['itineraries' => []], $out);
        $this->assertStringContainsString('/flights/skyscanner-quotes', $this->urls[0]);
        $this->assertStringContainsString('origin=London', $this->urls[0]);
    }

    /**
     * @dataProvider errorProvider
     */
    public function testErrorMapping(int $status, string $exceptionClass): void
    {
        $client = $this->client([['status' => $status, 'body' => 'nope']], ['max_retries' => 0]);
        $this->expectException($exceptionClass);
        $client->getPageSource('https://example.com');
    }

    public static function errorProvider(): array
    {
        return [
            [400, InvalidRequestException::class],
            [403, BlockedException::class],
            [429, RateLimitException::class],
            [503, UpstreamOutageException::class],
        ];
    }

    public function testRetriesThenSucceeds(): void
    {
        $client = $this->client([
            ['status' => 503, 'body' => 'outage'],
            ['status' => 200, 'body' => 'recovered'],
        ], ['max_retries' => 2]);

        $this->assertSame('recovered', $client->getPageSource('https://example.com'));
        $this->assertCount(2, $this->urls);
    }
}
