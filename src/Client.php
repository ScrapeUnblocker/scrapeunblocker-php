<?php

declare(strict_types=1);

namespace ScrapeUnblocker;

use ScrapeUnblocker\Exception\ApiException;
use ScrapeUnblocker\Exception\AuthenticationException;
use ScrapeUnblocker\Exception\BlockedException;
use ScrapeUnblocker\Exception\BrowserTimeoutException;
use ScrapeUnblocker\Exception\ConnectionException;
use ScrapeUnblocker\Exception\CreditLimitExceededException;
use ScrapeUnblocker\Exception\InvalidRequestException;
use ScrapeUnblocker\Exception\NoSubscriptionException;
use ScrapeUnblocker\Exception\NotFoundException;
use ScrapeUnblocker\Exception\PaymentFailedException;
use ScrapeUnblocker\Exception\PaymentRequiredException;
use ScrapeUnblocker\Exception\QuotaExceededException;
use ScrapeUnblocker\Exception\RateLimitException;
use ScrapeUnblocker\Exception\ScrapeUnblockerException;
use ScrapeUnblocker\Exception\ServerException;
use ScrapeUnblocker\Exception\TimeoutException;
use ScrapeUnblocker\Exception\UnsupportedContentException;
use ScrapeUnblocker\Exception\UpstreamOutageException;
use ScrapeUnblocker\Exception\ValidationException;

/**
 * Client for the ScrapeUnblocker API.
 *
 * ```php
 * $su = new \ScrapeUnblocker\Client('YOUR_API_KEY');
 * $html = $su->getPageSource('https://example.com');
 * ```
 */
final class Client
{
    private const DEFAULT_BASE_URL = 'https://api.scrapeunblocker.com';
    private const VERSION = '0.2.0';
    private const API_KEY_HEADER = 'x-scrapeunblocker-key';
    private const RETRYABLE = [429, 502, 503, 504];

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly int $timeout;
    private readonly int $maxRetries;
    /** @var callable(string,array):array */
    private $transport;

    public readonly Skyscanner $skyscanner;

    /**
     * @param string|null $apiKey Your API key. Falls back to the
     *                            SCRAPEUNBLOCKER_KEY environment variable.
     * @param array{base_url?:string,timeout?:int,max_retries?:int,transport?:callable} $options
     */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        $key = $apiKey ?? getenv('SCRAPEUNBLOCKER_KEY') ?: null;
        if (!$key) {
            throw new ScrapeUnblockerException(
                'No API key provided. Pass it to the constructor or set the ' .
                'SCRAPEUNBLOCKER_KEY environment variable. Get your key at ' .
                'https://app.scrapeunblocker.com'
            );
        }
        $this->apiKey = $key;
        $this->baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $this->timeout = $options['timeout'] ?? 180;
        $this->maxRetries = $options['max_retries'] ?? 2;
        $this->transport = $options['transport'] ?? [$this, 'curlTransport'];
        $this->skyscanner = new Skyscanner($this);
    }

    /**
     * Fetch a URL and return the fully rendered HTML.
     *
     * Options: proxy_country, time_sleep, method, value, method_timeout, and
     * 'steps' - an ordered list of browser actions run in a real browser after
     * the page loads (see below). Steps run once and are non-idempotent; if a
     * step fails the API answers HTTP 422 and this client raises a
     * ValidationException whose body holds { error: "step_failed", step_index,
     * action, reason, selector, html }.
     *
     * Each step is an associative array with an 'action' key and its fields:
     * - wait_for   { selector, selector_type?, timeout_ms? }
     * - wait_for_text { value, timeout_ms? }
     * - wait       { value } (milliseconds)
     * - click      { selector, selector_type?, timeout_ms? }
     * - type       { selector, selector_type?, value, clear?, timeout_ms? }
     * - select     { selector, selector_type?, value, timeout_ms? }
     * - press_key  { value } (Enter, Tab, Escape, ArrowDown, ...)
     * - scroll     { value } ("bottom" or a pixel count)
     * selector_type is one of css (default), xPath, className, tagName.
     *
     * ```php
     * $html = $su->getPageSource('https://example.com/search', [
     *     'steps' => [
     *         ['action' => 'type', 'selector' => '#q', 'value' => 'laptops'],
     *         ['action' => 'click', 'selector' => 'button[type=submit]'],
     *         ['action' => 'wait_for', 'selector' => '.results'],
     *     ],
     * ]);
     * ```
     *
     * @param array{proxy_country?:string,time_sleep?:int,method?:string,value?:string,method_timeout?:int,steps?:list<array<string,mixed>>} $options
     */
    public function getPageSource(string $url, array $options = []): string
    {
        return $this->request('/getPageSource', [
            'url' => $url,
            'proxy_country' => $options['proxy_country'] ?? null,
            'time_sleep' => $options['time_sleep'] ?? null,
            'method' => $options['method'] ?? null,
            'value' => $options['value'] ?? null,
            'method_timeout' => $options['method_timeout'] ?? null,
            'steps' => $this->encodeSteps($options['steps'] ?? null),
        ])['body'];
    }

    /**
     * Fetch a URL and return its elements as an array instead of HTML.
     *
     * With list_elements the API answers with { url, count, elements: [...] }
     * rather than a rendered document - the same request, a structured result.
     * Accepts the same browser options as getPageSource(), including 'steps' to
     * drive the page before the elements are collected.
     *
     * ```php
     * $out = $su->listElements('https://example.com', [
     *     'steps' => [['action' => 'scroll', 'value' => 'bottom']],
     * ]);
     * echo $out['count'];
     * print_r($out['elements']);
     * ```
     *
     * @param array{proxy_country?:string,time_sleep?:int,method?:string,value?:string,method_timeout?:int,steps?:list<array<string,mixed>>} $options
     * @return array<mixed>
     */
    public function listElements(string $url, array $options = []): array
    {
        return $this->postJson('/getPageSource', [
            'url' => $url,
            'list_elements' => true,
            'proxy_country' => $options['proxy_country'] ?? null,
            'time_sleep' => $options['time_sleep'] ?? null,
            'method' => $options['method'] ?? null,
            'value' => $options['value'] ?? null,
            'method_timeout' => $options['method_timeout'] ?? null,
            'steps' => $this->encodeSteps($options['steps'] ?? null),
        ]);
    }

    /** Fetch a URL and return structured JSON instead of HTML. */
    public function getParsed(string $url, array $options = []): ParsedPage
    {
        $body = $this->request('/getPageSource', [
            'url' => $url,
            'parsed_data' => true,
            'proxy_country' => $options['proxy_country'] ?? null,
            'time_sleep' => $options['time_sleep'] ?? null,
            'refresh_rules' => ($options['refresh_rules'] ?? false) ? true : null,
            'rules_hint' => $options['rules_hint'] ?? null,
        ])['body'];

        return ParsedPage::fromArray($this->decodeJson($body));
    }

    /** Fetch a URL and also return the cookies and proxy that served it. */
    public function getPageWithCookies(string $url, array $options = []): PageResult
    {
        $body = $this->request('/getPageSource', [
            'url' => $url,
            'get_cookies' => true,
            'proxy_country' => $options['proxy_country'] ?? null,
            'time_sleep' => $options['time_sleep'] ?? null,
        ])['body'];

        return PageResult::fromArray($this->decodeJson($body));
    }

    /** Run a Google search and return the parsed SERP as an array. */
    public function serp(string $keyword, array $options = []): array
    {
        return $this->postJson('/serpApi', [
            'keyword' => $keyword,
            'proxy_country' => $options['proxy_country'] ?? null,
            'pages_to_check' => $options['pages_to_check'] ?? 1,
            'wait_after_load' => ($options['wait_after_load'] ?? 0) ?: null,
            'captcha_pause' => ($options['captcha_pause'] ?? 0) ?: null,
        ]);
    }

    /**
     * Search Google Local (Maps) and return the businesses as an array.
     *
     * Returns up to ~20 businesses, each with name, rating, reviews, price,
     * category, address, hours and a top review snippet. Local results are
     * location-sensitive - set 'proxy_country' (and optionally 'gl').
     */
    public function googleLocal(string $keyword, array $options = []): array
    {
        return $this->postJson('/maps/google-local', [
            'keyword' => $keyword,
            'proxy_country' => $options['proxy_country'] ?? null,
            'hl' => $options['hl'] ?? null,
            'gl' => $options['gl'] ?? null,
        ]);
    }

    /**
     * Search Oopbuy goods and return the products as an array.
     *
     * Searches the "1688" channel by default ("taobao" and "official" are
     * also supported) and returns products with USD and CNY prices, images
     * and monthly sales. Oopbuy trademark-blocks brand keywords at its own
     * backend: those return a successful 200 with keywordRejected = true and
     * an empty results array, not an error.
     */
    public function oopbuySearch(string $keyword, array $options = []): array
    {
        return $this->postJson('/goods/oopbuy-search', [
            'keyword' => $keyword,
            'channel' => $options['channel'] ?? null,
            'page' => $options['page'] ?? null,
            'page_size' => $options['page_size'] ?? null,
            'sort' => $options['sort'] ?? null,
            'proxy_country' => $options['proxy_country'] ?? null,
        ]);
    }

    /**
     * Search eBay and return the listings as an array.
     *
     * Each listing carries title, numeric price and currency, condition (with
     * a normalised conditionCode), seller username and feedback, shipping
     * cost, sold/watcher/bid counts, image and a clean item URL.
     *
     * Options: marketplace (default 'ebay.com'), page, page_size (60, 120 or
     * 240), condition ('new', 'open_box', 'refurbished', 'used', 'for_parts'),
     * sort ('best_match', 'newly_listed', 'ending_soon', 'price_asc',
     * 'price_desc'), listing_type ('all', 'buy_it_now', 'auction'), min_price,
     * max_price, free_shipping, seller, category, proxy_country.
     *
     * When eBay finds no exact match it still serves a page of loosely related
     * suggestions, and the response then carries exactMatches = false.
     */
    public function ebaySearch(string $keyword, array $options = []): array
    {
        return $this->postJson('/marketplace/ebay-search', [
            'keyword' => $keyword,
            'marketplace' => $options['marketplace'] ?? null,
            'page' => $options['page'] ?? null,
            'page_size' => $options['page_size'] ?? null,
            'condition' => $options['condition'] ?? null,
            'sort' => $options['sort'] ?? null,
            'listing_type' => $options['listing_type'] ?? null,
            'min_price' => $options['min_price'] ?? null,
            'max_price' => $options['max_price'] ?? null,
            'free_shipping' => ($options['free_shipping'] ?? false) ? true : null,
            'seller' => $options['seller'] ?? null,
            'category' => $options['category'] ?? null,
            'proxy_country' => $options['proxy_country'] ?? null,
        ]);
    }

    /**
     * Scrape one Amazon product by ASIN or URL and return it as an array.
     *
     * Returns title, brand, numeric price and currency, list price and
     * savings, availability, rating, review count, seller, feature bullets,
     * categories and images. Prices come back in the marketplace's own
     * currency: proxy_country defaults to the marketplace's home country
     * (amazon.com -> US), pinning the exit over the ISP pool.
     *
     * Options: asin (10-char id, pair with marketplace) OR url (full product
     * URL), marketplace (default 'amazon.com'), proxy_country.
     */
    public function amazonProduct(array $options = []): array
    {
        return $this->postJson('/marketplace/amazon-product', [
            'asin' => $options['asin'] ?? null,
            'url' => $options['url'] ?? null,
            'marketplace' => $options['marketplace'] ?? null,
            'proxy_country' => $options['proxy_country'] ?? null,
        ]);
    }

    /**
     * Search Amazon and return the result cards as an array.
     *
     * Each card carries asin, title, numeric price and currency, list price,
     * rating, review count, a clean product URL, image and the sponsored /
     * prime flags. Fetch a card's full detail with amazonProduct(). Prices are
     * in the marketplace's own currency.
     *
     * Options: marketplace (default 'amazon.com'), page, sort ('featured',
     * 'price_asc', 'price_desc', 'avg_review', 'newest'), min_price, max_price,
     * proxy_country.
     */
    public function amazonSearch(string $keyword, array $options = []): array
    {
        return $this->postJson('/marketplace/amazon-search', [
            'keyword' => $keyword,
            'marketplace' => $options['marketplace'] ?? null,
            'page' => $options['page'] ?? null,
            'sort' => $options['sort'] ?? null,
            'min_price' => $options['min_price'] ?? null,
            'max_price' => $options['max_price'] ?? null,
            'proxy_country' => $options['proxy_country'] ?? null,
        ]);
    }

    /** Fetch an image URL through the bypass chain and return its raw bytes. */
    public function getImage(string $url, array $options = []): string
    {
        return $this->request('/getImage', [
            'url' => $url,
            'proxy_country' => $options['proxy_country'] ?? null,
        ])['body'];
    }

    /**
     * @internal
     * @return array<mixed>
     */
    public function postJson(string $path, array $params): array
    {
        return $this->decodeJson($this->request($path, $params)['body']);
    }

    /**
     * @param array<string,mixed> $params
     * @return array{status:int,body:string}
     */
    private function request(string $path, array $params): array
    {
        $url = $this->baseUrl . $path . '?' . $this->buildQuery($params);
        $headers = [
            self::API_KEY_HEADER . ': ' . $this->apiKey,
            'User-Agent: scrapeunblocker-php/' . self::VERSION,
            'Accept: */*',
        ];

        $attempt = 0;
        while (true) {
            $result = ($this->transport)($url, $headers);
            $status = (int) $result['status'];
            $body = (string) ($result['body'] ?? '');

            if (in_array($status, self::RETRYABLE, true) && $attempt < $this->maxRetries) {
                usleep((int) (min(0.5 * (2 ** $attempt), 8.0) * 1_000_000));
                $attempt++;
                continue;
            }

            if ($status >= 200 && $status < 300) {
                return ['status' => $status, 'body' => $body];
            }

            throw $this->errorForStatus($status, $body);
        }
    }

    /**
     * JSON-encode the browser steps into the single 'steps' query param the API
     * expects. Null or an empty list drops the param entirely.
     *
     * @param list<array<string,mixed>>|null $steps
     */
    private function encodeSteps(?array $steps): ?string
    {
        if ($steps === null || $steps === []) {
            return null;
        }

        return json_encode(array_values($steps));
    }

    /**
     * @param array<string,mixed> $params
     */
    private function buildQuery(array $params): string
    {
        $clean = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $clean[$key] = $value;
        }

        return http_build_query($clean);
    }

    private function decodeJson(string $body): array
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new ScrapeUnblockerException('Expected a JSON response but could not decode it.');
        }

        return $decoded;
    }

    private function errorForStatus(int $status, string $body): ApiException
    {
        $snippet = trim(preg_replace('/\s+/', ' ', $body) ?? '');
        if (strlen($snippet) > 200) {
            $snippet = substr($snippet, 0, 200) . '...';
        }
        $base = match ($status) {
            400 => 'Invalid request (bad URL, unsupported scheme, or missing API key header)',
            401 => 'Authentication failed - key not recognised, or account has no active plan',
            402 => 'Billing block - quota exceeded, credit limit exceeded, or a failed payment',
            403 => 'Target blocked by bot protection on every bypass path',
            404 => 'Requested element not found on the page',
            408 => 'Browser run timed out before the page was ready',
            415 => 'URL does not serve HTML',
            422 => 'Validation error - see the detail array in the response body',
            429 => 'Rate limited - too many requests',
            503 => 'Upstream origin returned a server-side outage page',
            504 => 'Fetch timed out upstream',
            default => "API returned HTTP {$status}",
        };
        $message = $snippet !== '' ? "{$base}: {$snippet}" : $base;

        return match (true) {
            $status === 400 => new InvalidRequestException($message, $status, $body),
            $status === 401 => $this->authError($message, $status, $body),
            $status === 402 => $this->billingError($message, $status, $body),
            $status === 403 => new BlockedException($message, $status, $body),
            $status === 404 => new NotFoundException($message, $status, $body),
            $status === 408 => new BrowserTimeoutException($message, $status, $body),
            $status === 415 => new UnsupportedContentException($message, $status, $body),
            $status === 422 => new ValidationException($message, $status, $body),
            $status === 429 => new RateLimitException($message, $status, $body),
            $status === 503 => new UpstreamOutageException($message, $status, $body),
            $status >= 500 => new ServerException($message, $status, $body),
            default => new ApiException($message, $status, $body),
        };
    }

    /**
     * A 401 is either an unknown key or a recognised key on an account without a plan,
     * and only the body tells them apart. Anything unrecognised stays on the general
     * AuthenticationException rather than guessing.
     */
    private function authError(string $message, int $status, string $body): AuthenticationException
    {
        if (str_contains(strtolower($body), 'no valid subscription')) {
            return new NoSubscriptionException($message, $status, $body);
        }

        return new AuthenticationException($message, $status, $body);
    }

    /**
     * The three billing blocks share a status code and differ only in their plain-text
     * body. An unrecognised body falls back to PaymentRequiredException.
     */
    private function billingError(string $message, int $status, string $body): PaymentRequiredException
    {
        $text = strtolower($body);

        return match (true) {
            str_contains($text, 'quota exceeded') => new QuotaExceededException($message, $status, $body),
            str_contains($text, 'credit limit exceeded') => new CreditLimitExceededException($message, $status, $body),
            str_contains($text, 'payment failed') => new PaymentFailedException($message, $status, $body),
            default => new PaymentRequiredException($message, $status, $body),
        };
    }

    /**
     * @param list<string> $headers
     * @return array{status:int,body:string}
     */
    private function curlTransport(string $url, array $headers): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 30,
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if ($errno === CURLE_OPERATION_TIMEOUTED) {
                throw new TimeoutException("Request timed out after {$this->timeout}s: {$error}");
            }
            throw new ConnectionException("Could not reach the API: {$error}");
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => (string) $body];
    }
}
