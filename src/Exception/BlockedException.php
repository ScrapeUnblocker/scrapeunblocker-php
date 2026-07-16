<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/** The target site blocked every available bypass path (HTTP 403). Blocked calls are not billed. */
class BlockedException extends ApiException
{
}
