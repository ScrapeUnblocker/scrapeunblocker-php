<?php

declare(strict_types=1);

namespace ScrapeUnblocker;

use ScrapeUnblocker\Exception\ApiException;
use ScrapeUnblocker\Exception\AuthenticationException;
use ScrapeUnblocker\Exception\BlockedException;
use ScrapeUnblocker\Exception\ConnectionException;
use ScrapeUnblocker\Exception\InvalidRequestException;
use ScrapeUnblocker\Exception\RateLimitException;
use ScrapeUnblocker\Exception\ScrapeUnblockerException;
use ScrapeUnblocker\Exception\ServerException;
use ScrapeUnblocker\Exception\TimeoutException;
use ScrapeUnblocker\Exception\UpstreamOutageException;

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
    private const VERSION = '0.1.1';
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

    /** Fetch a URL and return the fully rendered HTML. */
    public function getPageSource(string $url, array $options = []): string
    {
        return $this->request('/getPageSource', [
            'url' => $url,
            'proxy_country' => $options['proxy_country'] ?? null,
            'time_sleep' => $options['time_sleep'] ?? null,
            'method' => $options['method'] ?? null,
            'value' => $options['value'] ?? null,
            'method_timeout' => $options['method_timeout'] ?? null,
        ])['body'];
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
            400 => 'Invalid request (bad URL or unsupported scheme)',
            401 => 'Authentication failed - check your API key',
            403 => 'Target blocked by bot protection on every bypass path',
            429 => 'Rate limited - too many requests',
            503 => 'Upstream origin returned a server-side outage page',
            default => "API returned HTTP {$status}",
        };
        $message = $snippet !== '' ? "{$base}: {$snippet}" : $base;

        return match (true) {
            $status === 400 => new InvalidRequestException($message, $status, $body),
            $status === 401 => new AuthenticationException($message, $status, $body),
            $status === 403 => new BlockedException($message, $status, $body),
            $status === 429 => new RateLimitException($message, $status, $body),
            $status === 503 => new UpstreamOutageException($message, $status, $body),
            $status >= 500 => new ServerException($message, $status, $body),
            default => new ApiException($message, $status, $body),
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
