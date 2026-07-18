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

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:install:light',
    description: 'Install the application WITHOUT RBAC, roles, permissions or users.'
)]
class AppInstallLightCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('⚙️ OktoDark Light Install');
        $io->text('Running install WITHOUT RBAC, roles, permissions or users.');

        // ---------------------------------------------------------
        // PLACE ANY NON-RBAC INITIALIZATION HERE
        // ---------------------------------------------------------

        // Example: create default boards, categories, settings, etc.
        // (Only if you want — otherwise leave empty)

        $io->success('Light install completed. RBAC was not touched.');

        return Command::SUCCESS;
    }
}
