# ScrapeUnblocker PHP client

Official PHP client for the [ScrapeUnblocker](https://scrapeunblocker.com) web scraping API.

Every request is fully JavaScript-rendered in a real browser and routed through premium proxies, so it bypasses Cloudflare, DataDome, PerimeterX, Akamai, Kasada and similar anti-bot systems - from one simple call. You are only billed for successful requests.

- **Highest success rate on the market** (95%+ on live production traffic)
- **Rendered HTML or parsed JSON** - no per-site parsers to maintain
- Zero dependencies (uses the built-in cURL extension), typed exceptions

## Install

```bash
composer require scrapeunblocker/client
```

Requires PHP 8.1+ with the `curl` and `json` extensions.

## Quickstart

```php
<?php
require 'vendor/autoload.php';

use ScrapeUnblocker\Client;

$su = new Client(); // reads SCRAPEUNBLOCKER_KEY, or new Client('YOUR_API_KEY')

// Rendered HTML for any URL
$html = $su->getPageSource('https://example.com');

// Structured JSON instead of HTML (products, listings, search results, ...)
$product = $su->getParsed('https://www.amazon.com/dp/B08N5WRWNW');
echo $product->pageType;      // "product"
print_r($product->data);
```

Get your API key at [app.scrapeunblocker.com](https://app.scrapeunblocker.com). The free trial does not require a credit card.

## Authentication

Set an environment variable and the client picks it up:

```bash
export SCRAPEUNBLOCKER_KEY="YOUR_API_KEY"
```

```php
$su = new Client(); // reads SCRAPEUNBLOCKER_KEY
```

## Fetch rendered HTML

```php
$html = $su->getPageSource('https://www.nordstrom.com/browse/women/clothing/dresses', [
    'proxy_country' => 'US', // route through a specific country
    'time_sleep' => 3,       // wait extra seconds after load
]);
```

## Get parsed JSON

```php
$result = $su->getParsed('https://www.walmart.com/ip/12345');
echo $result->pageType;   // e.g. "product"
echo $result->source;     // how it was extracted
print_r($result->data);   // the fields

// If a parse ever comes back wrong, force a fresh set of rules:
$fresh = $su->getParsed($url, ['refresh_rules' => true, 'rules_hint' => 'price is missing']);
```

## Google search (SERP)

```php
$serp = $su->serp('web scraping api', ['pages_to_check' => 2, 'proxy_country' => 'US']);
```

## Cookies and the serving proxy

```php
$page = $su->getPageWithCookies('https://example.com');
echo $page->html;
print_r($page->cookies);
echo $page->proxy;
```

## Images

```php
$bytes = $su->getImage('https://example.com/photo.jpg');
file_put_contents('photo.jpg', $bytes);
```

## Skyscanner plugins

Flights, hotels and car hire as JSON:

```php
$locations = $su->skyscanner->flightLocations('London');

$flights = $su->skyscanner->flights([
    'origin' => 'London', 'dest' => 'New York',
    'depart_date' => '2026-09-01', 'adults' => 1, 'currency' => 'USD',
]);

$hotels = $su->skyscanner->hotels(['destination' => 'Madrid', 'checkin' => '2026-09-01', 'checkout' => '2026-09-03']);
$cars = $su->skyscanner->carhire(['pickup' => 'Madrid', 'pickup_datetime' => '2026-09-01T10:00', 'dropoff_datetime' => '2026-09-03T10:00']);
```

## Error handling

Non-2xx responses throw typed exceptions, all subclasses of `ScrapeUnblockerException`. Transient failures (429, 502, 503, 504 and network errors) are retried automatically with exponential backoff.

```php
use ScrapeUnblocker\Exception\BlockedException;
use ScrapeUnblocker\Exception\RateLimitException;
use ScrapeUnblocker\Exception\UpstreamOutageException;

try {
    $html = $su->getPageSource('https://example.com');
} catch (BlockedException $e) {
    // 403: the target blocked every bypass path (not billed)
} catch (RateLimitException $e) {
    // 429: slow down
} catch (UpstreamOutageException $e) {
    // 503: the target site itself is down - retry later
}
```

| Exception | Status | Meaning |
|---|---|---|
| `InvalidRequestException` | 400 | Bad URL or unsupported scheme |
| `AuthenticationException` | 401 | Missing or invalid API key |
| `BlockedException` | 403 | Blocked by bot protection on every path |
| `RateLimitException` | 429 | Too many requests |
| `UpstreamOutageException` | 503 | The target origin is down |
| `ServerException` | 5xx | Unexpected server error |
| `TimeoutException` | - | Request exceeded the timeout |
| `ConnectionException` | - | Could not reach the API |

## Configuration

```php
new Client('YOUR_API_KEY', [
    'base_url' => 'https://api.scrapeunblocker.com',
    'timeout' => 180,     // seconds; protected pages can be slow
    'max_retries' => 2,
]);
```

## Links

- Documentation: https://developers.scrapeunblocker.com
- Website: https://scrapeunblocker.com
- Dashboard: https://app.scrapeunblocker.com

## License

MIT
