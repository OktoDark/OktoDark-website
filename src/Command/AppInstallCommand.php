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
use App\Service\RbacInstaller;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:install',
    description: 'Initial application install: RBAC + first SUPER ADMIN'
)]
class AppInstallCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private RbacInstaller $rbacInstaller,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🚀 OktoDark Initial Install');

        // 1) Run RBAC installer (roles, permissions, groups, mappings)
        $this->rbacInstaller->install($io);

        // 2) Check if a SUPER ADMIN already exists
        $roleRepo = $this->em->getRepository(Role::class);
        $userRepo = $this->em->getRepository(User::class);

        $superRole = $roleRepo->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);
        if (!$superRole) {
            $io->error('ROLE_SUPER_ADMIN does not exist after RBAC install.');

            return Command::FAILURE;
        }

        $existingSuperAdmins = $userRepo->createQueryBuilder('u')
            ->join('u.roleEntities', 'r')
            ->andWhere('r.name = :role')
            ->setParameter('role', 'ROLE_SUPER_ADMIN')
            ->getQuery()
            ->getResult();

        if (!empty($existingSuperAdmins)) {
            $io->success('SUPER ADMIN user already exists. Skipping user creation.');

            return Command::SUCCESS;
        }

        // 3) Ask for first SUPER ADMIN credentials
        $io->section('Create first SUPER ADMIN user');

        $username = $io->ask('Username');
        $email = $io->ask('Email');
        $password = $io->askHidden('Password (input hidden)');

        if (!$username || !$email || !$password) {
            $io->error('Username, email and password are required.');

            return Command::FAILURE;
        }

        // 4) Create user
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setActive(true);
        $user->setIsVerified(true);

        $hashed = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashed);

        // attach SUPER ADMIN role
        $user->addRoleEntity($superRole);

        $this->em->persist($user);
        $this->em->flush();

        $io->success("SUPER ADMIN user '$username' created.");

        return Command::SUCCESS;
    }
}
