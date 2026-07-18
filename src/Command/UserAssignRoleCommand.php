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
use App\Security\RoleValidator;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// php bin/console app:user:assign-role <username> <roleName>
#[AsCommand(
    name: 'app:user:assign-role',
    description: 'Assign a role to a user using direct SQL (user_role table)'
)]
class UserAssignRoleCommand extends Command
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
            ->addArgument('username', InputArgument::REQUIRED, 'Username')
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

        // Check if already assigned
        $exists = $this->db->fetchOne(
            'SELECT COUNT(*) FROM user_role WHERE user_id = ? AND role_id = ?',
            [$userId, $roleId]
        );

        if ($exists > 0) {
            $io->warning("User '$username' already has role '$roleName'.");

            return Command::SUCCESS;
        }

        $validator = new RoleValidator($this->em);
        $errors = $validator->validate($role);

        if (!empty($errors)) {
            foreach ($errors as $err) {
                $io->warning($err);
            }
            $io->warning('Assigning role anyway, but it has missing permissions.');
        }

        // Insert into user_role
        $this->db->insert('user_role', [
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);

        $io->success("Role '$roleName' assigned to user '$username' via SQL.");

        return Command::SUCCESS;
    }
}
