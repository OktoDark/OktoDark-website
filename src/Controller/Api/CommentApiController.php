<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Api;

use App\Entity\CardComment;
use App\Entity\User;
use App\Repository\CardCommentRepository;
use App\Repository\CardRepository;
use App\Service\CardService;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/comments', name: 'api_comment_')]
class CommentApiController extends AbstractController
{
    public function __construct(
        private readonly CardService $cardService,
        private readonly CardRepository $cardRepository,
        private readonly CardCommentRepository $commentRepository,
    ) {
    }

    #[Route('/card/{cardId}', name: 'list_card', methods: ['GET'])]
    public function listCardComments(int $cardId): JsonResponse
    {
        $card = $this->cardRepository->find($cardId);

        if (!$card) {
            return $this->handleNotFound('Card');
        }

        try {
            return $this->json([
                'success' => true,
                'comments' => array_map(fn (CardComment $comment) => $this->getCommentData($comment), $card->getComments()->toArray()),
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to retrieve comments for card');
        }
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createComment(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!isset($data['cardId']) || !isset($data['content'])) {
            return $this->handleBadRequest('Card ID and content are required');
        }

        $card = $this->cardRepository->find($data['cardId']);

        if (!$card) {
            return $this->handleNotFound('Card');
        }

        try {
            $comment = $this->cardService->addComment($card, $user, $data['content']);

            return $this->json([
                'success' => true,
                'comment' => $this->getCommentData($comment),
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to create comment');
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateComment(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $comment = $this->commentRepository->find($id);

        if (!$comment) {
            return $this->handleNotFound('Comment');
        }

        if ($comment->getAuthor() !== $user) {
            return $this->handleUnauthorized();
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['content'])) {
            return $this->handleBadRequest('Content is required');
        }

        try {
            $comment = $this->cardService->updateComment($comment, $data['content']);

            return $this->json([
                'success' => true,
                'comment' => $this->getCommentData($comment),
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to update comment');
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteComment(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $comment = $this->commentRepository->find($id);

        if (!$comment) {
            return $this->handleNotFound('Comment');
        }

        if ($comment->getAuthor() !== $user) {
            return $this->handleUnauthorized();
        }

        try {
            $this->cardService->deleteComment($comment);

            return $this->json(['success' => true]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to delete comment');
        }
    }

    private function getCommentData(CardComment $comment): array
    {
        return [
            'id' => $comment->getId(),
            'content' => $comment->getContent(),
            'author' => [
                'id' => $comment->getAuthor()->getId(),
                'username' => $comment->getAuthor()->getUsername(),
            ],
            'createdAt' => $comment->getCreatedAt()?->format(\DateTime::ATOM),
            'updatedAt' => $comment->getUpdatedAt()?->format(\DateTime::ATOM),
        ];
    }

    private function handleNotFound(string $entityName): JsonResponse
    {
        return $this->json(['success' => false, 'error' => $entityName.' not found'], Response::HTTP_NOT_FOUND);
    }

    private function handleBadRequest(string $message): JsonResponse
    {
        return $this->json(['success' => false, 'error' => $message], Response::HTTP_BAD_REQUEST);
    }

    private function handleUnauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->json(['success' => false, 'error' => $message], Response::HTTP_FORBIDDEN);
    }

    private function handleServiceException(\Throwable $e, string $defaultMessage): JsonResponse
    {
        // Log the exception for debugging purposes
        // $this->logger->error($defaultMessage.': '.$e->getMessage());

        $message = $defaultMessage;
        if ($e instanceof ORMException) {
            $message .= ': Database ORM error: '.$e->getMessage();
        } elseif ($e instanceof DBALException) {
            $message .= ': Database connection/query error: '.$e->getMessage();
        } else {
            $message .= ': '.$e->getMessage();
        }

        return $this->json(['success' => false, 'error' => $message], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
