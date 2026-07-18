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

use App\Repository\UserRepository;
use App\Service\RbacInstaller;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rbac:reset',
    description: 'Safely resets RBAC tables and reinstalls roles, permissions, and mappings.'
)]
class RbacResetCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private RbacInstaller $installer,
        private UserRepository $userRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::OPTIONAL, 'Username to assign SUPER ADMIN role to (optional, will prompt if not provided)')
            ->addOption('skip-super-admin', null, InputOption::VALUE_NONE, 'Skip assigning SUPER ADMIN to any user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('RBAC Reset Utility');
        $io->warning('This will clear ONLY RBAC tables. User accounts and content remain untouched.');

        $connection = $this->em->getConnection();

        $io->section('Disabling foreign key checks...');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $io->section('Clearing RBAC tables...');

        $tables = [
            'role_permission',
            'permission',
            'permission_group',
            'role',
            'user_role',
        ];

        foreach ($tables as $table) {
            $connection->executeStatement("DELETE FROM `$table`");
            $io->text("Cleared table: $table");
        }

        $io->section('Re-enabling foreign key checks...');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $io->section('Reinstalling RBAC...');
        $this->installer->install($io);

        // Assign SUPER ADMIN to a user (interactive)
        if ($input->getOption('skip-super-admin')) {
            $io->text('Skipping SUPER ADMIN assignment (--skip-super-admin flag set).');
        } else {
            $io->section('Assigning SUPER ADMIN to admin user...');

            $username = $input->getArgument('username');
            if (!$username) {
                $username = $io->ask('Enter username to assign SUPER ADMIN role to (leave empty to skip)', null);
            }

            if ($username) {
                $user = $this->userRepo->findOneBy(['username' => $username]);

                if ($user) {
                    $role = $this->em->getRepository(\App\Entity\Role::class)
                        ->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);

                    if ($role) {
                        $user->addRoleEntity($role);
                        $this->em->flush();
                        $io->success("Assigned ROLE_SUPER_ADMIN to user: $username");
                    } else {
                        $io->error('SUPER ADMIN role not found after reinstall.');
                    }
                } else {
                    $io->warning("User '$username' not found. Skipping SUPER ADMIN assignment.");
                }
            } else {
                $io->text('No username provided, skipping SUPER ADMIN assignment.');
            }
        }

        $io->success('RBAC reset complete.');

        return Command::SUCCESS;
    }
}
