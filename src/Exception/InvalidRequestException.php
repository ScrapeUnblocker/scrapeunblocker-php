<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * The request was rejected as invalid (HTTP 400).
 *
 * Thrown for a malformed URL or unsupported scheme, for a missing x-scrapeunblocker-key
 * header ("Missing x-scrapeunblocker-key"), and for a URL that belongs to a dedicated
 * plugin - the response names the endpoint to use instead.
 */
class InvalidRequestException extends ApiException
{
}
