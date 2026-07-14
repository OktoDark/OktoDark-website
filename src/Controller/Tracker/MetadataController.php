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

use App\Service\MetadataEnricher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class MetadataController extends AbstractController
{
    #[Route('/metadata/enrich', name: 'metadata_enrich')]
    public function enrich(MetadataEnricher $enricher): JsonResponse
    {
        $result = $enricher->enrichMissing();

        return new JsonResponse([
            'status' => 'ok',
            'updated' => $result,
        ]);
    }
}
