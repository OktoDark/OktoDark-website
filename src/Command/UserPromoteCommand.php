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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// app:user:promote
#[AsCommand(
    name: 'app:user:promote',
    description: 'Promote a user to admin or super admin'
)]
class UserPromoteCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Username to promote')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Promote to admin')
            ->addOption('super', null, InputOption::VALUE_NONE, 'Promote to super admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $input->getArgument('username');
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);

        if (!$user) {
            $io->error("User '$username' not found.");

            return Command::FAILURE;
        }

        $roleRepo = $this->em->getRepository(Role::class);
        $roleAssigned = false;

        if ($input->getOption('super')) {
            $role = $roleRepo->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);
            if ($role) {
                $user->addRoleEntity($role);
                $io->success("User '$username' promoted to SUPER ADMIN.");
                $roleAssigned = true;
            } else {
                $io->error('ROLE_SUPER_ADMIN not found in the system.');
            }
        } else {
            $role = $roleRepo->findOneBy(['name' => 'ROLE_ADMIN']);
            if ($role) {
                $user->addRoleEntity($role);
                $io->success("User '$username' promoted to ADMIN.");
                $roleAssigned = true;
            } else {
                $io->error('ROLE_ADMIN not found in the system.');
            }
        }

        if ($roleAssigned) {
            $this->em->flush();
        }

        return Command::SUCCESS;
    }
}
