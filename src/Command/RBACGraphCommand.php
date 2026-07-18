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

use App\RBAC\PermissionConfigLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// dot -Tpng rbac.dot -o rbac.png
#[AsCommand(
    name: 'rbac:graph',
    description: 'Generate RBAC Graphviz DOT file.'
)]
class RBACGraphCommand extends Command
{
    public function __construct(private PermissionConfigLoader $loader)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->loader->loadAll();

        $dot = "digraph RBAC {\n";
        $dot .= "rankdir=LR;\n";
        $dot .= "node [shape=box, style=rounded];\n";

        foreach ($config['roles'] as $role => $perms) {
            foreach ($perms as $perm) {
                $dot .= "\"$role\" -> \"$perm\";\n";
            }
        }

        $dot .= "}\n";

        file_put_contents('rbac.dot', $dot);

        $output->writeln('<info>RBAC graph generated: rbac.dot</info>');

        return Command::SUCCESS;
    }
}
