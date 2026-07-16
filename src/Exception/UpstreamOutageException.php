<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/** The origin site returned a server-side outage page (HTTP 503). */
class UpstreamOutageException extends ApiException
{
}
