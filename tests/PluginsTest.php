<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ScrapeUnblocker\Client;
use ScrapeUnblocker\PluginsTrait;

final class PluginsTest extends TestCase
{
    /** @var list<string> */
    private array $urls = [];

    private function client(): Client
    {
        $this->urls = [];
        $transport = function (string $url, array $headers): array {
            $this->urls[] = $url;
            return ['status' => 200, 'body' => json_encode(['ok' => true])];
        };

        return new Client('test-key', ['transport' => $transport]);
    }

    /** @return list<array{0: string, 1: string}> */
    private function query(int $index = 0): array
    {
        $pairs = [];
        $query = (string) parse_url($this->urls[$index], PHP_URL_QUERY);
        foreach ($query === '' ? [] : explode('&', $query) as $part) {
            [$key, $value] = explode('=', $part, 2);
            $pairs[] = [urldecode($key), urldecode($value)];
        }
        return $pairs;
    }

    /** @return list<string> */
    private static function pluginMethods(): array
    {
        $names = [];
        foreach ((new \ReflectionClass(PluginsTrait::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->getName() !== 'plugin') {
                $names[] = $m->getName();
            }
        }
        return $names;
    }

    public function testCoversEveryPublicPluginEndpoint(): void
    {
        $this->assertGreaterThan(90, count(self::pluginMethods()));
    }

    public function testEveryMethodPostsItsPositionalArguments(): void
    {
        foreach (self::pluginMethods() as $name) {
            $client = $this->client();
            $args = [];
            foreach ((new ReflectionMethod(Client::class, $name))->getParameters() as $i => $p) {
                if ($p->getName() !== 'options') {
                    $args[] = "v$i";
                }
            }
            $this->assertSame(['ok' => true], $client->$name(...$args), $name);
            $values = array_column($this->query(), 1);
            foreach ($args as $arg) {
                $this->assertContains($arg, $values, $name);
            }
        }
    }

    public function testOptionsAreSentUnderTheirApiNames(): void
    {
        $this->client()->targetSearch('tv', ['sort' => 'price_low', 'max_results' => 48]);
        $this->assertStringContainsString('/marketplace/target-search?', $this->urls[0]);
        $this->assertSame([['query', 'tv'], ['sort', 'price_low'], ['max_results', '48']], $this->query());
    }

    public function testAModeMethodPinsItsMode(): void
    {
        $this->client()->woolworthsProduct('888137');
        $this->assertSame([['mode', 'product'], ['product', '888137']], $this->query());
    }

    public function testTwoPositionalArgumentsAndAFalseFlag(): void
    {
        $this->client()->yelpSearch('coffee', 'San Francisco, CA', ['open_now' => false]);
        $this->assertSame(
            [['mode', 'search'], ['find_desc', 'coffee'], ['find_loc', 'San Francisco, CA'], ['open_now', 'false']],
            $this->query()
        );
    }

    public function testAListIsJoinedWhereTheApiTakesOneString(): void
    {
        $this->client()->googleTrends(['coffee', 'tea'], ['geo' => 'US']);
        $this->assertSame([['keywords', 'coffee,tea'], ['geo', 'US']], $this->query());
    }

    public function testAListParameterRepeatsItsKey(): void
    {
        $this->client()->trustpilotReviews('www.nike.com', ['stars' => [4, 5]]);
        $this->assertSame([['company', 'www.nike.com'], ['stars', '4'], ['stars', '5']], $this->query());
    }

    public function testOptionalPositionalArgumentCanBeLeftOut(): void
    {
        $this->client()->careerSiteJobs(null, ['provider' => 'greenhouse', 'company' => 'stripe']);
        $this->assertSame([['provider', 'greenhouse'], ['company', 'stripe']], $this->query());
    }

    public function testGenericPluginCall(): void
    {
        $this->client()->plugin('/marketplace/target-search', ['query' => 'tv', 'sort' => null]);
        $this->assertSame('https://api.scrapeunblocker.com/marketplace/target-search?query=tv', $this->urls[0]);
    }
}
