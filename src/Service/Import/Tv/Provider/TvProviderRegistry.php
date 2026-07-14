<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Tv\Provider;

class TvProviderRegistry
{
    public function __construct(
        private iterable $providers,
    ) {
    }

    public function resolve(array $record): TvImportProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($record)) {
                return $provider;
            }
        }

        return new GenericTvProvider();
    }
}
