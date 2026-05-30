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

use App\Entity\User;
use App\Repository\AccountActivityRepository;

class FailedLoginAttemptService
{
    private const FAILED_ATTEMPT_THRESHOLD = 5; // e.g., 5 failed attempts
    private const FAILED_ATTEMPT_TIMEFRAME = '-15 minutes'; // e.g., within the last 15 minutes

    public function __construct(
        private AccountActivityRepository $activityRepository,
        private LoginAlertService $loginAlertService,
        private GeoIpService $geoIpService,
    ) {
    }

    public function checkAndAlert(User $user, string $ip, string $userAgent): void
    {
        // Get recent failed login attempts for this user
        $failedAttempts = $this->activityRepository->findRecentFailedLoginAttempts(
            $user,
            self::FAILED_ATTEMPT_TIMEFRAME
        );

        if (\count($failedAttempts) >= self::FAILED_ATTEMPT_THRESHOLD) {
            // Send alert for multiple failed attempts
            $location = $this->geoIpService->getCountryCode($ip);
            $this->loginAlertService->sendFailedLoginAlert($user, $ip, $userAgent, $location, \count($failedAttempts));
        }
    }
}
