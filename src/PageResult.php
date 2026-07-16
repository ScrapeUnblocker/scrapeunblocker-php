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
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['html'] ?? $payload['page_source'] ?? $payload['content'] ?? null,
            $payload['cookies'] ?? null,
            $payload['proxy'] ?? $payload['proxy_address'] ?? null,
            $payload,
        );
    }
}
