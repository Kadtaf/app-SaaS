<?php

namespace App\Controller;

use App\Entity\Tenant;
use App\Service\TenantDatabaseCreator;
use App\Service\TenantSlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;


class TenantController extends AbstractController
{
    /**
     * Endpoint POST /api/tenants
     *
     * Rôle :
     * - recevoir les données JSON envoyées par Postman ou le frontend
     * - valider les champs obligatoires
     * - créer un objet Tenant
     * - créer une base PostgreSQL dédiée au tenant
     * - sauvegarder le tenant dans la base maître
     * - retourner une réponse JSON lisible
     */
    #[Route('/api/tenants', name: 'create_tenant', methods: ['POST'])]
    public function createTenant(
        Request $request,
        EntityManagerInterface $em,
        TenantDatabaseCreator $dbCreator,
        TenantSlugGenerator $slugGenerator,
    ): JsonResponse {
        /**
         * 1. Récupérer le contenu brut de la requête HTTP
         * puis le convertir en tableau PHP.
         *
         * Exemple JSON envoyé :
         * {
         *   "name": "Restaurant Atlas",
         *   "plan": "basic"
         * }
         */
        $data = json_decode($request->getContent(), true);

        /**
         * 2. Vérifier que le JSON est valide.
         * Si json_decode échoue, $data peut être null.
         */
        if (!is_array($data)) {
            return new JsonResponse([
                'error' => 'Le corps de la requête doit être un JSON valide.'
            ], 400);
        }

        /**
         * 3. Vérifier les champs obligatoires.
         */
        if (empty($data['name']) || empty($data['plan'])) {
            return new JsonResponse([
                'error' => 'Les champs "name" et "plan" sont obligatoires.'
            ], 400);
        }

        /**
         * 4. Créer l’objet Tenant en mémoire.
         * À ce stade, rien n’est encore enregistré en base.
         */
        $tenant = new Tenant();

        /**
         * On nettoie le nom reçu pour éviter les espaces inutiles.
         */
        $name = trim($data['name']);
        $plan = trim($data['plan']);

        /**
         * Création d’un slug simple à partir du nom.
         * Exemple : "Restaurant Atlas" => "restaurant-atlas"
         *
         * Plus tard, on pourra utiliser le composant String de Symfony
         * pour gérer les accents et cas plus complexes.
         */
        $slug = $slugGenerator->generateUniqueSlug($name);

        $tenant->setName($name);
        $tenant->setSlug($slug);
        $tenant->setPlan($plan);
        $tenant->setCreatedAt(new \DateTimeImmutable());
        $tenant->setUpdatedAt(new \DateTimeImmutable());

        try {
            /**
             * 5. Créer la base dédiée au tenant.
             * Le service retourne le nom de base généré.
             */
            $dbName = $dbCreator->createDatabaseForTenant($tenant);

            /**
             * 6. Enregistrer ce nom de base dans l’entité Tenant.
             */
            $tenant->setDbName($dbName);

            /**
             * 7. Sauvegarder le tenant dans la base maître.
             * Ici seulement, car maintenant toutes les données utiles sont prêtes.
             */
            $em->persist($tenant);
            $em->flush();

            /**
             * 8. Retourner une réponse JSON propre.
             */
            return new JsonResponse([
                'message' => 'Tenant créé avec succès.',
                'tenant' => [
                    'id' => $tenant->getId(),
                    'name' => $tenant->getName(),
                    'slug' => $tenant->getSlug(),
                    'plan' => $tenant->getPlan(),
                    'dbName' => $tenant->getDbName(),
                    'createdAt' => $tenant->getCreatedAt()?->format('Y-m-d H:i:s'),
                ]
            ], 201);
        } catch (\Throwable $e) {
            /**
             * En cas d’erreur, on retourne le message pour le debug en dev.
             * En production, il faudra éviter d’exposer les détails internes.
             */
            return new JsonResponse([
                'error' => 'Erreur lors de la création du tenant.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}