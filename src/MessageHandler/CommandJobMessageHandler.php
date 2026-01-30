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
        $this->em->flush();

        try {
            $output = $this->runner->run($job->getCommand(), $this->projectDir);
            $job->setOutput($output);
            $job->setStatus(CommandJob::STATUS_FINISHED);
        } catch (\Throwable $e) {
            $job->appendOutput("\n[exception] ".$e->getMessage());
            $job->setStatus(CommandJob::STATUS_FAILED);
        }

        $job->setFinishedAt(new \DateTimeImmutable());
        $this->em->flush();
    }
}
