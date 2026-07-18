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

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// app:user:list
#[AsCommand(
    name: 'app:user:list',
    description: 'List all users with roles'
)]
class UserListCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->em->getRepository(User::class)->findAll();

        $rows = [];
        foreach ($users as $user) {
            $rows[] = [
                $user->getId(),
                $user->getUsername(),
                $user->getEmail(),
                implode(', ', $user->getRoles()),
                $user->isActive() ? 'Active' : 'Disabled',
            ];
        }

        $io->table(
            ['ID', 'Username', 'Email', 'Roles', 'Status'],
            $rows
        );

        return Command::SUCCESS;
    }
}
