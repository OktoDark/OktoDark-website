<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller;

use App\Repository\ModsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ModsController extends AbstractController
{
    #[Route('/mods', name: 'mods', methods: ['GET'])]
    public function mods(ModsRepository $mods): Response
    {
        return $this->render('@theme/mods.html.twig', [
            'mods' => $mods->findAll(),
        ]);
    }
}
