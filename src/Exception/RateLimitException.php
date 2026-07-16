<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/** Too many requests against your account in a short window (HTTP 429). */
class RateLimitException extends ApiException
{
}
