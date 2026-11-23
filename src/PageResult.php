<?php

declare(strict_types=1);

namespace ScrapeUnblocker;

/** HTML plus the cookies and proxy that served it (getPageWithCookies()). */
final class PageResult
{
    public function __construct(
        public readonly ?string $html,
        public readonly mixed $cookies,
        public readonly ?string $proxy,
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
            $payload['html'] ?? $payload['page_source'] ?? $payload['content'] ?? null,
            $payload['cookies'] ?? null,
            $payload['proxy'] ?? $payload['proxy_address'] ?? null,
            $payload,
            $sessionChanged,
        );
    }
}
