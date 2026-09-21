<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260921091028 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add relation between contact and department.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact ADD department_id BINARY(16) DEFAULT NULL, DROP department');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E638AE80F5DF FOREIGN KEY (department_id) REFERENCES department (id)');
        $this->addSql('CREATE INDEX IDX_4C62E638AE80F5DF ON contact (department_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_4C62E638AE80F5DF');
        $this->addSql('DROP INDEX IDX_4C62E638AE80F5DF ON contact');
        $this->addSql('ALTER TABLE contact ADD department VARCHAR(255) DEFAULT NULL, DROP department_id');
    }
}
