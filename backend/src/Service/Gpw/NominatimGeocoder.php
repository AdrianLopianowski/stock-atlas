<?php

declare(strict_types=1);

namespace App\Service\Gpw;

use Symfony\Contracts\HttpClient\HttpClientInterface;


final class NominatimGeocoder
{
    private const URL = 'https://nominatim.openstreetmap.org/search';

    private float $lastRequestAt = 0.0;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $userAgent = 'gpw-importer/1.0 (kontakt@twojadomena.pl)',
    ) {}

    /**
     * @return array{0:float,1:float}|null [latitude, longitude]
     */
    public function geocode(?string $address, ?string $city, string $country = 'Polska'): ?array
    {
        $query = trim(implode(', ', array_filter([$address, $city, $country])));
        if ('' === $query || (null === $address && null === $city)) {
            return null;
        }

        $this->throttle();

        try {
            $data = $this->http->request('GET', self::URL, [
                'query' => [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'addressdetails' => 0,
                ],
                'headers' => ['User-Agent' => $this->userAgent],
                'timeout' => 15,
            ])->toArray(false);
        } catch (\Throwable) {
            return null;
        }

        if (!isset($data[0]['lat'], $data[0]['lon'])) {
            // druga próba – samo miasto
            if (null !== $city && null !== $address) {
                return $this->geocode(null, $city, $country);
            }

            return null;
        }

        return [(float) $data[0]['lat'], (float) $data[0]['lon']];
    }

    private function throttle(): void
    {
        $elapsed = microtime(true) - $this->lastRequestAt;
        if ($elapsed < 1.1) {
            usleep((int) ((1.1 - $elapsed) * 1_000_000));
        }
        $this->lastRequestAt = microtime(true);
    }
}
