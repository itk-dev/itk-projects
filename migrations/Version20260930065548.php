<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930065548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace the project_type enum column with the admin-managed project_type entity and a many-to-many join table (no data is carried over).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE project_type (id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, name VARCHAR(255) NOT NULL, created_by_id BINARY(16) DEFAULT NULL, modified_by_id BINARY(16) DEFAULT NULL, UNIQUE INDEX UNIQ_B54F9F315E237E06 (name), INDEX IDX_B54F9F31B03A8386 (created_by_id), INDEX IDX_B54F9F3199049ECE (modified_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_project_type (project_id BINARY(16) NOT NULL, project_type_id BINARY(16) NOT NULL, INDEX IDX_9FE87B52166D1F9C (project_id), INDEX IDX_9FE87B52535280F6 (project_type_id), PRIMARY KEY (project_id, project_type_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE project_type ADD CONSTRAINT FK_B54F9F31B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_type ADD CONSTRAINT FK_B54F9F3199049ECE FOREIGN KEY (modified_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_project_type ADD CONSTRAINT FK_9FE87B52166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_project_type ADD CONSTRAINT FK_9FE87B52535280F6 FOREIGN KEY (project_type_id) REFERENCES project_type (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project DROP project_type');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_project_type DROP FOREIGN KEY FK_9FE87B52166D1F9C');
        $this->addSql('ALTER TABLE project_project_type DROP FOREIGN KEY FK_9FE87B52535280F6');
        $this->addSql('ALTER TABLE project_type DROP FOREIGN KEY FK_B54F9F31B03A8386');
        $this->addSql('ALTER TABLE project_type DROP FOREIGN KEY FK_B54F9F3199049ECE');
        $this->addSql('DROP TABLE project_project_type');
        $this->addSql('DROP TABLE project_type');
        $this->addSql('ALTER TABLE project ADD project_type VARCHAR(32) DEFAULT NULL');
    }
}
