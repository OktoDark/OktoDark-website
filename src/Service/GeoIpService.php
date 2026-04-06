<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service;

use GeoIp2\Database\Reader;

class GeoIpService
{
    private Reader $countryReader;
    private Reader $cityReader;

    private array $cache = [];

    public function __construct(string $countryDbPath, string $cityDbPath)
    {
        $this->countryReader = new Reader($countryDbPath);
        $this->cityReader = new Reader($cityDbPath);
    }

    private function initCache(string $ip): void
    {
        if (!isset($this->cache[$ip])) {
            $this->cache[$ip] = [
                'country' => null,
                'city' => null,
                'continent' => null,
            ];
        }
    }

    public function getCountryCode(?string $ip): ?string
    {
        if (!$ip) {
            return null;
        }

        $this->initCache($ip);

        if (null !== $this->cache[$ip]['country']) {
            return $this->cache[$ip]['country'];
        }

        try {
            $record = $this->countryReader->country($ip);

            return $this->cache[$ip]['country'] = $record->country->isoCode;
        } catch (\Exception $e) {
            return $this->cache[$ip]['country'] = null;
        }
    }

    public function getCity(?string $ip): ?string
    {
        if (!$ip) {
            return null;
        }

        $this->initCache($ip);

        if (null !== $this->cache[$ip]['city']) {
            return $this->cache[$ip]['city'];
        }

        try {
            $record = $this->cityReader->city($ip);

            return $this->cache[$ip]['city'] = $record->city->name;
        } catch (\Exception $e) {
            return $this->cache[$ip]['city'] = null;
        }
    }

    public function getContinent(?string $ip): ?string
    {
        if (!$ip) {
            return null;
        }

        $this->initCache($ip);

        if (null !== $this->cache[$ip]['continent']) {
            return $this->cache[$ip]['continent'];
        }

        try {
            $record = $this->countryReader->country($ip);

            return $this->cache[$ip]['continent'] = $record->continent->code;
        } catch (\Exception $e) {
            return $this->cache[$ip]['continent'] = null;
        }
    }
}
