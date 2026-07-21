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
 * Normalized cast member (used for show-level and episode-level guest stars).
 */
final class CastFull
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $character = null,
        public readonly ?string $image = null,
    ) {
    }

    /**
     * @return array<int, CastFull>
     */
    public static function fromArray(array $cast): array
    {
        return array_map(
            static fn (array $c): CastFull => new self(
                $c['name'] ?? null,
                $c['character'] ?? null,
                $c['image'] ?? null,
            ),
            $cast,
        );
    }
}
