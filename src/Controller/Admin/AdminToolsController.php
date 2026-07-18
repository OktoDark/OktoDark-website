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
use App\Security\Attribute\Permission;
use App\Service\CommandRunner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/tools')]
final class AdminToolsController extends AbstractController
{
    /**
     * Initialize controller dependencies for command execution and message dispatching.
     */
    public function __construct(
        private CommandRunner $runner,
        private string $projectDir,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * Build the whitelist of safe, predefined terminal commands available to admins.
     *
     * Composer commands are prefixed with the configured PHP binary so the correct
     * interpreter is always used.
     */
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

    /**
     * Render the admin terminal view populated with the allowed command list.
     */
    #[Route('/terminal', name: 'admin_tools_terminal', methods: ['GET'])]
    #[Permission('admin.tools.index')]
    public function terminal(): Response
    {
        return $this->render('@theme/admin/tools/terminal.html.twig', [
            'allowedCommands' => $this->getAllowedCommands(),
        ]);
    }

    /**
     * Queue an allowed terminal command as a CommandJob and dispatch it via Messenger.
     *
     * Validates the CSRF token, ensures the requested command is present in the
     * allowlist, then persists a pending CommandJob and dispatches a
     * CommandJobMessage for asynchronous execution, returning the new job ID.
     */
    #[Route('/terminal/run', name: 'admin_tools_terminal_run', methods: ['POST'])]
    #[Permission('admin.tools.run')]
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

    /**
     * Return the current status and output of a queued CommandJob as JSON.
     */
    #[Route('/terminal/status/{id}', name: 'admin_tools_terminal_status', methods: ['GET'])]
    #[Permission('admin.tools.status')]
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
