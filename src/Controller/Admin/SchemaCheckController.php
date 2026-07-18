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

use App\Security\Attribute\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')] // Changed base route to /admin
class SchemaCheckController extends AbstractController
{
    /**
     * Compare the current database schema against the mapped Doctrine entities.
     *
     * Generates the update SQL (including drops) via the SchemaTool and reports
     * whether the schema is synchronized or lists the statements required to sync.
     */
    #[Route('/schema-check', name: 'admin_schema_check_index')]
    #[Permission('admin.schema_check.index')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $metadatas = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);

        $updateSql = [];
        try {
            $updateSql = $schemaTool->getUpdateSchemaSql($metadatas, true); // Pass true to get drop statements as well
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'An error occurred while trying to generate schema update SQL: '.$e->getMessage(),
                'details' => $e->getTraceAsString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (empty($updateSql)) {
            return new JsonResponse([
                'status' => 'synchronized',
                'message' => 'Your database schema is synchronized with your Doctrine entities.',
            ]);
        }

        return new JsonResponse([
            'status' => 'out_of_sync',
            'message' => 'Your database schema is NOT synchronized with your Doctrine entities. The following SQL statements would be executed to bring it in sync:',
            'sql' => $updateSql,
        ], Response::HTTP_OK);
    }

    /**
     * Return the current authenticated user's identifier and roles as JSON (debug route).
     */
    #[Route('/my-roles', name: 'admin_my_roles')] // New route for debugging roles
    public function myRoles(): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['message' => 'No user is currently logged in.'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ]);
    }
}
