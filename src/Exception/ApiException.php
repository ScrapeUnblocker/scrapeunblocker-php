<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/** An error response returned by the ScrapeUnblocker API. */
class ApiException extends ScrapeUnblockerException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $body = null,
    ) {
        parent::__construct($message, $statusCode);
    }
}
