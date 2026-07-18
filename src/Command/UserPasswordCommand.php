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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// app:user:password
#[AsCommand(
    name: 'app:user:password',
    description: 'Change a user password'
)]
class UserPasswordCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
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
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);

        if (!$user) {
            $io->error("User '$username' not found.");

            return Command::FAILURE;
        }

        $password = $io->askHidden('New password');

        $hashed = $this->hasher->hashPassword($user, $password);
        $user->setPassword($hashed);

        $this->em->flush();

        $io->success("Password updated for '$username'.");

        return Command::SUCCESS;
    }
}
