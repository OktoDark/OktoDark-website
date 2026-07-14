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

use App\Entity\Episode;
use App\Entity\MediaMetadata;
use App\Entity\Season;
use App\Entity\TV;
use App\Enum\MediaType;
use Doctrine\ORM\EntityManagerInterface;

class SeasonEpisodeBuilder
{
    public function __construct(
        private readonly TvMazeService $maze,
        private readonly MetadataHydrator $hydrator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Build seasons + episodes for a TV show using TVMaze (hydrated metadata).
     */
    public function buildForShow(TV $tv): void
    {
        $parentMeta = $tv->getMediaMetadata();
        $mazeId = (int) $parentMeta->getExternalId();

        if (!$mazeId) {
            return;
        }

        // Fetch hydrated metadata from TvMazeService
        $seasons = $this->maze->getSeasons($mazeId);     // hydrated season metadata
        $episodes = $this->maze->getEpisodes($mazeId);   // hydrated episode metadata

        // Group episodes by seasonNumber
        $episodesBySeason = [];
        foreach ($episodes as $epMeta) {
            $seasonNum = $epMeta['seasonNumber'];
            $episodesBySeason[$seasonNum][] = $epMeta;
        }

        foreach ($seasons as $seasonMetaData) {
            $seasonNumber = $seasonMetaData['seasonNumber'];

            // Skip specials (season 0)
            if (0 === $seasonNumber) {
                continue;
            }

            // Find or create season metadata
            $seasonMeta = $this->findOrCreateMetadata(
                $parentMeta,
                $seasonNumber,
                null,
                MediaType::SEASON
            );

            // Apply hydrated metadata
            $seasonMeta->applyImportedMetadata($seasonMetaData);
            $this->hydrator->hydrate($seasonMeta);

            // Find or create Season entity
            $season = $this->em->getRepository(Season::class)->findOneBy([
                'relatedTv' => $tv,
                'mediaMetadata' => $seasonMeta,
            ]);

            if (!$season) {
                $season = new Season();
                $season->setRelatedTv($tv);
                $season->setMediaMetadata($seasonMeta);
                $this->em->persist($season);
            }

            // Build episodes for this season
            foreach ($episodesBySeason[$seasonNumber] ?? [] as $epMetaData) {
                $episodeNumber = $epMetaData['episodeNumber'];

                // Find or create episode metadata
                $episodeMeta = $this->findOrCreateMetadata(
                    $parentMeta,
                    $seasonNumber,
                    $episodeNumber,
                    MediaType::EPISODE
                );

                // Apply hydrated metadata
                $episodeMeta->applyImportedMetadata($epMetaData);
                $this->hydrator->hydrate($episodeMeta);

                // Find or create Episode entity
                $episode = $this->em->getRepository(Episode::class)->findOneBy([
                    'relatedSeason' => $season,
                    'mediaMetadata' => $episodeMeta,
                ]);

                if (!$episode) {
                    $episode = new Episode();
                    $episode->setRelatedSeason($season);
                    $episode->setMediaMetadata($episodeMeta);
                    $this->em->persist($episode);
                }
            }
        }

        $this->em->flush();
    }

    /**
     * Create or reuse MediaMetadata for seasons/episodes.
     */
    private function findOrCreateMetadata(
        MediaMetadata $parent,
        int $season,
        ?int $episode,
        MediaType $type,
    ): MediaMetadata {
        $repo = $this->em->getRepository(MediaMetadata::class);

        $meta = $repo->findOneBy([
            'mediaId' => $parent->getMediaId(),
            'mediaType' => $type,
            'seasonNumber' => $season,
            'episodeNumber' => $episode,
        ]);

        if ($meta) {
            return $meta;
        }

        $meta = new MediaMetadata();
        $meta->setMediaId($parent->getMediaId());
        $meta->setSource($parent->getSource());
        $meta->setMediaType($type);
        $meta->setSeasonNumber($season);
        $meta->setEpisodeNumber($episode);

        $this->em->persist($meta);

        return $meta;
    }
}
