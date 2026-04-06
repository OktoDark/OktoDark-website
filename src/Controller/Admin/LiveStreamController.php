<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Admin;

use App\Repository\AnalyticsPageViewRepository;
use App\Repository\AnalyticsSessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class LiveStreamController extends AbstractController
{
    #[Route('/live-stream', name: 'admin_live_stream')]
    public function liveStream(
        AnalyticsPageViewRepository $views,
        AnalyticsSessionRepository $sessions,
    ): StreamedResponse {
        $response = new StreamedResponse(static function () use ($views, $sessions) {
            while (true) {
                $data = [
                    'active' => $sessions->countActiveSessions(),
                    'views' => $views->countLastMinuteViews(),
                    'time' => (new \DateTime())->format('H:i:s'),
                ];

                echo 'data: '.json_encode($data)."\n\n";
                ob_flush();
                flush();

                sleep(3);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
