<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Tracker\Import;

use App\Security\Attribute\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ImportDashboardController extends AbstractController
{
    #[Route('/import/dashboard', name: 'app_import_dashboard', methods: ['GET'])]
    #[Permission('tracker.import.dashboard')]
    public function dashboard(Request $request): Response
    {
        $session = $request->getSession();
        $logs = $session->get('import_logs', []);

        return $this->render('@theme/tracker/import/dashboard.html.twig', [
            'logs' => $logs,
        ]);
    }

    #[Route('/import/logs', name: 'app_import_logs', methods: ['GET'])]
    public function logs(Request $request): Response
    {
        $session = $request->getSession();

        return $this->json(['logs' => $session->get('import_logs', [])]);
    }

    #[Route('/import/logs/clear', name: 'app_import_logs_clear', methods: ['POST'])]
    public function clearLogs(Request $request): Response
    {
        $request->getSession()->set('import_logs', []);

        return $this->json(['cleared' => true]);
    }
}
