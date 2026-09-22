<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The "Stakeholders and partners" free-tagging field duplicated Partners.
 */
final class Version20260922121618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the stakeholder vocabulary: drop project_stakeholder and its terms.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_stakeholder DROP FOREIGN KEY `FK_4357D451166D1F9C`');
        $this->addSql('ALTER TABLE project_stakeholder DROP FOREIGN KEY `FK_4357D451E2C35FC`');
        $this->addSql('DROP TABLE project_stakeholder');
        // The Vocabulary enum no longer has a stakeholder case, so leftover rows
        // would fail to hydrate.
        $this->addSql("DELETE FROM term WHERE vocabulary = 'stakeholder'");
    }

    public function down(Schema $schema): void
    {
        // Recreates the empty join table only; the deleted terms are not restored.
        $this->addSql('CREATE TABLE project_stakeholder (project_id BINARY(16) NOT NULL, term_id BINARY(16) NOT NULL, INDEX IDX_4357D451166D1F9C (project_id), INDEX IDX_4357D451E2C35FC (term_id), PRIMARY KEY (project_id, term_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE project_stakeholder ADD CONSTRAINT `FK_4357D451166D1F9C` FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_stakeholder ADD CONSTRAINT `FK_4357D451E2C35FC` FOREIGN KEY (term_id) REFERENCES term (id) ON DELETE CASCADE');
    }
}
