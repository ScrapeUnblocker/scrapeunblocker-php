<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * The API key was rejected (HTTP 401).
 *
 * Two cases produce a 401: an unrecognised key ("Unauthorized" - a typo, trailing
 * whitespace, an empty value, or a key rotated in the dashboard), and a valid key on an
 * account with no plan, which throws the NoSubscriptionException subclass.
 *
 * Omitting the API key header entirely is a 400, not a 401. Nothing is scraped for a 401,
 * so it is not billed and does not count against your quota.
 */
class AuthenticationException extends ApiException
{
}
