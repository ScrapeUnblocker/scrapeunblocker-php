<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/** The API key is missing, malformed, or not recognised (HTTP 401). */
class AuthenticationException extends ApiException
{
}
