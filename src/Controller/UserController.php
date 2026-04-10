<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\TenantRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    /**
     * Endpoint pour créer un utilisateur admin lié à un tenant.
     *
     * Exemple d'URL :
     * POST /api/tenants/7/users
     *
     * JSON attendu :
     * {
     *   "email": "admin@dupon.fr",
     *   "password": "motdepasse123",
     *   "fullName": "Abdelkader TAFTAF"
     * }
     */
    #[Route('/api/tenants/{tenantId}/users', name: 'create_tenant_user', methods: ['POST'])]
    public function createTenantUser(
        int $tenantId,
        Request $request,
        TenantRepository $tenantRepository,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
    ): JsonResponse {
        /**
         * 1. Récupérer les données JSON envoyées par Postman
         */
        $data = json_decode($request->getContent(), true);

        /**
         * 2. Vérifier que le JSON est valide
         */
        if (!is_array($data)) {
            return new JsonResponse([
                'error' => 'Le corps de la requête doit être un JSON valide.'
            ], 400);
        }

        /**
         * 3. Vérifier les champs obligatoires
         */
        if (empty($data['email']) || empty($data['password']) || empty($data['fullName'])) {
            return new JsonResponse([
                'error' => 'Les champs "email", "password" et "fullName" sont obligatoires.'
            ], 400);
        }

        /**
         * 4. Récupérer le tenant grâce à l'identifiant passé dans l'URL
         */
        $tenant = $tenantRepository->find($tenantId);

        if (!$tenant) {
            return new JsonResponse([
                'error' => sprintf('Aucun tenant trouvé avec l\'id %d.', $tenantId)
            ], 404);
        }

        /**
         * 5. Nettoyer et normaliser les données reçues
         */
        $email = strtolower(trim($data['email']));
        $plainPassword = trim($data['password']);
        $fullName = trim($data['fullName']);

        /**
         * 6. Vérifier si un utilisateur avec cet email existe déjà
         */
        $existingUser = $userRepository->findOneBy(['email' => $email]);

        if ($existingUser !== null) {
            return new JsonResponse([
                'error' => 'Un utilisateur avec cet email existe déjà.'
            ], 409);
        }

        /**
         * 7. Créer le nouvel utilisateur
         */
        $user = new User();
        $user->setTenant($tenant);
        $user->setEmail($email);
        $user->setFullName($fullName);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setIsActive(true);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());

        /**
         * 8. Hasher le mot de passe avant sauvegarde
         */
        $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        /**
         * 9. Sauvegarder l'utilisateur en base
         */
        $em->persist($user);
        $em->flush();

        /**
         * 10. Retourner une réponse JSON propre
         */
        return new JsonResponse([
            'message' => 'Utilisateur admin créé avec succès.',
            'user' => [
                'id' => $user->getId(),
                'tenantId' => $tenant->getId(),
                'tenantName' => $tenant->getName(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'roles' => $user->getRoles(),
                'isActive' => $user->isActive(),
                'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }
}