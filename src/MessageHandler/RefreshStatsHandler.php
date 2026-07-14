<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\MessageHandler;

use App\Message\RefreshStatsMessage;
use App\Repository\UserRepository;
use App\Service\StatsService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RefreshStatsHandler
{
    public function __construct(
        private UserRepository $userRepo,
        private StatsService $statsService,
    ) {
    }

    public function __invoke(RefreshStatsMessage $message): void
    {
        $user = $this->userRepo->find($message->userId);
        if (!$user) {
            return;
        }

        $this->statsService->refreshStats($user);
    }
}
