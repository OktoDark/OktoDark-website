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
use App\Repository\UserRepository;
use App\Utils\Validator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Stopwatch\Stopwatch;

use function Symfony\Component\String\u;

#[AsCommand(
    name: 'app:add-user',
    description: 'Creates users and stores them in the database'
)]
class AddUserCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private Validator $validator,
        private UserRepository $users,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp($this->getCommandHelp())
            ->addArgument('username', InputArgument::OPTIONAL, 'The username of the new user')
            ->addArgument('password', InputArgument::OPTIONAL, 'The plain password of the new user')
            ->addArgument('email', InputArgument::OPTIONAL, 'The email of the new user')
            ->addArgument('first-name', InputArgument::OPTIONAL, 'The first name of the new user')
            ->addArgument('last-name', InputArgument::OPTIONAL, 'The last name of the new user')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'If set, the user is created as an administrator')
            ->addOption('super', null, InputOption::VALUE_NONE, 'If set, the user is created as a super administrator');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if (
            null !== $input->getArgument('username')
            && null !== $input->getArgument('password')
            && null !== $input->getArgument('email')
            && null !== $input->getArgument('first-name')
            && null !== $input->getArgument('last-name')
        ) {
            return;
        }

        $this->io->title('Add User Command Interactive Wizard');
        $this->io->text([
            'If you prefer to not use this interactive wizard, provide the',
            'arguments required by this command as follows:',
            '',
            ' $ php bin/console app:add-user username password email firstName lastName',
            '',
            'Now we\'ll ask you for the value of all the missing command arguments.',
        ]);

        // Username
        $username = $input->getArgument('username');
        if (null !== $username) {
            $this->io->text(' > <info>Username</info>: '.$username);
        } else {
            $username = $this->io->ask('Username', null, [$this->validator, 'validateUsername']);
            $input->setArgument('username', $username);
        }

        // Password
        $password = $input->getArgument('password');
        if (null !== $password) {
            $this->io->text(' > <info>Password</info>: '.u('*')->repeat(u($password)->length()));
        } else {
            $password = $this->io->askHidden('Password (your type will be hidden)', [$this->validator, 'validatePassword']);
            $input->setArgument('password', $password);
        }

        // Email
        $email = $input->getArgument('email');
        if (null !== $email) {
            $this->io->text(' > <info>Email</info>: '.$email);
        } else {
            $email = $this->io->ask('Email', null, [$this->validator, 'validateEmail']);
            $input->setArgument('email', $email);
        }

        // First Name
        $firstName = $input->getArgument('first-name');
        if (null !== $firstName) {
            $this->io->text(' > <info>First Name</info>: '.$firstName);
        } else {
            $firstName = $this->io->ask('First Name', null, [$this->validator, 'validateFullName']);
            $input->setArgument('first-name', $firstName);
        }

        // Last Name
        $lastName = $input->getArgument('last-name');
        if (null !== $lastName) {
            $this->io->text(' > <info>Last Name</info>: '.$lastName);
        } else {
            $lastName = $this->io->ask('Last Name', null, [$this->validator, 'validateFullName']);
            $input->setArgument('last-name', $lastName);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('add-user-command');

        $username = $input->getArgument('username');
        $plainPassword = $input->getArgument('password');
        $email = $input->getArgument('email');
        $firstName = $input->getArgument('first-name');
        $lastName = $input->getArgument('last-name');
        $isAdmin = $input->getOption('admin');
        $isSuper = $input->getOption('super');

        $this->validateUserData($username, $plainPassword, $email, $firstName, $lastName);

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);

        // Determine role based on options
        if ($isSuper) {
            $roleName = 'ROLE_SUPER_ADMIN';
        } elseif ($isAdmin) {
            $roleName = User::ROLE_ADMIN;
        } else {
            $roleName = User::ROLE_USER;
        }

        $role = $this->entityManager->getRepository(Role::class)->findOneBy(['name' => $roleName]);
        if ($role) {
            $user->addRoleEntity($role);
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->io->success(\sprintf(
            '%s was successfully created: %s (%s)',
            $isSuper ? 'Super administrator user' : ($isAdmin ? 'Administrator user' : 'User'),
            $user->getUsername(),
            $user->getEmail()
        ));

        $event = $stopwatch->stop('add-user-command');
        if ($output->isVerbose()) {
            $this->io->comment(\sprintf(
                'New user database id: %d / Elapsed time: %.2f ms / Consumed memory: %.2f MB',
                $user->getId(),
                $event->getDuration(),
                $event->getMemory() / (1024 ** 2)
            ));
        }

        return Command::SUCCESS;
    }

    private function validateUserData($username, $plainPassword, $email, $firstName, $lastName): void
    {
        // Username must be unique
        $existingUser = $this->users->findOneBy(['username' => $username]);
        if ($existingUser) {
            throw new RuntimeException(\sprintf('There is already a user registered with the "%s" username.', $username));
        }

        // Validate fields
        $this->validator->validatePassword($plainPassword);
        $this->validator->validateEmail($email);
        $this->validator->validateFullName($firstName);
        $this->validator->validateFullName($lastName);

        // Email must be unique
        $existingEmail = $this->users->findOneBy(['email' => $email]);
        if ($existingEmail) {
            throw new RuntimeException(\sprintf('There is already a user registered with the "%s" email.', $email));
        }
    }

    private function getCommandHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command creates new users and saves them in the database:

  <info>php %command.full_name%</info> <comment>username password email firstName lastName</comment>

To create administrator users, add the <comment>--admin</comment> option:

  <info>php %command.full_name%</info> username password email firstName lastName <comment>--admin</comment>

To create super administrator users, add the <comment>--super</comment> option:

  <info>php %command.full_name%</info> username password email firstName lastName <comment>--super</comment>

If you omit any of the required arguments, the command will ask you to provide the missing values.
HELP;
    }
}
