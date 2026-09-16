<?php

declare(strict_types=1);

namespace ScrapeUnblocker;

/** Southwest Airlines plugin endpoints (flights). */
final class Southwest
{
    /** @internal */
    public function __construct(private readonly Client $client)
    {
    }

    public function flights(array $params = []): array
    {
        return $this->client->postJson('/flights/southwest-quotes', $params);
    }
}
