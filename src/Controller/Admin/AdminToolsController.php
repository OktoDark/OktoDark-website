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
use Symfony\Component\Uid\Uuid;

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
        $composer = $php.' '.$this->getParameter('composer_bin');

        return [
            ['label' => 'Validate',     'cmd' => "$composer validate"],
            ['label' => 'Install',      'cmd' => "$composer install"],
            ['label' => 'Update',       'cmd' => "$composer update"],
            ['label' => 'Outdated',     'cmd' => "$composer outdated"],
            ['label' => 'Cache Clear',  'cmd' => "$php bin/console cache:clear"],
            ['label' => 'Cache Warmup', 'cmd' => "$php bin/console cache:warmup"],
            ['label' => 'About',        'cmd' => "$php bin/console about"],
            ['label' => 'PHP Info',     'cmd' => "$php -i"],
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
        // Extract only the command strings
        $allowedCommands = array_column($this->getAllowedCommands(), 'cmd');
        $command = $request->request->get('command');

        if (!in_array($command, $allowedCommands, true)) {
            return $this->json(['error' => 'Command not allowed'], 400);
        }

        $job = new CommandJob();
        $job->setId(Uuid::v4());
        $job->setCommand($command);
        $job->setStatus(CommandJob::STATUS_PENDING);
        $job->setCreatedAt(new \DateTimeImmutable());

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
