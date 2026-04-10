<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends AbstractController
{
    /**
     * Retourne les informations de l'utilisateur connecté
     * ainsi que son tenant.
     *
     * Appel :
     * GET /api/me
     * Header: Authorization: Bearer <token>
     */
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'Utilisateur non authentifié.'
            ], 401);
        }

        $tenant = $user->getTenant();

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'roles' => $user->getRoles(),
            'tenant' => $tenant ? [
                'id' => $tenant->getId(),
                'name' => $tenant->getName(),
                'slug' => $tenant->getSlug(),
                'plan' => $tenant->getPlan(),
            ] : null,
        ]);
    }
}