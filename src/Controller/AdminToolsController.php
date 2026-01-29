<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller;

use App\Entity\CommandJob;
use App\Service\CommandRunner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/tools'), IsGranted('ROLE_ADMIN')]
final class AdminToolsController extends AbstractController
{
    public function __construct(
        private CommandRunner $runner,
        private string $projectDir,
    ) {
    }

    // 1) Web terminal for composer & symfony commands
    #[Route('/terminal', name: 'admin_tools_terminal', methods: ['GET', 'POST'])]
    public function terminal(Request $request): Response
    {
        $allowedCommands = [
            'composer validate',
            'composer install',
            'composer update',
            'composer outdated',
            'symfony composer:update',
            'symfony cache:clear',
            'symfony cache:warmup',
            'symfony about',
        ];

        $output = null;
        $selected = null;

        if ($request->isMethod('POST')) {
            $selected = $request->request->get('command');

            if (!\in_array($selected, $allowedCommands, true)) {
                $this->addFlash('error', 'Command not allowed.');
            } else {
                $output = $this->runner->run($selected, $this->projectDir);
            }
        }

        return $this->render('@theme/admin/tools/terminal.html.twig', [
            'allowedCommands' => $allowedCommands,
            'selected' => $selected,
            'output' => $output,
        ]);
    }

    #[Route('/terminal/run', name: 'admin_tools_terminal_run', methods: ['POST'])]
    public function terminalRun(Request $request, EntityManagerInterface $em): Response
    {
        $allowedCommands = [
            'composer validate',
            'composer install',
            'composer update',
            'composer update --dry-run',
            'composer outdated',
            'symfony cache:clear',
            'symfony cache:warmup',
            'symfony about',
        ];

        $command = $request->request->get('command');

        if (!\in_array($command, $allowedCommands, true)) {
            return $this->json(['error' => 'Command not allowed'], 400);
        }

        $job = new CommandJob();
        $job->setId(Uuid::v4());
        $job->setCommand($command);
        $job->setStatus(CommandJob::STATUS_PENDING);
        $job->setCreatedAt(new \DateTimeImmutable());

        $em->persist($job);
        $em->flush();

        // Dispatch Messenger message
        $this->dispatchMessage(new \App\Message\CommandJobMessage((string) $job->getId()));

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

    // 2) Composer.json editor
    #[Route('/composer', name: 'admin_tools_composer', methods: ['GET', 'POST'])]
    public function composerEditor(Request $request): Response
    {
        $fs = new Filesystem();
        $composerPath = $this->projectDir.'/composer.json';

        if (!$fs->exists($composerPath)) {
            throw $this->createNotFoundException('composer.json not found.');
        }

        $currentContent = file_get_contents($composerPath);
        $newContent = $currentContent;
        $diff = null;
        $mode = 'view'; // view | preview | saved

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action'); // "preview" or "save"
            $newContent = (string) $request->request->get('composer_json');

            // Basic JSON validation
            json_decode($newContent, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                $this->addFlash('error', 'Invalid JSON: '.json_last_error_msg());

                return $this->render('@theme/admin/tools/composer.html.twig', [
                    'composerJson' => $newContent,
                    'originalJson' => $currentContent,
                    'diff' => null,
                    'mode' => 'view',
                ]);
            }

            // Compute line-by-line diff (simple implementation)
            $diff = $this->createDiff($currentContent, $newContent);

            if ('preview' === $action) {
                $mode = 'preview';
            } elseif ('save' === $action) {
                $backupPath = $composerPath.'.'.date('Ymd_His').'.bak';
                $fs->copy($composerPath, $backupPath);
                file_put_contents($composerPath, $newContent);

                $this->addFlash('success', 'composer.json updated. Backup: '.basename($backupPath));
                $mode = 'saved';
                $currentContent = $newContent;
            }
        }

        return $this->render('@theme/admin/tools/composer.html.twig', [
            'composerJson' => $newContent,
            'originalJson' => $currentContent,
            'diff' => $diff,
            'mode' => $mode,
        ]);
    }

    /**
     * Very simple line-based diff: returns array of ['type' => 'same|add|remove', 'line' => string].
     */
    private function createDiff(string $old, string $new): array
    {
        $oldLines = explode("\n", $old);
        $newLines = explode("\n", $new);

        // Naive approach: mark lines that differ by index
        $max = max(\count($oldLines), \count($newLines));
        $result = [];

        for ($i = 0; $i < $max; ++$i) {
            $oldLine = $oldLines[$i] ?? null;
            $newLine = $newLines[$i] ?? null;

            if ($oldLine === $newLine) {
                $result[] = ['type' => 'same', 'line' => $newLine ?? ''];
            } elseif (null === $oldLine && null !== $newLine) {
                $result[] = ['type' => 'add', 'line' => $newLine];
            } elseif (null !== $oldLine && null === $newLine) {
                $result[] = ['type' => 'remove', 'line' => $oldLine];
            } else {
                $result[] = ['type' => 'remove', 'line' => $oldLine];
                $result[] = ['type' => 'add', 'line' => $newLine];
            }
        }

        return $result;
    }

    // 3) Symfony tools dashboard
    #[Route('/symfony', name: 'admin_tools_symfony', methods: ['GET', 'POST'])]
    public function symfonyDashboard(Request $request): Response
    {
        $tools = [
            'Cache: clear' => 'symfony cache:clear',
            'Cache: warmup' => 'symfony cache:warmup',
            'Debug: router' => 'symfony debug:router',
            'Debug: container' => 'symfony debug:container',
            'Debug: autowiring' => 'symfony debug:autowiring',
            'About project' => 'symfony about',
        ];

        $output = null;
        $chosen = null;

        if ($request->isMethod('POST')) {
            $chosen = $request->request->get('tool');
            if (!isset($tools[$chosen])) {
                $this->addFlash('error', 'Invalid tool selection.');
            } else {
                $command = $tools[$chosen];
                $output = $this->runner->run($command, $this->projectDir);
            }
        }

        return $this->render('@theme/admin/tools/symfony.html.twig', [
            'tools' => array_keys($tools),
            'output' => $output,
            'chosen' => $chosen,
        ]);
    }
}
