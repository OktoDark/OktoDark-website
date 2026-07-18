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
    name: 'app:user:assign-roles',
    description: 'Assign multiple roles to a user (interactive or via arguments)'
)]
class UserAssignRolesCommand extends Command
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
            ->addArgument('roles', InputArgument::IS_ARRAY, 'Roles to assign (optional)');
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

        // Fetch all roles
        $roleRepo = $this->em->getRepository(Role::class);
        $allRoles = $roleRepo->findAll();

        if (empty($allRoles)) {
            $io->error('No roles exist in the system.');

            return Command::FAILURE;
        }

        // If no roles passed → interactive selection
        if (empty($rolesArg)) {
            $choices = array_map(static fn (Role $r) => $r->getName(), $allRoles);

            $io->section('Available roles:');
            foreach ($choices as $c) {
                $io->writeln(" - $c");
            }

            $io->writeln('');
            $io->writeln('Tip: Select multiple roles by entering them comma-separated.');
            $selectedInput = $io->ask('Enter roles to assign', '');

            if (!$selectedInput) {
                $io->warning('No roles selected.');

                return Command::SUCCESS;
            }

            $rolesArg = array_map('trim', explode(',', $selectedInput));
        }

        // Assign roles
        foreach ($rolesArg as $roleName) {
            $role = $roleRepo->findOneBy(['name' => $roleName]);

            if (!$role) {
                $io->warning("Role '$roleName' does not exist. Skipping.");
                continue;
            }

            // Check if already assigned
            $exists = $this->db->fetchOne(
                'SELECT COUNT(*) FROM user_role WHERE user_id = ? AND role_id = ?',
                [$user->getId(), $role->getId()]
            );

            if ($exists > 0) {
                $io->text("• User already has role '$roleName'.");
                continue;
            }

            // Insert into user_role
            $this->db->insert('user_role', [
                'user_id' => $user->getId(),
                'role_id' => $role->getId(),
            ]);

            $io->success("Assigned role '$roleName' to '$username'.");
        }

        return Command::SUCCESS;
    }
}
