<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * The account reached the monthly budget limit you set (HTTP 402).
 *
 * Thrown when the API answers a 402 with `User set budget exceeded`. The limit is set in
 * your profile (EUR, excluding VAT) and spend is counted the way the invoice is: the plan's
 * fixed monthly fee, if any, plus the requests billed on top of it. Requests paid from
 * coupon credit do not count. The key works again at the start of the next billing period,
 * or within about a minute after you raise or remove the limit at
 * https://app.scrapeunblocker.com/dashboard/profile.
 */
class BudgetExceededException extends PaymentRequiredException
{
}
