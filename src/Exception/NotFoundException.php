<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * Something the call asked for does not exist (HTTP 404).
 *
 * Plugin methods throw it when the item they look up does not exist. When the
 * target page itself answered 404 or 410, the more specific
 * TargetNotFoundException subclass is thrown instead.
 */
class NotFoundException extends ApiException
{
}
