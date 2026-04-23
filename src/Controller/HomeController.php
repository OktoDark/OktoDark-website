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

use App\Repository\NewsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/')]
final class HomeController extends AbstractController
{
    #[Route('/', name: 'home_index', methods: ['GET'])]
    #[Route('/home', name: 'home', methods: ['GET'])]
    public function home(NewsRepository $news): Response
    {
        return $this->render('@theme/home.html.twig', [
            'news' => $news->findLatest(),
        ]);
    }
}
