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

use App\Entity\TrustedDevice;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class TrustedDeviceService
{
    public function __construct(
        private EntityManagerInterface $em,
        private DeviceParserService $deviceParser,
    ) {
    }

    public function hasTrustedDevice(User $user, Request $request): bool
    {
        $ua = $request->headers->get('User-Agent') ?? 'unknown';
        $fingerprint = hash('sha256', $ua);

        $repo = $this->em->getRepository(TrustedDevice::class);

        $device = $repo->findOneBy([
            'user' => $user,
            'fingerprint' => $fingerprint,
        ]);

        if (null !== $device && !$device->isExpired()) {
            $device->setLastUsedAt(new \DateTime());
            $this->em->flush();

            return true;
        }

        return false;
    }

    public function addTrustedDevice(User $user, Request $request): void
    {
        $ua = $request->headers->get('User-Agent') ?? 'Unknown device';
        $parsed = $this->deviceParser->parse($ua);

        // FIXED: same fingerprint logic everywhere
        $fingerprint = hash('sha256', $ua);

        $device = new TrustedDevice();
        $device
            ->setUser($user)
            ->setFingerprint($fingerprint)
            ->setLabel($parsed['label'])
            ->setIcon($parsed['icon'])
            ->setCreatedAt(new \DateTime())
            ->setLastUsedAt(new \DateTime())
            ->setExpiresAt(new \DateTime('+30 days'));

        $this->em->persist($device);
        $this->em->flush();
    }

    public function removeTrustedDevice(TrustedDevice $device): void
    {
        $this->em->remove($device);
        $this->em->flush();
    }

    public function removeAllForUser(User $user): void
    {
        $repo = $this->em->getRepository(TrustedDevice::class);
        $devices = $repo->findBy(['user' => $user]);

        foreach ($devices as $device) {
            $this->em->remove($device);
        }

        $this->em->flush();
    }

    public function getCurrentFingerprint(Request $request): string
    {
        $ua = $request->headers->get('User-Agent') ?? 'unknown';

        return hash('sha256', $ua);
    }
}
