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

use App\Entity\Role;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:remove-role',
    description: 'Remove a role from a user using direct SQL (user_role table)'
)]
class UserRemoveRoleCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private Connection $db,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED)
            ->addArgument('role', InputArgument::REQUIRED, 'Role name (e.g. ROLE_ADMIN)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $input->getArgument('username');
        $roleName = $input->getArgument('role');

        // Fetch user
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        if (!$user) {
            $io->error("User '$username' not found.");

            return Command::FAILURE;
        }

        // Fetch role
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => $roleName]);
        if (!$role) {
            $io->error("Role '$roleName' not found.");

            return Command::FAILURE;
        }

        $userId = $user->getId();
        $roleId = $role->getId();

        // Check if exists
        $exists = $this->db->fetchOne(
            'SELECT COUNT(*) FROM user_role WHERE user_id = ? AND role_id = ?',
            [$userId, $roleId]
        );

        if (0 === $exists) {
            $io->warning("User '$username' does not have role '$roleName'.");

            return Command::SUCCESS;
        }

        // Delete
        $this->db->executeStatement(
            'DELETE FROM user_role WHERE user_id = ? AND role_id = ?',
            [$userId, $roleId]
        );

        $io->success("Role '$roleName' removed from user '$username' via SQL.");

        return Command::SUCCESS;
    }
}
