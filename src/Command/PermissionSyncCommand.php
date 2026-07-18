<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Command;

use App\Security\PermissionScanner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:permissions:sync',
    description: 'Synchronize permissions from controller attributes into the database'
)]
class PermissionSyncCommand extends Command
{
    public function __construct(
        private PermissionScanner $scanner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔐 Permission Synchronization');
        $io->text('Scanning controllers for #[Permission(...)] attributes…');

        $result = $this->scanner->scan();

        $added = $result['added_permissions'];
        $updated = $result['updated_permissions'];
        $groupsAdded = $result['added_groups'];

        if (empty($added) && empty($updated) && empty($groupsAdded)) {
            $io->success('Everything is already up to date.');

            return Command::SUCCESS;
        }

        if (!empty($groupsAdded)) {
            $io->section('📁 New Permission Groups');
            foreach ($groupsAdded as $group) {
                $io->writeln("  • <info>$group</info>");
            }
        }

        if (!empty($added)) {
            $io->section('🆕 Added Permissions');
            foreach ($added as $perm) {
                $io->writeln("  • <info>$perm</info>");
            }
        }

        if (!empty($updated)) {
            $io->section('♻️ Updated Permissions');
            foreach ($updated as $perm) {
                $io->writeln("  • <comment>$perm</comment>");
            }
        }

        $io->success('Permission synchronization complete.');

        return Command::SUCCESS;
    }
}
