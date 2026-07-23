<?php

declare(strict_types=1);

namespace ScrapeUnblocker\Exception;

/**
 * The browser run did not finish in time on our side (HTTP 408).
 *
 * Distinct from TimeoutException, which is this client giving up locally. Here the API
 * answered - it just could not render the page in the time allowed. Retrying usually helps.
 */
class BrowserTimeoutException extends ApiException
{
}
