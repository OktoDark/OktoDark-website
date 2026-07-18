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

use App\RBAC\PermissionAttributeScanner;
use App\RBAC\PermissionConfigLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'rbac:debug',
    description: 'Debug RBAC configuration, permissions, roles, and controller attributes.'
)]
class RBACDebugCommand extends Command
{
    public function __construct(
        private PermissionConfigLoader $loader,
        private PermissionAttributeScanner $scanner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->loader->loadAll();
        $attributes = $this->scanner->scanControllers();

        $output->writeln('<info>=== RBAC DEBUG ===</info>');

        // Roles
        $output->writeln("\n<comment>Roles:</comment>");
        foreach ($config['roles'] as $role => $perms) {
            $output->writeln(" - <info>$role</info> (".count($perms).' perms)');
        }

        // Permissions
        $output->writeln("\n<comment>Permissions:</comment>");
        foreach ($config['manual_permissions'] as $perm) {
            $output->writeln(" - {$perm['name']}");
        }

        // Controller attributes
        $output->writeln("\n<comment>Controller Attributes:</comment>");
        foreach ($attributes as $attr) {
            $output->writeln(" - {$attr['controller']}::{$attr['method']} → {$attr['name']}");
        }

        $output->writeln("\n<info>Done.</info>");

        return Command::SUCCESS;
    }
}
