<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * The key is valid but the account has no active plan (HTTP 401).
 *
 * Thrown when the API answers a 401 with "No valid subscription". Pick a plan at
 * https://app.scrapeunblocker.com - access resumes within about a minute, and the
 * key does not change.
 */
class NoSubscriptionException extends AuthenticationException
{
}
