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

use App\Entity\CommandJob;
use App\Message\CommandJobMessage;
use App\Service\CommandRunner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CommandJobMessageHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private CommandRunner $runner,
        private string $projectDir,
    ) {
    }

    public function __invoke(CommandJobMessage $message): void
    {
        $repo = $this->em->getRepository(CommandJob::class);
        /** @var CommandJob|null $job */
        $job = $repo->find($message->getJobId());

        if (!$job) {
            return;
        }

        $job->setStatus(CommandJob::STATUS_RUNNING);
        $job->setStartedAt(new \DateTimeImmutable());
        $job->setOutput('');
        $this->em->flush();

        $lastFlush = microtime(true);

        $exitCode = $this->runner->stream(
            $job->getCommand(),
            $this->projectDir,
            function (string $chunk) use ($job, &$lastFlush) {
                $job->appendOutput($chunk);

                // Flush every 0.3 seconds
                if (microtime(true) - $lastFlush > 0.3) {
                    $this->em->flush();
                    $lastFlush = microtime(true);
                }
            }
        );

        $job->appendOutput("\n[exit code] $exitCode\n");
        $job->setStatus(CommandJob::STATUS_FINISHED);
        $job->setFinishedAt(new \DateTimeImmutable());

        $this->em->flush();
    }
}
