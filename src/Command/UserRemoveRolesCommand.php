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
    name: 'app:user:remove-roles',
    description: 'Remove multiple roles from a user (interactive or via arguments)'
)]
class UserRemoveRolesCommand extends Command
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
            ->addArgument('roles', InputArgument::IS_ARRAY, 'Roles to remove (optional)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $input->getArgument('username');
        $rolesArg = $input->getArgument('roles');

        // Fetch user
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        if (!$user) {
            $io->error("User '$username' not found.");

            return Command::FAILURE;
        }

        // Fetch all roles assigned to user
        $assignedRoles = $this->db->fetchAllAssociative(
            'SELECT r.id, r.name
             FROM user_role ur
             JOIN role r ON r.id = ur.role_id
             WHERE ur.user_id = ?',
            [$user->getId()]
        );

        if (empty($assignedRoles)) {
            $io->warning("User '$username' has no roles.");

            return Command::SUCCESS;
        }

        // If no roles passed → interactive selection
        if (empty($rolesArg)) {
            $io->section("Roles assigned to '$username':");

            foreach ($assignedRoles as $r) {
                $io->writeln(' - '.$r['name']);
            }

            $io->writeln('');
            $io->writeln('Tip: Select multiple roles by entering them comma-separated.');
            $selectedInput = $io->ask('Enter roles to remove', '');

            if (!$selectedInput) {
                $io->warning('No roles selected.');

                return Command::SUCCESS;
            }

            $rolesArg = array_map('trim', explode(',', $selectedInput));
        }

        // Remove roles
        foreach ($rolesArg as $roleName) {
            $role = $this->em->getRepository(Role::class)->findOneBy(['name' => $roleName]);

            if (!$role) {
                $io->warning("Role '$roleName' does not exist. Skipping.");
                continue;
            }

            // Check if user has this role
            $exists = $this->db->fetchOne(
                'SELECT COUNT(*) FROM user_role WHERE user_id = ? AND role_id = ?',
                [$user->getId(), $role->getId()]
            );

            if (0 === $exists) {
                $io->text("• User does not have role '$roleName'.");
                continue;
            }

            // Delete from user_role
            $this->db->executeStatement(
                'DELETE FROM user_role WHERE user_id = ? AND role_id = ?',
                [$user->getId(), $role->getId()]
            );

            $io->success("Removed role '$roleName' from '$username'.");
        }

        return Command::SUCCESS;
    }
}
