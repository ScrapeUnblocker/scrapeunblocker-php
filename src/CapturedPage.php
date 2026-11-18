<?php

declare(strict_types=1);

namespace ScrapeUnblocker;

/** A page plus the XHR/fetch responses it received (getPageWithXhr()). */
final class CapturedPage
{
    /**
     * @param list<XhrCall> $xhr the matching responses in the order the page received them
     * @param bool $xhrTruncated true when more responses matched than were returned
     */
    public function __construct(
        public readonly ?string $html,
        public readonly array $xhr,
        public readonly bool $xhrTruncated,
        public readonly ?string $proxy,
        public readonly array $raw,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        $calls = [];
        foreach (($payload['xhr'] ?? []) as $item) {
            if (is_array($item)) {
                $calls[] = XhrCall::fromArray($item);
            }
        }

        return new self(
            isset($payload['html']) ? (string) $payload['html'] : null,
            $calls,
            ($payload['xhr_truncated'] ?? false) === true,
            isset($payload['proxy']) ? (string) $payload['proxy'] : null,
            $payload,
        );
    }
}
