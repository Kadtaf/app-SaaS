<?php

namespace App\Service;

use App\Repository\TenantRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

class TenantSlugGenerator
{
    /**
     * Ce service génère un slug unique pour un Tenant
     * à partir de son nom, en vérifiant en base
     * s'il existe déjà des slugs identiques.
     */
    public function __construct(
        private SluggerInterface $slugger,
        private TenantRepository $tenantRepository,
    ) {
    }

    /**
     * Génère un slug unique pour un nom de tenant donné.
     *
     * Exemple :
     *  - "Boulangerie Dupon"        => "boulangerie-dupon"
     *  - si déjà pris, alors        => "boulangerie-dupon-2"
     *  - si encore pris, alors      => "boulangerie-dupon-3", etc.
     */
    public function generateUniqueSlug(string $name): string
    {
        // 1) On génère d'abord un slug "de base" à partir du nom
        $baseSlug = strtolower($this->slugger->slug($name)->toString());

        // 2) On part du slug de base comme première tentative
        $slug = $baseSlug;
        $suffix = 1;

        // 3) Tant qu'un tenant existe déjà avec ce slug,
        //    on ajoute un suffixe numérique : "-2", "-3", ...
        while ($this->tenantRepository->findOneBy(['slug' => $slug]) !== null) {
            $suffix++;
            $slug = $baseSlug . '-' . $suffix;
        }

        // 4) Quand on sort de la boucle, slug est unique
        return $slug;
    }
}