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

use App\Entity\MediaMetadata;
use App\Entity\TV;
use App\Entity\User;
use App\Repository\TVRepository;
use App\Security\Attribute\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ListController extends AbstractController
{
    #[Route('/tracker/list', name: 'app_tracker_list')]
    #[Permission('tracker.list')]
    public function index(Request $request, TVRepository $tvRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $search = $request->query->get('search');
        $country = $request->query->get('country');
        $actor = $request->query->get('actor');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 24;

        $result = $tvRepo->findUserList($user, $search, $country, $actor, $page, $limit);

        return $this->render('@theme/tracker/list/index.html.twig', [
            'list' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'search' => $search,
            'country' => $country,
            'actor' => $actor,
        ]);
    }

    #[Route('/tracker/list/add/{metaId}', name: 'app_tracker_list_add', methods: ['POST'])]
    #[Permission('tracker.list.add')]
    public function add(int $metaId, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $meta = $em->getRepository(MediaMetadata::class)->find($metaId);

        $tv = new TV();
        $tv->setMediaMetadata($meta);
        $tv->setUser($user);

        $em->persist($tv);
        $em->flush();

        return $this->json(['status' => 'added']);
    }

    #[Route('/tracker/list/remove/{id}', name: 'app_tracker_list_remove', methods: ['POST'])]
    #[Permission('tracker.list.remove')]
    public function remove(int $id, TVRepository $tvRepo, EntityManagerInterface $em): Response
    {
        $tv = $tvRepo->find($id);
        $em->remove($tv);
        $em->flush();

        return $this->json(['status' => 'removed']);
    }
}
