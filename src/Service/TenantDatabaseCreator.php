<?php

namespace App\Service;

use App\Entity\Tenant;
use Doctrine\DBAL\Connection;
use Symfony\Component\Process\Process;

class TenantDatabaseCreator
{
    /**
     * On injecte la connexion Doctrine vers la base maître.
     * Cette connexion sert à exécuter du SQL brut comme CREATE DATABASE.
     */
    public function __construct(private Connection $masterConnection)
    {
    }

    /**
     * Crée la base PostgreSQL d’un tenant puis retourne son nom.
     *
     * Étapes :
     * 1. Générer un nom unique de base
     * 2. Exécuter CREATE DATABASE
     * 3. Retourner le nom de base généré
     *
     * On garde cette version volontairement simple pour le debug.
     */
    public function createDatabaseForTenant(Tenant $tenant): string
    {
        /**
         * On génère un nom unique.
         * Exemple : tenant_a1b2c3d4
         */
        $dbName = 'tenant_' . bin2hex(random_bytes(4));

        /**
         * CREATE DATABASE ne prend pas de paramètres bindés classiques
         * pour le nom de base, donc on construit ici une chaîne sûre
         * à partir d’une valeur générée par notre application.
         */
        $sql = sprintf('CREATE DATABASE "%s"', $dbName);

        /**
         * Exécute la requête SQL sur la connexion maître.
         */
        $this->masterConnection->executeStatement($sql);

        /**
         * Pour l’instant, on ne lance PAS les migrations automatiquement.
         * On pourra le rajouter ensuite quand la stratégie multi-tenant
         * Doctrine sera bien en place.
         */
        return $dbName;
    }

    /**
     * Méthode optionnelle pour plus tard :
     * elle montre comment on pourrait lancer une commande Symfony
     * dans un sous-processus.
     *
     * Pour l’instant, on ne l’appelle pas dans le flux principal.
     */
    public function runCommandExample(): void
    {
        $process = new Process([
            'php',
            'bin/console',
            'about'
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }
    }
}