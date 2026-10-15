<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * The URL serves a content type the call cannot return (HTTP 415).
 *
 * getPageSource() throws it for something other than HTML (for images, use
 * getImage()); getImage() throws it for something other than an image, such as
 * a page URL. The message names the content type that was found.
 */
class UnsupportedContentException extends ApiException
{
}
