<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * Every request the plan allows this period has been used (HTTP 402).
 *
 * On plans that permit overages this only fires past the quota *plus* the overage
 * allowance; inside that band requests still succeed and the extra usage is invoiced.
 * Active coupon credit is spent before plan quota. The counter resets on the
 * subscription's anniversary day, not the first of the month.
 */
class QuotaExceededException extends PaymentRequiredException
{
}
