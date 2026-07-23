<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * The URL serves something other than HTML (HTTP 415).
 *
 * The message names the content type that was found. For images, use getImage()
 * instead of getPageSource().
 */
class UnsupportedContentException extends ApiException
{
}
