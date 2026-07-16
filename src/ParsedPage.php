<?php

declare(strict_types=1);

namespace ScrapeUnblocker;

/** Structured data extracted from a page (getParsed()). */
final class ParsedPage
{
    public function __construct(
        /** What the API classified the page as, e.g. "product". */
        public readonly ?string $pageType,
        /** How the data was extracted (Schema.org, __NEXT_DATA__, AI rules). */
        public readonly ?string $source,
        /** The extracted fields. */
        public readonly mixed $data,
        /** The full JSON payload as returned by the API. */
        public readonly array $raw,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        $inner = $payload['data'] ?? $payload;
        if (!is_array($inner)) {
            $inner = [];
        }

        return new self(
            $inner['page_type'] ?? null,
            $inner['source'] ?? null,
            $inner['data'] ?? null,
            $payload,
        );
    }
}
