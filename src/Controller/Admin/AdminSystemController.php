<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Admin;

use App\Repository\AnalyticsSessionRepository;
use App\Repository\RegistrationWaitlistRepository;
use App\Service\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/system')]
#[IsGranted('ROLE_ADMIN')]
final class AdminSystemController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $parameterBag,
        private SettingsProvider $settingsProvider,
    ) {
    }

    #[Route('/health', name: 'admin_system_health')]
    public function health(): Response
    {
        $health = [
            'database' => $this->checkDatabase(),
            'disk' => $this->getDiskUsage(),
            'memory' => $this->getMemoryUsage(),
            'php' => [
                'version' => \PHP_VERSION,
                'memory_limit' => \ini_get('memory_limit'),
                'upload_max_filesize' => \ini_get('upload_max_filesize'),
            ],
            'env' => $this->parameterBag->get('kernel.environment'),
            'debug' => $this->parameterBag->get('kernel.debug'),
            'symfony_version' => Kernel::VERSION,
        ];

        return $this->render('@theme/admin/system_health.html.twig', [
            'health' => $health,
        ]);
    }

    #[Route('/registration', name: 'admin_registration_panel')]
    public function registration(RegistrationWaitlistRepository $waitlistRepo): Response
    {
        return $this->render('@theme/admin/registration_panel.html.twig', [
            'registration_enabled' => $this->settingsProvider->isRegistrationEnabled(),
            'waitlist_count' => $waitlistRepo->count([]),
        ]);
    }

    #[Route('/registration/toggle', name: 'admin_registration_toggle', methods: ['POST'])]
    public function toggleRegistration(): RedirectResponse
    {
        $enabled = !$this->settingsProvider->isRegistrationEnabled();
        $this->settingsProvider->setRegistrationEnabled($enabled);

        $this->addFlash('success', $enabled ? 'Registration enabled.' : 'Registration disabled.');

        return $this->redirectToRoute('admin_registration_panel');
    }

    #[Route('/active-users', name: 'admin_active_users')]
    public function activeUsers(AnalyticsSessionRepository $repo): JsonResponse
    {
        $active = $repo->countActiveSessions();

        return new JsonResponse([
            'active' => $active,
        ]);
    }

    #[Route('/email-preview/{templateName}', name: 'admin_email_preview')]
    public function emailPreview(string $templateName): Response
    {
        $pathMap = [
            'login_alert' => 'emails/login_alert.html.twig',
            'login_code' => 'emails/login_code.html.twig',
            'confirmation' => 'emails/registration/confirmation.html.twig',
            'reset' => 'emails/reset_password/reset.html.twig',
            'invite_waitlist' => 'emails/admin/invite_waitlist.html.twig',
        ];

        $path = $pathMap[$templateName] ?? "emails/{$templateName}.html.twig";

        return $this->render($path, [
            'user' => $this->getUser(),
            'code' => '123456',
            'resetToken' => null,
            'tokenLifetime' => 3600,
            'loggedAt' => new \DateTime(),
            'ip' => '127.0.0.1',
            'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/123.0.0.0',
            'location' => 'Cluj-Napoca, Romania',
            'inviteUrl' => 'https://oktodark.com/register?invite=SAMPLE_TOKEN',

            // Dummy data for registration confirmation (SymfonyCasts VerifyEmailBundle)
            'signedUrl' => 'https://oktodark.com/verify/email/SAMPLE_SIGNED_URL',
            'expiresAtMessageKey' => 'The link expires at %expiresAt%',
            'expiresAtMessageData' => ['%expiresAt%' => (new \DateTime('+1 hour'))->format('H:i')],
        ]);
    }

    // --- Private Health Helpers ---

    private function checkDatabase(): array
    {
        try {
            $connection = $this->entityManager->getConnection();
            $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());

            return ['status' => 'ok', 'message' => 'Connected'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Connection failed: '.$e->getMessage()];
        }
    }

    private function getDiskUsage(): array
    {
        $path = $this->parameterBag->get('kernel.project_dir');
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        $used = $total - $free;
        $percentage = ($total > 0) ? round(($used / $total) * 100, 2) : 0;

        return [
            'total' => $this->formatBytes($total),
            'free' => $this->formatBytes($free),
            'used' => $this->formatBytes($used),
            'percentage' => $percentage,
            'status' => $percentage > 90 ? 'warning' : 'ok',
        ];
    }

    private function getMemoryUsage(): array
    {
        $usage = memory_get_usage(true);
        $limit = $this->parseSize(\ini_get('memory_limit'));
        $percentage = ($limit > 0) ? round(($usage / $limit) * 100, 2) : 0;

        return [
            'used' => $this->formatBytes($usage),
            'limit' => \ini_get('memory_limit'),
            'percentage' => $percentage,
            'status' => $percentage > 80 ? 'warning' : 'ok',
        ];
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, \count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }

    private function parseSize(string $size): int
    {
        $unit = preg_replace('/[^bkmgt]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);

        return round($unit ? $size * pow(1024, mb_stripos('bkmgt', $unit[0])) : $size);
    }
}
