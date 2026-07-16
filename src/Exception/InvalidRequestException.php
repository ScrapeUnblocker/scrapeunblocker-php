<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/** The request was rejected as invalid, e.g. a malformed URL (HTTP 400). */
class InvalidRequestException extends ApiException
{
}
