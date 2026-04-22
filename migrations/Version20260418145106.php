<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418145106 extends AbstractMigration
{
    public function getDescription(): string
{
    return 'Ajoute paid_amount et remaining_amount à invoice, backfill les anciennes lignes, puis impose NOT NULL';
}

public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE invoice ADD paid_amount DOUBLE PRECISION DEFAULT NULL');
    $this->addSql('ALTER TABLE invoice ADD remaining_amount DOUBLE PRECISION DEFAULT NULL');

    $this->addSql('UPDATE invoice SET paid_amount = 0 WHERE paid_amount IS NULL');
    $this->addSql('UPDATE invoice SET remaining_amount = COALESCE(total, 0) WHERE remaining_amount IS NULL');

    $this->addSql('ALTER TABLE invoice ALTER COLUMN paid_amount SET NOT NULL');
    $this->addSql('ALTER TABLE invoice ALTER COLUMN remaining_amount SET NOT NULL');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE invoice DROP paid_amount');
    $this->addSql('ALTER TABLE invoice DROP remaining_amount');
}
}
