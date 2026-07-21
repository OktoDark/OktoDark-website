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

use App\Domain\SeasonLifecycleManager;
use App\Domain\TvLifecycleManager;
use App\Entity\Episode;
use App\Entity\User;
use App\Entity\UserMessage;
use App\Enum\UserMessageLevel;
use App\Enum\WatchStatus;
use Doctrine\ORM\EntityManagerInterface;

class MediaService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SeasonLifecycleManager $seasonLifecycle,
        private readonly TvLifecycleManager $tvLifecycle,
    ) {
    }

    /**
     * Toggle an episode's watched status and propagate updates.
     */
    public function toggleEpisodeWatchStatus(Episode $episode, User $user, bool $isWatched): void
    {
        $season = $episode->getRelatedSeason();
        if (!$season) {
            return;
        }

        $tv = $season->getRelatedTv();

        // ---------------------------------------------------------
        // Update episode completion state
        // ---------------------------------------------------------
        if ($isWatched) {
            $episode->setEndDate(new \DateTimeImmutable());
            $episode->setStatus(WatchStatus::COMPLETED);
            $episode->setUpdatedAt(new \DateTimeImmutable());

            if (!$episode->getStartDate()) {
                $episode->setStartDate(new \DateTimeImmutable());
            }
        } else {
            $episode->setEndDate(null);
            $episode->setStatus(WatchStatus::IN_PROGRESS);
            $episode->setUpdatedAt(new \DateTimeImmutable());
        }

        // ---------------------------------------------------------
        // Delegate season progress + status to lifecycle manager
        // ---------------------------------------------------------
        $this->seasonLifecycle->updateSeasonAndTv($season);

        // ---------------------------------------------------------
        // Update timestamps for sorting
        // ---------------------------------------------------------
        $season->setProgressedAt(new \DateTimeImmutable());

        if ($tv) {
            $tv->setProgressedAt(new \DateTimeImmutable());
        }

        // ---------------------------------------------------------
        // Queue completion message if season is finished
        // ---------------------------------------------------------
        if (WatchStatus::COMPLETED === $season->getStatus()) {
            $this->queueUserMessage(
                $user,
                UserMessageLevel::SUCCESS,
                \sprintf(
                    'Congratulations! You finished watching %s!',
                    $season->getMediaMetadata()?->getTitle() ?? 'a season'
                )
            );
        }

        $this->em->flush();
    }

    /**
     * Queue a UI message for the user.
     */
    public function queueUserMessage(User $user, UserMessageLevel $level, string $text): void
    {
        $message = new UserMessage();
        $message->setUser($user)
            ->setLevel($level)
            ->setMessage($text);

        $this->em->persist($message);
    }
}
