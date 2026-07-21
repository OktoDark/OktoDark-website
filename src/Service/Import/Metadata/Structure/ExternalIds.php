<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Metadata\Structure;

/**
 * Normalized external identifier set shared by every TV metadata provider.
 *
 * Every provider maps its native response into this shape so the discovery,
 * scoring and merge engines can treat TMDB / TVDB / TVMaze identically.
 */
final class ExternalIds
{
    public function __construct(
        public readonly ?string $tmdb = null,
        public readonly ?string $tvdb = null,
        public readonly ?string $tvmaze = null,
    ) {
    }

    /**
     * @return array{tmdb?:string, tvdb?:string, tvmaze?:string}
     */
    public function toArray(): array
    {
        $ids = [];
        if (null !== $this->tmdb) {
            $ids['tmdb'] = $this->tmdb;
        }
        if (null !== $this->tvdb) {
            $ids['tvdb'] = $this->tvdb;
        }
        if (null !== $this->tvmaze) {
            $ids['tvmaze'] = $this->tvmaze;
        }

        return $ids;
    }

    public function merge(self $other): self
    {
        return new self(
            $this->tmdb ?? $other->tmdb,
            $this->tvdb ?? $other->tvdb,
            $this->tvmaze ?? $other->tvmaze,
        );
    }
}
