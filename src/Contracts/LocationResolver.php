<?php

namespace MltStephane\LaravelAnalytics\Contracts;

/**
 * Resolves the geographic location of an IP address.
 *
 * Return null when the location cannot be determined, or an array shaped like:
 * ['country' => 'FR', 'region' => 'Île-de-France', 'city' => 'Paris']
 */
interface LocationResolver
{
    public function resolve(string $ip): ?array;
}
