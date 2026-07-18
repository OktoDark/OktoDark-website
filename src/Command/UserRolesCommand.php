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
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:roles',
    description: 'List all roles assigned to a user (via SQL)'
)]
class UserRolesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private Connection $db,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $input->getArgument('username');

        // Fetch user
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        if (!$user) {
            $io->error("User '$username' not found.");

            return Command::FAILURE;
        }

        $userId = $user->getId();

        // Fetch roles via SQL
        $rows = $this->db->fetchAllAssociative(
            'SELECT r.name, r.label
             FROM user_role ur
             JOIN role r ON r.id = ur.role_id
             WHERE ur.user_id = ?',
            [$userId]
        );

        if (empty($rows)) {
            $io->warning("User '$username' has no roles.");

            return Command::SUCCESS;
        }

        $io->title("Roles for user '$username'");

        $io->table(
            ['Role Name', 'Label'],
            array_map(static fn ($r) => [$r['name'], $r['label']], $rows)
        );

        return Command::SUCCESS;
    }
}
