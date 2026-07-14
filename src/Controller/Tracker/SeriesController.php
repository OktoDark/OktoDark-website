<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Tracker;

use App\Entity\User;
use App\Repository\SeasonRepository;
use App\Repository\TVRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SeriesController extends AbstractController
{
    #[Route('/tracker/series/{id}', name: 'app_tracker_series')]
    public function show(
        int $id,
        TVRepository $tvRepo,
        SeasonRepository $seasonRepo,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Correct ownership check
        $tv = $tvRepo->find($id);

        if (!$tv || $tv->getUser() !== $user) {
            throw $this->createNotFoundException('Series not found.');
        }

        // Fetch seasons for this TV show
        $seasons = $seasonRepo->findBy([
            'relatedTv' => $tv,
        ]);

        return $this->render('@theme/tracker/series/show.html.twig', [
            'series' => $tv,
            'seasons' => $seasons,
        ]);
    }
}
