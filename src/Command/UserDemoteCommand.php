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

// app:user:demote
#[AsCommand(
    name: 'app:user:demote',
    description: 'Remove admin or super admin rights from a user'
)]
class UserDemoteCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED)
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Remove admin role')
            ->addOption('super', null, InputOption::VALUE_NONE, 'Remove super admin role');
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
        $roleRemoved = false;

        if ($input->getOption('super')) {
            $role = $roleRepo->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);
            if ($role && $user->getRoleByName('ROLE_SUPER_ADMIN')) {
                $user->removeRoleEntity($role);
                $io->success("User '$username' demoted from SUPER ADMIN.");
                $roleRemoved = true;
            } else {
                $io->warning("User '$username' does not have ROLE_SUPER_ADMIN.");
            }
        } else {
            $role = $roleRepo->findOneBy(['name' => 'ROLE_ADMIN']);
            if ($role && $user->getRoleByName('ROLE_ADMIN')) {
                $user->removeRoleEntity($role);
                $io->success("User '$username' demoted from ADMIN.");
                $roleRemoved = true;
            } else {
                $io->warning("User '$username' does not have ROLE_ADMIN.");
            }
        }

        if ($roleRemoved) {
            $this->em->flush();
        }

        return Command::SUCCESS;
    }
}
