<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Entity;

use App\Repository\TVRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TVRepository::class)]
#[ORM\Table(name: 'tracking_tv')]
#[ORM\UniqueConstraint(name: 'unique_tv_item_user', columns: ['user_id', 'media_metadata_id'])]
class TV extends AbstractMedia
{
    /** @var Collection<int, Season> */
    #[ORM\OneToMany(targetEntity: Season::class, mappedBy: 'relatedTv', cascade: ['remove'])]
    private Collection $seasons;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        parent::__construct();
        $this->seasons = new ArrayCollection();
    }

    public function setUser(?User $user): self
    {
        parent::setUser($user);

        return $this;
    }

    /**
     * @return Collection<int, Season>
     */
    public function getSeasons(): Collection
    {
        return $this->seasons;
    }

    // ─────────────────────────────────────────────
    // PASSTHROUGH HELPERS FOR METADATA FIELDS
    // ─────────────────────────────────────────────

    public function getSeasonNumber(): ?int
    {
        return $this->getMediaMetadata()?->getSeasonNumber();
    }

    public function getEpisodeNumber(): ?int
    {
        return $this->getMediaMetadata()?->getEpisodeNumber();
    }

    // ─────────────────────────────────────────────
    // PROGRESS CALCULATION
    // ─────────────────────────────────────────────

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function getWatchedEpisodes(): int
    {
        $watched = 0;

        foreach ($this->seasons as $season) {
            foreach ($season->getEpisodes() as $ep) {
                if ($ep->isWatched()) {
                    ++$watched;
                }
            }
        }

        return $watched;
    }

    public function getTotalEpisodes(): int
    {
        $total = 0;

        foreach ($this->seasons as $season) {
            if (method_exists($season, 'getTotalEpisodes')) {
                $total += $season->getTotalEpisodes();
            }
        }

        return $total;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    // ─────────────────────────────────────────────
    // NEXT EPISODE LOGIC (FULLY FIXED)
    // ─────────────────────────────────────────────

    /**
     * Returns the episode to watch next, advancing from the last watched episode.
     *
     * If episodes have been watched, the method locates the most recently watched
     * episode by endDate and returns the first unwatched episode that follows it
     * within the same season, or the first unwatched episode of the next season.
     * If no episodes have been watched yet, it falls back to the first unwatched
     * episode across all seasons.
     */
    public function getNextEpisode(): array
    {
        $seasons = $this->seasons->toArray();

        if (empty($seasons)) {
            return [];
        }

        usort($seasons, static fn ($a, $b) => ($a->getMediaMetadata()?->getSeasonNumber() ?? 0)
            <=>
            ($b->getMediaMetadata()?->getSeasonNumber() ?? 0)
        );

        $lastWatched = null;
        $lastWatchedDate = null;

        foreach ($seasons as $season) {
            foreach ($season->getEpisodes() as $ep) {
                $endDate = $ep->getEndDate();
                if ($endDate && (null === $lastWatchedDate || $endDate > $lastWatchedDate)) {
                    $lastWatchedDate = $endDate;
                    $lastWatched = $ep;
                }
            }
        }

        if ($lastWatched) {
            $lastWatchedSeason = $lastWatched->getRelatedSeason();
            $lastWatchedNumber = $lastWatched->getMediaMetadata()?->getEpisodeNumber();

            foreach ($seasons as $season) {
                if ($season->getId() === $lastWatchedSeason->getId()) {
                    $episodes = $season->getEpisodes()->toArray();

                    usort($episodes, static fn ($a, $b) => ($a->getMediaMetadata()?->getEpisodeNumber() ?? 0)
                        <=>
                        ($b->getMediaMetadata()?->getEpisodeNumber() ?? 0)
                    );

                    foreach ($episodes as $ep) {
                        $epNumber = $ep->getMediaMetadata()?->getEpisodeNumber();
                        if ($epNumber > $lastWatchedNumber && null === $ep->getEndDate()) {
                            return [
                                'season' => $season->getMediaMetadata()?->getSeasonNumber(),
                                'episode' => $epNumber,
                            ];
                        }
                    }
                    break;
                }
            }

            $foundLastSeason = false;

            foreach ($seasons as $season) {
                if ($foundLastSeason) {
                    $episodes = $season->getEpisodes()->toArray();

                    if (!empty($episodes)) {
                        usort($episodes, static fn ($a, $b) => ($a->getMediaMetadata()?->getEpisodeNumber() ?? 0)
                            <=>
                            ($b->getMediaMetadata()?->getEpisodeNumber() ?? 0)
                        );

                        foreach ($episodes as $ep) {
                            if (null === $ep->getEndDate()) {
                                return [
                                    'season' => $season->getMediaMetadata()?->getSeasonNumber(),
                                    'episode' => $ep->getMediaMetadata()?->getEpisodeNumber(),
                                ];
                            }
                        }
                    }
                }

                if ($season->getId() === $lastWatchedSeason->getId()) {
                    $foundLastSeason = true;
                }
            }
        }

        foreach ($seasons as $season) {
            $episodes = $season->getEpisodes()->toArray();

            if (empty($episodes)) {
                continue;
            }

            usort($episodes, static fn ($a, $b) => ($a->getMediaMetadata()?->getEpisodeNumber() ?? 0)
                <=>
                ($b->getMediaMetadata()?->getEpisodeNumber() ?? 0)
            );

            foreach ($episodes as $ep) {
                if (null === $ep->getEndDate()) {
                    return [
                        'season' => $season->getMediaMetadata()?->getSeasonNumber(),
                        'episode' => $ep->getMediaMetadata()?->getEpisodeNumber(),
                    ];
                }
            }
        }

        return [];
    }
}
