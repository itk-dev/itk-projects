<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930065548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace the project_type enum column with the admin-managed project_character entity and a many-to-many join table (no data is carried over).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE project_project_character (project_id BINARY(16) NOT NULL, project_character_id BINARY(16) NOT NULL, INDEX IDX_B695A9DC166D1F9C (project_id), INDEX IDX_B695A9DC3B915A16 (project_character_id), PRIMARY KEY (project_id, project_character_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_character (id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, name VARCHAR(255) NOT NULL, created_by_id BINARY(16) DEFAULT NULL, modified_by_id BINARY(16) DEFAULT NULL, UNIQUE INDEX UNIQ_8246F5E35E237E06 (name), INDEX IDX_8246F5E3B03A8386 (created_by_id), INDEX IDX_8246F5E399049ECE (modified_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE project_project_character ADD CONSTRAINT FK_B695A9DC166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_project_character ADD CONSTRAINT FK_B695A9DC3B915A16 FOREIGN KEY (project_character_id) REFERENCES project_character (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_character ADD CONSTRAINT FK_8246F5E3B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_character ADD CONSTRAINT FK_8246F5E399049ECE FOREIGN KEY (modified_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project DROP project_type');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_project_character DROP FOREIGN KEY FK_B695A9DC166D1F9C');
        $this->addSql('ALTER TABLE project_project_character DROP FOREIGN KEY FK_B695A9DC3B915A16');
        $this->addSql('ALTER TABLE project_character DROP FOREIGN KEY FK_8246F5E3B03A8386');
        $this->addSql('ALTER TABLE project_character DROP FOREIGN KEY FK_8246F5E399049ECE');
        $this->addSql('DROP TABLE project_project_character');
        $this->addSql('DROP TABLE project_character');
        $this->addSql('ALTER TABLE project ADD project_type VARCHAR(32) DEFAULT NULL');
    }
}
