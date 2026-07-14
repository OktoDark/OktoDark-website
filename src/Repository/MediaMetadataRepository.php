<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Repository;

use App\Entity\MediaMetadata;
use App\Enum\MediaType;
use App\Enum\Source;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MediaMetadata>
 */
class MediaMetadataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MediaMetadata::class);
    }

    /**
     * Finds or creates a global item wrapper based on unique constraint criteria.
     */
    public function getOrCreateItem(
        string $mediaId,
        Source $source,
        MediaType $mediaType,
        string $title,
        ?string $image = null,
        ?int $seasonNumber = null,
        ?int $episodeNumber = null,
    ): MediaMetadata {
        $em = $this->getEntityManager();

        $item = $this->findOneBy([
            'mediaId' => $mediaId,
            'source' => $source,
            'mediaType' => $mediaType,
            'seasonNumber' => $seasonNumber,
            'episodeNumber' => $episodeNumber,
        ]);

        if (!$item) {
            $item = new MediaMetadata();
            $item->setMediaId($mediaId)
                ->setSource($source)
                ->setMediaType($mediaType)
                ->setTitle($title)
                ->setImage($image)
                ->setSeasonNumber($seasonNumber)
                ->setEpisodeNumber($episodeNumber);

            $em->persist($item);
            // ❗ DO NOT FLUSH HERE
        }

        return $item;
    }
}
