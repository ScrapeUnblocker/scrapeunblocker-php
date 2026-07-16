<?php

declare(strict_types=1);

namespace ScrapeUnblocker;

/** Skyscanner plugin endpoints (flights, hotels, car hire). */
final class Skyscanner
{
    /** @internal */
    public function __construct(private readonly Client $client)
    {
    }

    public function flightLocations(string $q, array $params = []): array
    {
        return $this->client->postJson('/flights/skyscanner-locations', ['q' => $q] + $params);
    }

    public function flights(array $params = []): array
    {
        return $this->client->postJson('/flights/skyscanner-quotes', $params);
    }

    public function hotelLocations(string $q, array $params = []): array
    {
        return $this->client->postJson('/hotels/skyscanner-locations', ['q' => $q] + $params);
    }

    public function hotels(array $params = []): array
    {
        return $this->client->postJson('/hotels/skyscanner-quotes', $params);
    }

    public function carhireLocations(string $q, array $params = []): array
    {
        return $this->client->postJson('/carhire/skyscanner-locations', ['q' => $q] + $params);
    }

    public function carhire(array $params = []): array
    {
        return $this->client->postJson('/carhire/skyscanner-quotes', $params);
    }
}
