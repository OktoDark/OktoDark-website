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

use App\Repository\CareersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CareersController extends AbstractController
{
    #[Route('/careers', name: 'careers', methods: ['GET'])]
    public function careers(CareersRepository $careers): Response
    {
        return $this->render('@theme/careers.html.twig', [
            'careers' => $careers->findAll(),
        ]);
    }
}
