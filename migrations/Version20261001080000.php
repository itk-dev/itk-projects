<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The "Strategies and plans" free-tagging field is gone; strategy and plans
 * are now part of what the Summary asks for.
 */
final class Version20261001080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the strategy vocabulary: drop project_strategy and its terms.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_strategy DROP FOREIGN KEY `FK_2908CE36166D1F9C`');
        $this->addSql('ALTER TABLE project_strategy DROP FOREIGN KEY `FK_2908CE36E2C35FC`');
        $this->addSql('DROP TABLE project_strategy');
        // The Vocabulary enum no longer has a strategy case, so leftover rows
        // would fail to hydrate.
        $this->addSql("DELETE FROM term WHERE vocabulary = 'strategy'");
    }

    public function down(Schema $schema): void
    {
        // Recreates the empty join table only; the deleted terms are not restored.
        $this->addSql('CREATE TABLE project_strategy (project_id BINARY(16) NOT NULL, term_id BINARY(16) NOT NULL, INDEX IDX_2908CE36166D1F9C (project_id), INDEX IDX_2908CE36E2C35FC (term_id), PRIMARY KEY (project_id, term_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE project_strategy ADD CONSTRAINT `FK_2908CE36166D1F9C` FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_strategy ADD CONSTRAINT `FK_2908CE36E2C35FC` FOREIGN KEY (term_id) REFERENCES term (id) ON DELETE CASCADE');
    }
}
