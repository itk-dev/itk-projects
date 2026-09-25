<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925081451 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename the project description column to summary (keeping its data) and add a new, longer description column.';
    }

    public function up(Schema $schema): void
    {
        // Rename first so the existing short texts end up under "summary"; a
        // generated diff would drop and re-add the column and lose them.
        $this->addSql('ALTER TABLE project CHANGE description summary LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP description');
        $this->addSql('ALTER TABLE project CHANGE summary description LONGTEXT DEFAULT NULL');
    }
}
