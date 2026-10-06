<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * A 422 {"error": "no_data_extracted", "detail": ...} answer.
 *
 * @deprecated The API no longer sends this 422: a page with no structured data
 * now comes back from getParsed() as a ParsedPage with ->dataExtracted false and
 * the rendered page on ->html. Kept so existing catch blocks still resolve.
 */
class NoDataExtractedException extends ValidationException
{
    public function __construct(
        string $message,
        int $statusCode,
        ?string $body = null,
        public readonly ?string $detail = null,
    ) {
        parent::__construct($message, $statusCode, $body);
    }
}
