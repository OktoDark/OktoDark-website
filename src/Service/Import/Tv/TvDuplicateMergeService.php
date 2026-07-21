<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Tv;

use App\Entity\Episode;
use App\Entity\MediaMetadata;
use App\Entity\Season;
use App\Entity\TV;
use Doctrine\ORM\EntityManagerInterface;

class TvDuplicateMergeService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function merge(bool $dryRun = true): int
    {
        $repo = $this->em->getRepository(MediaMetadata::class);
        $allTv = $repo->findBy(['mediaType' => 'TV']);

        $byTmdb = [];
        $noTmdb = [];

        foreach ($allTv as $meta) {
            $tmdbId = $meta->getTmdbId();

            if ($tmdbId) {
                $byTmdb[$tmdbId][] = $meta;
            } else {
                $noTmdb[] = $meta;
            }
        }

        $merged = 0;

        foreach ($byTmdb as $items) {
            if (\count($items) <= 1) {
                continue;
            }

            $canonical = $items[0];

            foreach ($items as $meta) {
                if ($meta === $canonical) {
                    continue;
                }

                $this->mergeTvEntities($meta, $canonical, $dryRun);
                $this->mergeSeasonMetadata($meta, $canonical);
                $this->mergeEpisodeMetadata($meta, $canonical);

                if (!$dryRun) {
                    $this->em->remove($meta);
                }

                ++$merged;
            }
        }

        if (!empty($noTmdb)) {
            $groups = [];

            foreach ($noTmdb as $meta) {
                $norm = $this->normalizeTitle($meta->getTitle());
                $groups[$norm][] = $meta;
            }

            foreach ($groups as $items) {
                if (\count($items) <= 1) {
                    continue;
                }

                $canonical = $items[0];

                foreach ($items as $meta) {
                    if ($meta === $canonical) {
                        continue;
                    }

                    $this->mergeTvEntities($meta, $canonical, $dryRun);
                    $this->mergeSeasonMetadata($meta, $canonical);
                    $this->mergeEpisodeMetadata($meta, $canonical);

                    if (!$dryRun) {
                        $this->em->remove($meta);
                    }

                    ++$merged;
                }
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        return $merged;
    }

    private function mergeTvEntities(MediaMetadata $duplicate, MediaMetadata $canonical, bool $dryRun): void
    {
        $tvs = $this->em->getRepository(TV::class)
            ->findBy(['mediaMetadata' => $duplicate]);

        foreach ($tvs as $tv) {
            $canonicalTv = $this->em->getRepository(TV::class)
                ->findOneBy([
                    'mediaMetadata' => $canonical,
                    'user' => $tv->getUser(),
                ]);

            if (!$canonicalTv) {
                $tv->setMediaMetadata($canonical);
            } else {
                foreach ($tv->getSeasons() as $season) {
                    $season->setRelatedTv($canonicalTv);
                }

                if (!$dryRun) {
                    $this->em->remove($tv);
                }
            }
        }
    }

    private function mergeSeasonMetadata(MediaMetadata $duplicate, MediaMetadata $canonical): void
    {
        $seasons = $this->em->getRepository(Season::class)
            ->findBy(['mediaMetadata' => $duplicate]);

        foreach ($seasons as $season) {
            $season->setMediaMetadata($canonical);
        }
    }

    private function mergeEpisodeMetadata(MediaMetadata $duplicate, MediaMetadata $canonical): void
    {
        $episodes = $this->em->getRepository(Episode::class)
            ->findBy(['mediaMetadata' => $duplicate]);

        foreach ($episodes as $episode) {
            $episode->setMediaMetadata($canonical);
        }
    }

    private function normalizeTitle(string $title): string
    {
        $title = mb_trim($title);
        $title = preg_replace('/\s+/', ' ', $title);
        $title = str_replace(["\xC2\xA0"], ' ', $title);
        $title = preg_replace('/\s*\(.*?\)\s*/', '', $title);

        return mb_strtolower($title);
    }
}
