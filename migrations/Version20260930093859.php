<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930093859 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the economy fields (amount applied, ITK budget, co-financing, funding rate, remaining funding) to projects.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD amount_applied INT DEFAULT NULL, ADD budget_itk INT DEFAULT NULL, ADD co_financing TINYINT NOT NULL, ADD funding_rate VARCHAR(32) DEFAULT NULL, ADD remaining_funding LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP amount_applied, DROP budget_itk, DROP co_financing, DROP funding_rate, DROP remaining_funding');
    }
}
