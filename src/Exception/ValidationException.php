<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * A request parameter is missing or has the wrong type (HTTP 422).
 *
 * Unlike the other errors the body is JSON, with a "detail" array pinpointing each
 * problem field. Read it from the $body property.
 */
class ValidationException extends ApiException
{
}
