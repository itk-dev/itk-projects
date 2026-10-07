<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The Summary ("Opsummering") field is gone; the Description is the one free
 * text describing a project.
 */
final class Version20261007080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the project summary column.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP summary');
    }

    public function down(Schema $schema): void
    {
        // Recreates the empty column only; the dropped texts are not restored.
        $this->addSql('ALTER TABLE project ADD summary LONGTEXT DEFAULT NULL');
    }
}
