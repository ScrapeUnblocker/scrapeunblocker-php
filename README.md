# ScrapeUnblocker PHP client

Official PHP client for the [ScrapeUnblocker](https://scrapeunblocker.com?utm_source=packagist&utm_medium=integration&utm_campaign=php-sdk) web scraping API.

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

Get your API key at [app.scrapeunblocker.com](https://app.scrapeunblocker.com?utm_source=packagist&utm_medium=integration&utm_campaign=php-sdk). The free trial does not require a credit card.

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

## Browser steps

Drive the page in the real browser after it loads - fill a search box, click a
button, wait for results, scroll to trigger lazy loading - then return the HTML
of the resulting page. Pass an ordered list of steps:

```php
$html = $su->getPageSource('https://example.com/search', [
    'steps' => [
        ['action' => 'type', 'selector' => '#q', 'value' => 'laptops'],
        ['action' => 'press_key', 'value' => 'Enter'],
        ['action' => 'wait_for', 'selector' => '.results'],
        ['action' => 'scroll', 'value' => 'bottom'],
    ],
]);
```

Available actions and their fields:

| Action | Fields |
|---|---|
| `wait_for` | `selector`, `selector_type?`, `timeout_ms?` |
| `wait_for_text` | `value` (the text), `timeout_ms?` |
| `wait` | `value` (milliseconds) |
| `click` | `selector`, `selector_type?`, `timeout_ms?` |
| `type` | `selector`, `selector_type?`, `value`, `clear?`, `timeout_ms?` |
| `select` | `selector`, `selector_type?`, `value`, `timeout_ms?` |
| `press_key` | `value` (`Enter`, `Tab`, `Escape`, `Backspace`, `Delete`, `Space`, `ArrowUp/Down/Left/Right`, `Home`, `End`, `PageUp`, `PageDown`) |
| `scroll` | `value` (`"bottom"` or a pixel count) |

`selector_type` is one of `css` (default), `xPath`, `className`, `tagName`.
Steps run once and are **non-idempotent**. If a step fails, the API answers
`422` and the client raises a `ValidationException` whose `$body` holds
`{ error: "step_failed", step_index, action, reason, selector, html }`.

## List elements

Ask the API to return the page's elements as structured JSON -
`{ url, count, elements: [...] }` - instead of a rendered document. Accepts the
same browser options as `getPageSource()`, including `steps`:

```php
$out = $su->listElements('https://example.com', [
    'steps' => [['action' => 'scroll', 'value' => 'bottom']],
]);
echo $out['count'], PHP_EOL;
print_r($out['elements']);
```

## Get parsed JSON

```php
$result = $su->getParsed('https://www.walmart.com/ip/12345');
echo $result->pageType;   // e.g. "product"
print_r($result->data);   // the fields
var_dump($result->dataExtracted); // false when nothing could be extracted; then $result->html holds the page

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

## Google Images

```php
$images = $su->googleImages('golden retriever puppy', ['proxy_country' => 'US', 'pages' => 3]);
foreach ($images['results'] as $img) {
    echo "{$img['imageUrl']} {$img['sourceDomain']} {$img['title']}\n";
}
```

## Meta Ad Library

```php
$ads = $su->metaAdLibrary('Nike', ['country' => 'US']);
foreach ($ads['results'] as $ad) {
    echo "{$ad['advertiser']} {$ad['adText']}\n";
}
```

Options: `country`, `active_status` (`active`, `inactive`, `all`), `media_type` (`all`, `image`, `video`, `meme`) and `max_ads`. Omit any of them and the API applies its own defaults.

## Oopbuy goods search

```php
$goods = $su->oopbuySearch('running shoes', ['channel' => '1688', 'sort' => 'best_selling', 'page_size' => 20]);
foreach ($goods['results'] as $item) {
    echo "{$item['title']} {$item['price']} {$item['url']}\n";
}
```

Channels: `1688` (default), `taobao`, `official`. Sort: `default`, `price_asc`, `price_desc`, `best_selling`. `page_size` up to 60. Oopbuy trademark-blocks brand keywords at its own backend: those come back as a successful `200` with `keywordRejected: true` and an empty `results` array, not an error.

## Amazon

Product and search data as an array, priced in the marketplace's own currency:

```php
// One product by ASIN (or ['url' => 'https://www.amazon.de/dp/B0BSHF7WHW'])
$product = $su->amazonProduct(['asin' => 'B0BSHF7WHW', 'marketplace' => 'amazon.com']);
echo $product['title'], ' ', $product['price'], ' ', $product['currency'], PHP_EOL;

// Keyword search
$results = $su->amazonSearch('wireless headphones', ['sort' => 'price_asc']);
foreach ($results['results'] as $item) {
    echo $item['title'], ' ', $item['price'], ' ', $item['currency'], ' ', $item['asin'], PHP_EOL;
}
```

`proxy_country` defaults to the marketplace's home country (`amazon.com` -> US, `amazon.de` -> DE), so prices come back in the right currency with no configuration. `sort` is one of `featured` (default), `price_asc`, `price_desc`, `avg_review`, `newest`.

## eBay search

```php
$items = $su->ebaySearch('iphone 13', [
    'marketplace' => 'ebay.com',
    'condition' => 'used',
    'sort' => 'newly_listed',
]);

if ($items['exactMatches']) {
    foreach ($items['results'] as $item) {
        echo $item['title'], ' ', $item['price'], ' ', $item['currency'], PHP_EOL;
    }
}
```

`marketplace` is any of the 19 regional eBay hosts (`ebay.com` default). `condition` is one of `new`, `open_box`, `refurbished`, `used`, `for_parts`; `sort` is one of `best_match` (default), `newly_listed`, `ending_soon`, `price_asc`, `price_desc`; `page_size` is 60, 120 or 240. `exactMatches` is `false` when eBay found nothing for the keyword and answered with its own loosely-related suggestions instead, so check it before using the listings.

### TikTok

```php
$profile = $su->tiktokProfile('nasa', ['max_videos' => 5]);     // exact stats + newest videos
$video = $su->tiktokVideo('https://www.tiktok.com/@nasa/video/7665075736742530317', ['include_transcript' => true]);
$tag = $su->tiktokHashtag('nasa', ['max_videos' => 10]);
$results = $su->tiktokSearch('space telescope', ['max_results' => 25]);   // TikTok's own ranking
$comments = $su->tiktokComments('https://www.tiktok.com/@nasa/video/7665075736742530317', ['max_comments' => 40]);
echo $profile['stats']['followers'], ' ', $video['stats']['plays'], ' ', $tag['stats']['views'], ' ', $comments['totalComments'];
```

Profiles and hashtags list up to 10 videos in a couple of seconds from TikTok's server-rendered widget; ask for more (up to 200) and the real grid is scrolled in a browser session.

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

## Southwest plugin

Southwest Airlines fares as raw booking JSON. Origin and destination are IATA
codes; omit `return_date` for a one-way search:

```php
$flights = $su->southwest->flights([
    'origin' => 'DAL', 'dest' => 'HOU',
    'depart_date' => '2026-10-20', 'return_date' => '2026-10-27',
]);
```

Options: `adults` (1-8, default 1), `fare_type` (`dollars` or `points`,
default `dollars`), `proxy_country` (default `US`) and `max_attempts` (1-5,
default 3).

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
    // 402: quota, credit limit, your monthly budget limit, or a failed payment - fix billing
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
| `PaymentRequiredException` | 402 | Billing block - base class for the four below |
| `QuotaExceededException` | 402 | The plan's requests for this period are used up |
| `CreditLimitExceededException` | 402 | Unpaid balance is past the account's credit limit |
| `BudgetExceededException` | 402 | This billing period's spend reached the monthly budget limit you set in your profile |
| `PaymentFailedException` | 402 | A card payment was declined three times |
| `BlockedException` | 403 | Blocked by bot protection on every path |
| `NotFoundException` | 404 | What you asked for does not exist - no image on the page (`getImage`), or a plugin lookup found nothing |
| `TargetNotFoundException` | 404 / 410 | The target page itself does not exist; carries `originStatus`, `html`, `destinationUrl` (subclass of `NotFoundException`, billed) |
| `BrowserTimeoutException` | 408 | Our browser run timed out before the page was ready |
| `UnsupportedContentException` | 415 | The URL serves something other than HTML |
| `ValidationException` | 422 | Missing or wrong-typed parameter; `$body` holds the `detail` array |
| `RateLimitException` | 429 | Too many requests |
| `UpstreamOutageException` | 503 | The target origin is down |
| `ServerException` | 5xx | Unexpected server error, including a 504 upstream timeout |
| `TimeoutException` | - | This client gave up locally before the API answered |
| `ConnectionException` | - | Could not reach the API |

### The target page does not exist (404 / 410)

When the site you scrape answers 404 or 410 itself, the API passes that status through with an `X-Origin-Status` header, and the client throws `TargetNotFoundException`. It is the target's final answer, so it is never retried, and it is billed like any delivered page. The not-found page is on `->html`:

```php
use ScrapeUnblocker\Exception\TargetNotFoundException;

try {
    $html = $su->getPageSource('https://example.com/removed-listing');
} catch (TargetNotFoundException $e) {
    echo $e->originStatus;   // 404 or 410
    echo $e->html;           // the target's own not-found page (can be empty)
}
```

`TargetNotFoundException` extends `NotFoundException`, so `catch (NotFoundException $e)` catches it too. A 404 without `X-Origin-Status` is the API's own and stays a plain `NotFoundException`.

With `getParsed()` the body is the parsed-data JSON (`{"data": {"page_type": "not_found", ...}}`), so `html` is `null` there; the raw JSON is on `->body`.

### No structured data on the page

When `getParsed()` renders the page but can extract no structured data from it, the API still answers 200: the result has `dataExtracted` false, empty `data`, the API's explanation on `detail` and the rendered page on `html`. The call is billed like `getPageSource()`, since you get the page:

```php
$page = $su->getParsed('https://example.com/some-page');
if (!$page->dataExtracted) {
    echo $page->detail;   // the API's explanation
    $html = $page->html;  // the rendered page - parse it yourself
}
```

`NoDataExtractedException` (for the 422 the API used to send here) is deprecated and no longer thrown; it stays in the package so existing `catch` blocks still resolve.

Transient failures (429, 502, 503, 504 and network errors) are retried automatically with exponential backoff. A 401 or 402 is never retried - it clears when the key or the billing state changes, not on another attempt. Neither is billed or counted against your quota, because the request is refused before anything is scraped.

### Billing errors (402)

The four billing blocks share a status code and differ only in their message, so the client throws a dedicated exception for each:

```php
use ScrapeUnblocker\Exception\BudgetExceededException;
use ScrapeUnblocker\Exception\CreditLimitExceededException;
use ScrapeUnblocker\Exception\PaymentFailedException;
use ScrapeUnblocker\Exception\QuotaExceededException;

try {
    $html = $su->getPageSource('https://example.com');
} catch (QuotaExceededException $e) {
    // plan quota (plus any overage allowance) is used up for this period
} catch (CreditLimitExceededException $e) {
    // unpaid balance passed the account credit limit
} catch (BudgetExceededException $e) {
    // this period's spend reached the monthly budget limit you set in your profile
} catch (PaymentFailedException $e) {
    // card declined three times - update the payment method
}
```

When more than one applies, the most serious wins: failed payment outranks credit limit, which outranks quota, which outranks your own budget limit. All four lift by themselves once the billing state changes - access returns within about a minute, and the API key stays the same. One catch worth knowing: subscribing to a new plan does **not** clear `PaymentFailedException`, because the old unpaid invoice stays open until it is paid.

`BudgetExceededException` means you set a monthly budget limit (EUR, excluding VAT) in your [profile](https://app.scrapeunblocker.com/dashboard/profile?utm_source=packagist&utm_medium=integration&utm_campaign=php-sdk) and this billing period's spend has reached it. Spend is counted the way your invoice is: the plan's fixed monthly fee, if any, plus the requests billed on top of it; requests paid from coupon credit do not count. The key works again at the start of the next billing period, or within about a minute after you raise or remove the limit.

Full details for every status code: [docs.scrapeunblocker.com/errors](https://docs.scrapeunblocker.com/errors).

## Configuration

```php
new Client('YOUR_API_KEY', [
    'base_url' => 'https://api.scrapeunblocker.com',
    'timeout' => 180,     // seconds; protected pages can be slow
    'max_retries' => 2,
]);
```

## Links

- Documentation: [docs.scrapeunblocker.com](https://docs.scrapeunblocker.com?utm_source=packagist&utm_medium=integration&utm_campaign=php-sdk)
- Website: [scrapeunblocker.com](https://scrapeunblocker.com?utm_source=packagist&utm_medium=integration&utm_campaign=php-sdk)
- Dashboard: [app.scrapeunblocker.com](https://app.scrapeunblocker.com?utm_source=packagist&utm_medium=integration&utm_campaign=php-sdk)

## License

MIT
