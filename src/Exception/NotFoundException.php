<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * The page loaded but the requested element was absent (HTTP 404).
 *
 * Only getImage() throws this: the page rendered fine and contained no <img> tag.
 */
class NotFoundException extends ApiException
{
}
