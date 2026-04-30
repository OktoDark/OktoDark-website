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

use App\Entity\CommandJob;
use App\Message\CommandJobMessage;
use App\Service\CommandRunner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tools'), IsGranted('ROLE_ADMIN')]
final class AdminToolsController extends AbstractController
{
    public function __construct(
        private CommandRunner $runner,
        private string $projectDir,
        private MessageBusInterface $bus,
    ) {
    }

    private function getAllowedCommands(): array
    {
        $php = $this->getParameter('php_bin');
        $composer = $this->getParameter('composer_bin');

        // Ensure we run composer through the specific PHP binary
        $composerCmd = $php.' '.$composer;

        return [
            ['label' => 'Composer Validate',     'cmd' => "$composerCmd validate"],
            ['label' => 'Composer Install',      'cmd' => "$composerCmd install"],
            ['label' => 'Composer Update',       'cmd' => "$composerCmd update"],
            ['label' => 'Composer Outdated',     'cmd' => "$composerCmd outdated"],
            ['label' => 'Symfony Cache Clear',  'cmd' => "$php bin/console cache:clear"],
            ['label' => 'Symfony Cache Warmup', 'cmd' => "$php bin/console cache:warmup"],
            ['label' => 'Symfony About',        'cmd' => "$php bin/console about"],
            ['label' => 'PHP Info',              'cmd' => "$php -i"],
        ];
    }

    #[Route('/terminal', name: 'admin_tools_terminal', methods: ['GET'])]
    public function terminal(): Response
    {
        return $this->render('@theme/admin/tools/terminal.html.twig', [
            'allowedCommands' => $this->getAllowedCommands(),
        ]);
    }

    #[Route('/terminal/run', name: 'admin_tools_terminal_run', methods: ['POST'])]
    public function terminalRun(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('terminal-run', $request->request->get('_token'))) {
            return $this->json(['error' => 'Invalid CSRF token'], 400);
        }

        // Extract only the command strings
        $allowedCommands = array_column($this->getAllowedCommands(), 'cmd');
        $command = $request->request->get('command');

        if (!\in_array($command, $allowedCommands, true)) {
            return $this->json(['error' => 'Command not allowed'], 400);
        }

        $job = new CommandJob();
        // Use a safe ID generation method since symfony/uid is not in composer.json
        $job->setId(bin2hex(random_bytes(16)));
        $job->setCommand($command);
        $job->setStatus(CommandJob::STATUS_PENDING);
        $job->setCreatedAt(new \DateTimeImmutable());
        $job->setOutput("Job queued...\n");

        $em->persist($job);
        $em->flush();

        $this->bus->dispatch(new CommandJobMessage((string) $job->getId()));

        return $this->json(['jobId' => (string) $job->getId()]);
    }

    #[Route('/terminal/status/{id}', name: 'admin_tools_terminal_status', methods: ['GET'])]
    public function terminalStatus(string $id, EntityManagerInterface $em): Response
    {
        $job = $em->getRepository(CommandJob::class)->find($id);

        if (!$job) {
            return $this->json(['error' => 'Job not found'], 404);
        }

        return $this->json([
            'status' => $job->getStatus(),
            'output' => $job->getOutput(),
            'startedAt' => $job->getStartedAt()?->format(\DateTimeInterface::ATOM),
            'finishedAt' => $job->getFinishedAt()?->format(\DateTimeInterface::ATOM),
        ]);
    }
}
