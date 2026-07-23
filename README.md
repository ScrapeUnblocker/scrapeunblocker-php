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

## Google Local (Maps)

```php
$local = $su->googleLocal('coffee shops in chicago', ['proxy_country' => 'US', 'gl' => 'us']);
foreach ($local['results'] as $biz) {
    echo "{$biz['name']} {$biz['rating']} {$biz['address']}\n";
}
```

## Oopbuy goods search

```php
$goods = $su->oopbuySearch('running shoes', ['channel' => '1688', 'sort' => 'best_selling', 'page_size' => 20]);
foreach ($goods['results'] as $item) {
    echo "{$item['title']} {$item['price']} {$item['url']}\n";
}
```

Channels: `1688` (default), `taobao`, `official`. Sort: `default`, `price_asc`, `price_desc`, `best_selling`. `page_size` up to 60. Oopbuy trademark-blocks brand keywords at its own backend: those come back as a successful `200` with `keywordRejected: true` and an empty `results` array, not an error.

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

Non-2xx responses throw typed exceptions, all subclasses of `ScrapeUnblockerException`.

```php
use ScrapeUnblocker\Exception\BlockedException;
use ScrapeUnblocker\Exception\PaymentRequiredException;
use ScrapeUnblocker\Exception\RateLimitException;
use ScrapeUnblocker\Exception\UpstreamOutageException;

try {
    $html = $su->getPageSource('https://example.com');
} catch (BlockedException $e) {
    // 403: the target blocked every bypass path (not billed)
} catch (PaymentRequiredException $e) {
    // 402: quota, credit limit, or a failed payment - fix billing
} catch (RateLimitException $e) {
    // 429: slow down
} catch (UpstreamOutageException $e) {
    // 503: the target site itself is down - retry later
}
```

| Exception | Status | Meaning |
|---|---|---|
| `InvalidRequestException` | 400 | Bad URL, unsupported scheme, or the API key header was not sent |
| `AuthenticationException` | 401 | Key not recognised - typo, stray whitespace, or a rotated key |
| `NoSubscriptionException` | 401 | Key is fine, but the account has no active plan |
| `PaymentRequiredException` | 402 | Billing block - base class for the three below |
| `QuotaExceededException` | 402 | The plan's requests for this period are used up |
| `CreditLimitExceededException` | 402 | Unpaid balance is past the account's credit limit |
| `PaymentFailedException` | 402 | A card payment was declined three times |
| `BlockedException` | 403 | Blocked by bot protection on every path |
| `NotFoundException` | 404 | Page loaded but held no image (`getImage` only) |
| `BrowserTimeoutException` | 408 | Our browser run timed out before the page was ready |
| `UnsupportedContentException` | 415 | The URL serves something other than HTML |
| `ValidationException` | 422 | Missing or wrong-typed parameter; `$body` holds the `detail` array |
| `RateLimitException` | 429 | Too many requests |
| `UpstreamOutageException` | 503 | The target origin is down |
| `ServerException` | 5xx | Unexpected server error, including a 504 upstream timeout |
| `TimeoutException` | - | This client gave up locally before the API answered |
| `ConnectionException` | - | Could not reach the API |

Transient failures (429, 502, 503, 504 and network errors) are retried automatically with exponential backoff. A 401 or 402 is never retried - it clears when the key or the billing state changes, not on another attempt. Neither is billed or counted against your quota, because the request is refused before anything is scraped.

### Billing errors (402)

The three billing blocks share a status code and differ only in their message, so the client throws a dedicated exception for each:

```php
use ScrapeUnblocker\Exception\CreditLimitExceededException;
use ScrapeUnblocker\Exception\PaymentFailedException;
use ScrapeUnblocker\Exception\QuotaExceededException;

try {
    $html = $su->getPageSource('https://example.com');
} catch (QuotaExceededException $e) {
    // plan quota (plus any overage allowance) is used up for this period
} catch (CreditLimitExceededException $e) {
    // unpaid balance passed the account credit limit
} catch (PaymentFailedException $e) {
    // card declined three times - update the payment method
}
```

When more than one applies, the most serious wins: failed payment outranks credit limit, which outranks quota. All three lift by themselves once the billing state changes - access returns within about a minute, and the API key stays the same. One catch worth knowing: subscribing to a new plan does **not** clear `PaymentFailedException`, because the old unpaid invoice stays open until it is paid.

Full details for every status code: [developers.scrapeunblocker.com/errors](https://developers.scrapeunblocker.com/errors).

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
