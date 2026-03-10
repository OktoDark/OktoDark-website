<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Twig;

use App\Service\ThemeResolver;
use Twig\Loader\FilesystemLoader;

class ThemeLoader extends FilesystemLoader
{
    public function __construct(ThemeResolver $resolver)
    {
        $theme = $resolver->getTheme();

        parent::__construct([
            \sprintf('%s/templates/%s', dirname(__DIR__, 2), $theme),
        ]);

        // Register @theme namespace
        $this->addPath(
            \sprintf('%s/templates/%s', dirname(__DIR__, 2), $theme),
            'theme'
        );
    }
}
