<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * The page rendered but no structured data came out of it (HTTP 422).
 *
 * Thrown by getParsed() when the API loaded the page but could not extract any
 * structured fields from it. The API answers 422 with a JSON body of
 * {"error": "no_data_extracted", "detail": ...}; $detail holds the API's
 * explanation. The call is not billed and retrying returns the same answer;
 * call getPageSource() for the HTML.
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
