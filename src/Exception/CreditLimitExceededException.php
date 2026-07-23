<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * The unpaid balance has passed the account's credit limit (HTTP 402).
 *
 * The balance counted here is the amount remaining on open invoices plus metered usage
 * already consumed but not yet invoiced. Outstanding invoices are charged automatically
 * when this triggers, so with a working card it usually clears itself within about a minute.
 */
class CreditLimitExceededException extends PaymentRequiredException
{
}
