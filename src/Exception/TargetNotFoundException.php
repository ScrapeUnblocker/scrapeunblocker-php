<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * The target page itself does not exist (HTTP 404 or 410).
 *
 * Thrown by getPageSource(), getParsed() and getPageWithCookies() when the site
 * you asked for answered 404 or 410 on its own. The API passes that status
 * through and marks it with the X-Origin-Status header, which is how this is
 * told apart from an API-side 404. It is the target's final answer, not a
 * block, so it is never retried - and the call is billed, because the page was
 * fetched and delivered.
 *
 * $originStatus is the status the target answered with (404 or 410), $html the
 * target's own not-found page as served (can be empty; null when the body is a
 * parsed-data JSON payload), and $destinationUrl the URL the target answered
 * for, when the API sent X-Destination-URL.
 */
class TargetNotFoundException extends NotFoundException
{
    public function __construct(
        string $message,
        int $statusCode,
        ?string $body,
        public readonly int $originStatus,
        public readonly ?string $html = null,
        public readonly ?string $destinationUrl = null,
    ) {
        parent::__construct($message, $statusCode, $body);
    }
}
