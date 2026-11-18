<?php

declare(strict_types=1);

namespace ScrapeUnblocker;

/**
 * One XHR/fetch response the page received (getPageWithXhr()).
 *
 * $status is 0 when the request failed without a response; $body is null when
 * it was not returned, with $bodyOmitted saying why ('binary', 'size_limit',
 * 'in_flight', 'redirect', 'unavailable' or 'request_failed').
 */
final class XhrCall
{
    public function __construct(
        public readonly string $url,
        public readonly string $method,
        public readonly int $status,
        public readonly ?string $contentType = null,
        public readonly ?string $body = null,
        public readonly ?string $bodyOmitted = null,
    ) {
    }

    public static function fromArray(array $item): self
    {
        return new self(
            (string) ($item['url'] ?? ''),
            (string) ($item['method'] ?? 'GET'),
            (int) ($item['status'] ?? 0),
            isset($item['content_type']) ? (string) $item['content_type'] : null,
            isset($item['body']) ? (string) $item['body'] : null,
            isset($item['body_omitted']) ? (string) $item['body_omitted'] : null,
        );
    }

    /**
     * The body decoded as JSON.
     *
     * @throws \JsonException when the body is missing or not JSON
     */
    public function json(): mixed
    {
        if ($this->body === null) {
            throw new \JsonException('no body (' . ($this->bodyOmitted ?? 'unavailable') . ')');
        }

        return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
    }
}
