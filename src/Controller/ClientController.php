<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\User;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ClientController extends AbstractController
{
    /**
     * Liste tous les clients du tenant de l'utilisateur connecté.
     *
     * Route :
     * GET /api/clients
     */
    #[Route('/api/clients', name: 'list_clients', methods: ['GET'])]
    public function listClients(ClientRepository $clientRepository): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'Utilisateur non authentifié.'
            ], 401);
        }

        $tenant = $user->getTenant();

        $clients = $clientRepository->findBy(
            ['tenant' => $tenant],
            ['id' => 'DESC']
        );

        $data = [];

        foreach ($clients as $client) {
            $data[] = [
                'id' => $client->getId(),
                'name' => $client->getName(),
                'email' => $client->getEmail(),
                'phone' => $client->getPhone(),
                'address' => $client->getAddress(),
                'createdAt' => $client->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse($data, 200);
    }

    /**
     * Crée un nouveau client pour le tenant de l'utilisateur connecté.
     *
     * Route :
     * POST /api/clients
     */
    #[Route('/api/clients', name: 'create_client', methods: ['POST'])]
    public function createClient(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'Utilisateur non authentifié.'
            ], 401);
        }

        $tenant = $user->getTenant();

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse([
                'error' => 'Le corps de la requête doit être un JSON valide.'
            ], 400);
        }

        if (empty($data['name'])) {
            return new JsonResponse([
                'error' => 'Le champ "name" est obligatoire.'
            ], 400);
        }

        $client = new Client();
        $client->setTenant($tenant);
        $client->setName($data['name']);
        $client->setEmail($data['email'] ?? null);
        $client->setPhone($data['phone'] ?? null);
        $client->setAddress($data['address'] ?? null);
        $client->setCreatedAt(new \DateTimeImmutable());
        $client->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($client);
        $em->flush();

        return new JsonResponse([
            'message' => 'Client créé avec succès.',
            'client' => [
                'id' => $client->getId(),
                'name' => $client->getName(),
                'email' => $client->getEmail(),
                'phone' => $client->getPhone(),
                'address' => $client->getAddress(),
                'tenantId' => $tenant?->getId(),
                'createdAt' => $client->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }
}