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

    public function testGoogleHotelsSearchAndProperty(): void
    {
        $client = $this->client();
        $client->googleHotels('hotels in Paris', ['check_in' => '2026-11-02', 'check_out' => '2026-11-05', 'currency' => 'EUR']);
        $client->googleHotelsProperty('ChkIqp', ['adults' => 1, 'children' => '7,10']);
        $this->assertStringContainsString('/travel/google-hotels?', $this->urls[0]);
        $this->assertSame(
            [['q', 'hotels in Paris'], ['check_in', '2026-11-02'], ['check_out', '2026-11-05'], ['currency', 'EUR']],
            $this->query()
        );
        $this->assertStringContainsString('/travel/google-hotels-property?', $this->urls[1]);
        $this->assertSame([['property_token', 'ChkIqp'], ['adults', '1'], ['children', '7,10']], $this->query(1));
    }

    public function testGoogleNewsSearchOrSection(): void
    {
        $client = $this->client();
        $client->googleNews('tesla', ['when' => '1d', 'max_results' => 50]);
        $client->googleNews(null, ['topic' => 'technology', 'gl' => 'gb']);
        $this->assertSame([['q', 'tesla'], ['when', '1d'], ['max_results', '50']], $this->query());
        $this->assertSame([['topic', 'technology'], ['gl', 'gb']], $this->query(1));
    }

    public function testGoogleAdsTransparencyAndAdvertisers(): void
    {
        $client = $this->client();
        $client->googleAdsTransparency(['domain' => 'nike.com', 'format' => 'video', 'include_preview' => false]);
        $client->googleAdsTransparencyAdvertisers('nike', ['max_results' => 5]);
        $this->assertStringContainsString('/ads/google-ads-transparency?', $this->urls[0]);
        $this->assertSame([['domain', 'nike.com'], ['format', 'video'], ['include_preview', 'false']], $this->query());
        $this->assertStringContainsString('/ads/google-ads-transparency-advertisers?', $this->urls[1]);
        $this->assertSame([['q', 'nike'], ['max_results', '5']], $this->query(1));
    }

    public function testShopeeSearchAndProduct(): void
    {
        $client = $this->client();
        $client->shopeeSearch('lego', ['country' => 'my', 'sort' => 'sales', 'min_price' => 10]);
        $client->shopeeProduct(null, ['shop_id' => '442800909', 'item_id' => '40270108836', 'country' => 'sg']);
        $this->assertSame([['q', 'lego'], ['country', 'my'], ['sort', 'sales'], ['min_price', '10']], $this->query());
        $this->assertStringContainsString('/marketplace/shopee-product?', $this->urls[1]);
        $this->assertSame([['shop_id', '442800909'], ['item_id', '40270108836'], ['country', 'sg']], $this->query(1));
    }

    public function testTaobaoSearchAndProduct(): void
    {
        $client = $this->client();
        $client->taobaoSearch('phone case', ['sort' => 'sales', 'price_max' => 99.5, 'filter' => '20000-88634403']);
        $client->taobaoProduct(null, ['url' => 'https://item.taobao.com/item.htm?id=765833709683']);
        $this->assertSame(
            [['q', 'phone case'], ['sort', 'sales'], ['price_max', '99.5'], ['filter', '20000-88634403']],
            $this->query()
        );
        $this->assertSame([['url', 'https://item.taobao.com/item.htm?id=765833709683']], $this->query(1));
    }

    public function testUpworkJobsWithoutAQuery(): void
    {
        $this->client()->upworkJobs(null, ['sort' => 'recency', 'job_type' => 'hourly', 'details' => true]);
        $this->assertStringContainsString('/jobs/upwork?', $this->urls[0]);
        $this->assertSame([['job_type', 'hourly'], ['sort', 'recency'], ['details', 'true']], $this->query());
    }

    public function testSmythsAndWish(): void
    {
        $client = $this->client();
        $client->smythsSearch('lego', ['max_results' => 24]);
        $client->wishSearch('phone case', ['page' => 2]);
        $this->assertSame([['mode', 'search'], ['query', 'lego'], ['max_results', '24']], $this->query());
        $this->assertSame([['query', 'phone case'], ['page', '2']], $this->query(1));
    }
}
