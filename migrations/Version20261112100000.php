<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Projects that are funding applications have a date the application must be
 * in by. Optional, since not every project applies for anything.
 */
final class Version20261112100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the optional application deadline to projects.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD application_deadline DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP application_deadline');
    }
}
