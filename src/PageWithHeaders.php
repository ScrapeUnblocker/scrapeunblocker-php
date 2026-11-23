<?php

declare(strict_types=1);

namespace ScrapeUnblocker;

/**
 * HTML plus the target's status, final URL and headers (getPageWithHeaders()).
 * $headers has lowercase names and 'set-cookie' as a list of strings; $cookies
 * is null unless the call asked for them. Any field the render path could not
 * observe is null.
 */
final class PageWithHeaders
{
    /**
     * @param int|null $statusCode the target's own status
     * @param string|null $finalUrl the URL after redirects
     * @param array<string,string|list<string>>|null $headers the target's response headers
     * @param array<string,string>|null $requestHeaders the headers sent to the target
     * @param string|null $proxy the exit's lowercase ISO-2 country
     */
    public function __construct(
        public readonly ?string $html,
        public readonly ?int $statusCode,
        public readonly ?string $finalUrl,
        public readonly ?array $headers,
        public readonly ?array $requestHeaders,
        public readonly ?string $proxy,
        public readonly mixed $cookies,
        public readonly array $raw,
        /**
         * True when the call named a sticky 'session' and the API answered it
         * through another IP than the session's (the X-Session-Changed
         * header); the session has then moved to the new IP, unless the call
         * ran in parallel over the session's limit.
         */
        public readonly bool $sessionChanged = false,
    ) {
    }

    public static function fromArray(array $payload, bool $sessionChanged = false): self
    {
        return new self(
            isset($payload['html']) ? (string) $payload['html'] : null,
            isset($payload['status_code']) ? (int) $payload['status_code'] : null,
            isset($payload['final_url']) ? (string) $payload['final_url'] : null,
            is_array($payload['headers'] ?? null) ? $payload['headers'] : null,
            is_array($payload['request_headers'] ?? null) ? $payload['request_headers'] : null,
            isset($payload['proxy']) ? (string) $payload['proxy'] : null,
            $payload['cookies'] ?? null,
            $payload,
            $sessionChanged,
        );
    }
}
