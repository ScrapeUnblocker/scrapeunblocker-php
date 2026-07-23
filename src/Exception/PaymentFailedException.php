<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * A card payment has been declined three times (HTTP 402).
 *
 * Those attempts are the payment provider's automatic retries spread over several days,
 * so a card has been failing for a while. Subscribing to a new plan does NOT clear this:
 * the old unpaid invoice stays open, and the block stays until that specific invoice is paid.
 */
class PaymentFailedException extends PaymentRequiredException
{
}
