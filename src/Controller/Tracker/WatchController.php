<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Tracker;

use App\Entity\User;
use App\Repository\EpisodeRepository;
use App\Repository\TVRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WatchController extends AbstractController
{
    #[Route('/tracker/watch/continue', name: 'app_tracker_continue')]
    public function continueWatching(
        TVRepository $tvRepo,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $shows = $tvRepo->findContinueWatching($user);

        return $this->json(['continue' => $shows]);
    }

    #[Route('/tracker/watch/next', name: 'app_tracker_next')]
    public function nextEpisode(
        EpisodeRepository $episodeRepo,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Updated repository method name
        $next = $episodeRepo->findNextEpisodes($user);

        return $this->json(['next' => $next]);
    }

    #[Route('/tracker/watch/recent', name: 'app_tracker_recent')]
    public function recent(
        EpisodeRepository $episodeRepo,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Updated repository method name
        $recent = $episodeRepo->findRecentlyWatched($user);

        return $this->json(['recent' => $recent]);
    }
}
