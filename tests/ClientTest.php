<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Tests;

use PHPUnit\Framework\TestCase;
use ScrapeUnblocker\Client;
use ScrapeUnblocker\Exception\ApiException;
use ScrapeUnblocker\Exception\AuthenticationException;
use ScrapeUnblocker\Exception\BlockedException;
use ScrapeUnblocker\Exception\BrowserTimeoutException;
use ScrapeUnblocker\Exception\CreditLimitExceededException;
use ScrapeUnblocker\Exception\InvalidRequestException;
use ScrapeUnblocker\Exception\NoSubscriptionException;
use ScrapeUnblocker\Exception\NotFoundException;
use ScrapeUnblocker\Exception\PaymentFailedException;
use ScrapeUnblocker\Exception\PaymentRequiredException;
use ScrapeUnblocker\Exception\QuotaExceededException;
use ScrapeUnblocker\Exception\RateLimitException;
use ScrapeUnblocker\Exception\ScrapeUnblockerException;
use ScrapeUnblocker\Exception\UnsupportedContentException;
use ScrapeUnblocker\Exception\UpstreamOutageException;
use ScrapeUnblocker\Exception\ValidationException;
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

    public function testGoogleLocalTargetsMapsEndpoint(): void
    {
        $client = $this->client([['status' => 200, 'body' => json_encode(['results' => []])]]);
        $out = $client->googleLocal('coffee shops in chicago', ['proxy_country' => 'US', 'gl' => 'us']);

        $this->assertSame(['results' => []], $out);
        $this->assertStringContainsString('/maps/google-local', $this->urls[0]);
        $this->assertStringContainsString('keyword=coffee', $this->urls[0]);
        $this->assertStringContainsString('proxy_country=US', $this->urls[0]);
        $this->assertStringContainsString('gl=us', $this->urls[0]);
    }

    public function testOopbuySearchTargetsGoodsEndpoint(): void
    {
        $client = $this->client([['status' => 200, 'body' => json_encode(['results' => []])]]);
        $out = $client->oopbuySearch('running shoes', ['channel' => 'taobao', 'page' => 2, 'page_size' => 40, 'sort' => 'price_asc']);

        $this->assertSame(['results' => []], $out);
        $this->assertStringContainsString('/goods/oopbuy-search', $this->urls[0]);
        $this->assertStringContainsString('keyword=running', $this->urls[0]);
        $this->assertStringContainsString('channel=taobao', $this->urls[0]);
        $this->assertStringContainsString('page=2', $this->urls[0]);
        $this->assertStringContainsString('page_size=40', $this->urls[0]);
        $this->assertStringContainsString('sort=price_asc', $this->urls[0]);
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
            [401, AuthenticationException::class],
            [402, PaymentRequiredException::class],
            [403, BlockedException::class],
            [404, NotFoundException::class],
            [408, BrowserTimeoutException::class],
            [415, UnsupportedContentException::class],
            [422, ValidationException::class],
            [429, RateLimitException::class],
            [503, UpstreamOutageException::class],
            [418, ApiException::class],
        ];
    }

    /**
     * @dataProvider billingBodyProvider
     */
    public function testBillingErrorSubclassFromBody(string $body, string $exceptionClass): void
    {
        $client = $this->client([['status' => 402, 'body' => $body]], ['max_retries' => 0]);

        try {
            $client->getPageSource('https://example.com');
            $this->fail('Expected a billing exception');
        } catch (PaymentRequiredException $e) {
            $this->assertInstanceOf($exceptionClass, $e);
            $this->assertSame(402, $e->statusCode);
            $this->assertSame($body, $e->body);
        }
    }

    public static function billingBodyProvider(): array
    {
        return [
            ["Quota exceeded\n", QuotaExceededException::class],
            ["Credit limit exceeded\n", CreditLimitExceededException::class],
            ["Payment failed - update payment method\n", PaymentFailedException::class],
            ['something new we do not know yet', PaymentRequiredException::class],
        ];
    }

    /**
     * @dataProvider authBodyProvider
     */
    public function testAuthErrorSubclassFromBody(string $body, string $exceptionClass): void
    {
        $client = $this->client([['status' => 401, 'body' => $body]], ['max_retries' => 0]);

        try {
            $client->getPageSource('https://example.com');
            $this->fail('Expected an authentication exception');
        } catch (AuthenticationException $e) {
            $this->assertInstanceOf($exceptionClass, $e);
            $this->assertSame(401, $e->statusCode);
        }
    }

    public static function authBodyProvider(): array
    {
        return [
            ["No valid subscription\n", NoSubscriptionException::class],
            ["Unauthorized\n", AuthenticationException::class],
        ];
    }

    /**
     * These clear when the key or billing state changes, never on a retry.
     *
     * @dataProvider nonRetryableProvider
     */
    public function testAuthAndBillingErrorsAreNotRetried(int $status): void
    {
        $client = $this->client([['status' => $status, 'body' => 'Quota exceeded']], ['max_retries' => 3]);

        try {
            $client->getPageSource('https://example.com');
            $this->fail('Expected an API exception');
        } catch (ApiException) {
            $this->assertCount(1, $this->urls);
        }
    }

    public static function nonRetryableProvider(): array
    {
        return [[401], [402]];
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
