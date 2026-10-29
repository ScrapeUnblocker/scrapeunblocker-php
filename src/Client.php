<?php

declare(strict_types=1);

namespace ScrapeUnblocker;

use ScrapeUnblocker\Exception\ApiException;
use ScrapeUnblocker\Exception\AuthenticationException;
use ScrapeUnblocker\Exception\BlockedException;
use ScrapeUnblocker\Exception\BrowserTimeoutException;
use ScrapeUnblocker\Exception\BudgetExceededException;
use ScrapeUnblocker\Exception\ConnectionException;
use ScrapeUnblocker\Exception\CreditLimitExceededException;
use ScrapeUnblocker\Exception\InvalidRequestException;
use ScrapeUnblocker\Exception\NoSubscriptionException;
use ScrapeUnblocker\Exception\NoDataExtractedException;
use ScrapeUnblocker\Exception\NotFoundException;
use ScrapeUnblocker\Exception\TargetNotFoundException;
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
    use PluginsTrait;

    private const DEFAULT_BASE_URL = 'https://api.scrapeunblocker.com';
    private const VERSION = '0.15.0';
    private const API_KEY_HEADER = 'x-scrapeunblocker-key';
    private const RETRYABLE = [429, 502, 503, 504];

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly int $timeout;
    private readonly int $maxRetries;
    /** @var callable(string,array):array */
    private $transport;

    public readonly Skyscanner $skyscanner;
    public readonly Southwest $southwest;

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
        $this->southwest = new Southwest($this);
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
     * When the target page itself answers 404 or 410 this throws
     * TargetNotFoundException (billed; the not-found page is on ->html).
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
     * Fetch a URL and return the rendered page as clean Markdown.
     *
     * Built for LLMs, agents and RAG pipelines. The page is rendered in a real
     * browser behind the same anti-bot bypass chain as getPageSource(), then
     * converted to Markdown: headings, lists, tables, absolute links, images
     * with their alt text and code blocks, without scripts, styles, navigation,
     * footers, forms or hidden elements. Billed like an HTML call.
     *
     * Options: 'main_content_only' (default true) keeps only the page's main
     * content, without navigation, the site header, footer and sidebars; pass
     * false to convert the whole page. 'proxy_country', 'time_sleep', 'method',
     * 'value', 'method_timeout' and 'steps' work as in getPageSource().
     *
     * ```php
     * $md = $su->getMarkdown('https://example.com/article');
     * $full = $su->getMarkdown('https://example.com', ['main_content_only' => false]);
     * ```
     *
     * When the target page itself answers 404 or 410 this throws
     * TargetNotFoundException (billed; the not-found page, as Markdown, is on
     * ->html).
     *
     * @param array{main_content_only?:bool,proxy_country?:string,time_sleep?:int,method?:string,value?:string,method_timeout?:int,steps?:list<array<string,mixed>>} $options
     */
    public function getMarkdown(string $url, array $options = []): string
    {
        return $this->request('/getPageSource', [
            'url' => $url,
            'markdown' => true,
            'main_content_only' => ($options['main_content_only'] ?? true) === false ? false : null,
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

    /**
     * Fetch a URL and return structured JSON instead of HTML.
     *
     * When the page rendered but held no structured data, the result has
     * ->dataExtracted false, empty ->data and the rendered page on ->html (billed
     * like getPageSource()). Throws TargetNotFoundException (billed, ->html null)
     * when the target page itself answered 404 or 410.
     */
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

    /**
     * Run a Google search and return the parsed SERP as an array.
     * The 'captcha_pause' option is kept for compatibility; the API ignores it.
     */
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
     * Search Google Images and return the image results as an array.
     *
     * Returns image results, each with the full-size 'imageUrl' and its
     * 'sourceDomain', plus the source page URL, title, source name, thumbnail
     * URL, pixel dimensions and file size. Options: 'proxy_country' (ISO-2;
     * the Google market follows it), 'pages' (1-5, ~100 results each; each
     * page fetched is billed as one request, see 'pagesFetched'), 'gl'
     * (optional ISO-2 lowercase market override), 'max_results' (1-500),
     * 'time_range' ('hour', 'day', 'week', 'month', 'year': only images Google
     * found in that recent period, e.g. the new ones since the last scheduled
     * scan) or 'date_from' / 'date_to' ('YYYY-MM-DD', each optional, a fixed
     * window). 'time_range' cannot be combined with 'date_from' / 'date_to'.
     */
    public function googleImages(string $keyword, array $options = []): array
    {
        return $this->postJson('/images/google-search', [
            'q' => $keyword,
            'pages' => $options['pages'] ?? null,
            'gl' => $options['gl'] ?? null,
            'max_results' => $options['max_results'] ?? null,
            'proxy_country' => $options['proxy_country'] ?? null,
            'time_range' => $options['time_range'] ?? null,
            'date_from' => $options['date_from'] ?? null,
            'date_to' => $options['date_to'] ?? null,
        ]);
    }

    /**
     * Fetch an advertiser's Meta (Facebook) Ad Library ads and return them as
     * an array.
     *
     * Options: country, active_status ('active', 'inactive', 'all'),
     * media_type ('all', 'image', 'video', 'meme') and max_ads. Omitted
     * options are dropped and the API applies its own defaults.
     */
    public function metaAdLibrary(string $advertiser, array $options = []): array
    {
        return $this->postJson('/ads/meta-ad-library', [
            'advertiser' => $advertiser,
            'country' => $options['country'] ?? null,
            'active_status' => $options['active_status'] ?? null,
            'media_type' => $options['media_type'] ?? null,
            'max_ads' => $options['max_ads'] ?? null,
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
     * (amazon.com -> US).
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

    /**
     * Fetch an image and return its bytes exactly as the image host serves them -
     * the original format (JPEG, PNG, WebP, AVIF, GIF, SVG, ...), nothing
     * re-encoded. $url must point at the image itself: a page URL throws
     * UnsupportedContentException, and an image the host answers 404 or 410 for
     * throws TargetNotFoundException.
     */
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
    /**
     * Scrape a public TikTok creator profile and its newest videos.
     *
     * Returns the exact follower, following, like and video counts, bio, bio
     * link, verified / private / organization / seller flags, avatar and a
     * `videos` array of the creator's newest posts (up to 200), each in the
     * full tiktokVideo() shape. Options: max_videos (0-200, default 10),
     * video_details (default true), proxy_country. No login.
     */
    public function tiktokProfile(string $username, array $options = []): array
    {
        return $this->postJson('/social/tiktok-profile', [
            'username' => $username,
            'max_videos' => $options['max_videos'] ?? null,
            'video_details' => (($options['video_details'] ?? true) === false) ? false : null,
            'proxy_country' => $options['proxy_country'] ?? null,
        ]);
    }

    /**
     * Scrape one TikTok video or photo post: exact plays, likes, comments,
     * shares, saves and reposts, hashtags, mentions, author, music, play /
     * download URLs, subtitle tracks and, with include_transcript, the
     * transcript as text. Options: include_transcript, transcript_language,
     * proxy_country.
     */
    public function tiktokVideo(string $url, array $options = []): array
    {
        return $this->postJson('/social/tiktok-video', [
            'url' => $url,
            'include_transcript' => ($options['include_transcript'] ?? false) ? true : null,
            'transcript_language' => $options['transcript_language'] ?? null,
            'proxy_country' => $options['proxy_country'] ?? null,
        ]);
    }

    /**
     * Scrape a TikTok hashtag: total views and videos plus its videos (up to
     * 200). Options: max_videos (0-200, default 10), video_details (default
     * true), proxy_country.
     */
    public function tiktokHashtag(string $hashtag, array $options = []): array
    {
        return $this->postJson('/social/tiktok-hashtag', [
            'hashtag' => $hashtag,
            'max_videos' => $options['max_videos'] ?? null,
            'video_details' => (($options['video_details'] ?? true) === false) ? false : null,
            'proxy_country' => $options['proxy_country'] ?? null,
        ]);
    }

    /**
     * Search TikTok videos by keyword. Runs in a browser session that clears
     * TikTok's captcha, so `results` carry TikTok's own ranking (region via
     * proxy_country), each in the full tiktokVideo() shape. Options:
     * max_results (1-200, default 20), proxy_country. 20-45 s.
     */
    public function tiktokSearch(string $query, array $options = []): array
    {
        return $this->postJson('/social/tiktok-search', [
            'query' => $query,
            'max_results' => $options['max_results'] ?? null,
            'proxy_country' => $options['proxy_country'] ?? null,
        ]);
    }

    /**
     * Scrape the comments of a TikTok post (text, date, likes, reply count,
     * author, creator flags, preloaded replies) plus totalComments and
     * hasMore. Runs in a browser session that clears TikTok's captcha.
     * Options: max_comments (1-500, default 50), proxy_country. 20-45 s.
     */
    public function tiktokComments(string $url, array $options = []): array
    {
        return $this->postJson('/social/tiktok-comments', [
            'url' => $url,
            'max_comments' => $options['max_comments'] ?? null,
            'proxy_country' => $options['proxy_country'] ?? null,
        ]);
    }

    /**
     * Search Temu by keyword. Returns the matching product urls (about 40 per
     * page); fetch each url's data with temuProduct().
     *
     * @deprecated Temu's plugin is unlisted; kept for compatibility.
     * @param string $keyword Search phrase, e.g. 'kids toys' or 'phone case'.
     * @param array{limit?: int, sort?: string} $options
     *   - limit: Max product urls to return (one search page carries ~40). API default: 40.
     *   - sort: Result order, using Temu's own sort: `relevance` (default), `best_selling`,
     *     `recent`, `price_asc` or `price_desc`.
     * @return array<mixed>
     * Calls POST /goods/temu-search.
     */
    public function temuSearch(string $keyword, array $options = []): array
    {
        return $this->postJson('/goods/temu-search', [
            'keyword' => $keyword,
            'limit' => $options['limit'] ?? null,
            'sort' => $options['sort'] ?? null,
        ]);
    }

    /**
     * Scrape a Temu product page.
     *
     * @deprecated Temu's plugin is unlisted; kept for compatibility.
     * @param string $url Full Temu product url, e.g.
     * 'https://www.temu.com/xyz-g-601099512345678.html'. Must be a product page (its path ends in
     * `-g-<id>.html`).
     * @return array<mixed>
     * Calls POST /goods/temu-product.
     */
    public function temuProduct(string $url): array
    {
        return $this->postJson('/goods/temu-product', [
            'url' => $url,
        ]);
    }

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

            $responseHeaders = [];
            foreach ($result['headers'] ?? [] as $name => $value) {
                $responseHeaders[strtolower((string) $name)] = is_array($value) ? implode(', ', $value) : (string) $value;
            }
            throw $this->errorForStatus($status, $body, $responseHeaders);
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
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            // A list is a repeated parameter (stars=4&stars=5), as the API reads it.
            foreach (is_array($value) ? $value : [$value] as $item) {
                if (is_bool($item)) {
                    $item = $item ? 'true' : 'false';
                }
                $parts[] = urlencode((string) $key) . '=' . urlencode((string) $item);
            }
        }

        return implode('&', $parts);
    }

    private function decodeJson(string $body): array
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new ScrapeUnblockerException('Expected a JSON response but could not decode it.');
        }

        return $decoded;
    }

    /**
     * The API passes a target's "page does not exist" answer through with its
     * status and an X-Origin-Status header. A 404 without that header is the
     * API's own (a plugin lookup, a missing element) and returns null so the
     * general NotFoundException applies.
     *
     * @param array<string,string> $headers lowercase header names
     */
    private function targetNotFound(int $status, string $body, array $headers): ?TargetNotFoundException
    {
        $origin = $headers['x-origin-status'] ?? '';
        if (!in_array($status, [404, 410], true) || $origin === '') {
            return null;
        }
        $originStatus = ctype_digit(trim($origin)) ? (int) trim($origin) : $status;
        $html = $body;
        $decoded = json_decode($body, true);
        if (is_array($decoded) && !array_is_list($decoded)) {
            $html = is_string($decoded['html'] ?? null) ? $decoded['html'] : null;
        }
        $message = "Target page does not exist (HTTP {$originStatus}). This is the target's own "
            . 'answer, not a block; the call is billed.';

        return new TargetNotFoundException(
            $message,
            $status,
            $body,
            $originStatus,
            $html,
            $headers['x-destination-url'] ?? null,
        );
    }

    /**
     * Legacy: a 422 {"error": "no_data_extracted", "detail"} (the API no longer
     * sends it). Anything else returns null so the general ValidationException
     * applies.
     */
    private function noDataExtracted(int $status, string $body): ?NoDataExtractedException
    {
        if ($status !== 422) {
            return null;
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || ($decoded['error'] ?? null) !== 'no_data_extracted') {
            return null;
        }
        $detail = is_string($decoded['detail'] ?? null) && $decoded['detail'] !== '' ? $decoded['detail'] : null;
        $message = $detail ?? 'The page was rendered, but no structured data could be extracted from it. Not billed.';
        if (!str_contains(strtolower($message), 'not billed')) {
            $message .= ' Not billed.';
        }

        return new NoDataExtractedException($message, $status, $body, $detail);
    }

    /**
     * @param array<string,string> $headers lowercase header names
     */
    private function errorForStatus(int $status, string $body, array $headers = []): ApiException
    {
        $targetError = $this->targetNotFound($status, $body, $headers);
        if ($targetError !== null) {
            return $targetError;
        }
        $noDataError = $this->noDataExtracted($status, $body);
        if ($noDataError !== null) {
            return $noDataError;
        }

        $snippet = trim(preg_replace('/\s+/', ' ', $body) ?? '');
        if (strlen($snippet) > 200) {
            $snippet = substr($snippet, 0, 200) . '...';
        }
        $base = match ($status) {
            400 => 'Invalid request (bad URL, unsupported scheme, or missing API key header)',
            401 => 'Authentication failed - key not recognised, or account has no active plan',
            402 => 'Billing block - quota exceeded, credit limit exceeded, monthly budget limit reached, or a failed payment',
            403 => 'Target blocked by bot protection on every bypass path',
            404 => 'Requested element not found on the page',
            408 => 'Browser run timed out before the page was ready',
            415 => 'URL serves a content type this call cannot return',
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
     * The four billing blocks share a status code and differ only in their plain-text
     * body. An unrecognised body falls back to PaymentRequiredException.
     */
    private function billingError(string $message, int $status, string $body): PaymentRequiredException
    {
        $text = strtolower($body);

        return match (true) {
            str_contains($text, 'quota exceeded') => new QuotaExceededException($message, $status, $body),
            str_contains($text, 'credit limit exceeded') => new CreditLimitExceededException($message, $status, $body),
            str_contains($text, 'user set budget exceeded') => new BudgetExceededException($message, $status, $body),
            str_contains($text, 'payment failed') => new PaymentFailedException($message, $status, $body),
            default => new PaymentRequiredException($message, $status, $body),
        };
    }

    /**
     * @param list<string> $headers
     * @return array{status:int,body:string,headers:array<string,string>}
     */
    private function curlTransport(string $url, array $headers): array
    {
        $responseHeaders = [];
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
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

        return ['status' => $status, 'body' => (string) $body, 'headers' => $responseHeaders];
    }
}
