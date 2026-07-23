<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * The account has a billing problem (HTTP 402).
 *
 * Credentials are fine - the request was stopped for a billing reason. There are three,
 * each thrown as a dedicated subclass: QuotaExceededException, CreditLimitExceededException
 * and PaymentFailedException. Catch this base class to handle all three.
 *
 * When more than one applies, the most serious wins: failed payment outranks credit limit,
 * which outranks quota. All three lift by themselves once the billing state changes - access
 * returns within roughly a minute, with no key change needed. Like a 401, a 402 is refused
 * before anything is scraped, so it is never billed. Retrying is pointless; fix the billing
 * state first.
 */
class PaymentRequiredException extends ApiException
{
}
