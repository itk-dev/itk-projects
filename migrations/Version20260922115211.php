<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922115211 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove file upload: drop the project_image and project_attachment tables (uploaded files are not migrated).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_attachment DROP FOREIGN KEY `FK_61F9A289166D1F9C`');
        $this->addSql('ALTER TABLE project_attachment DROP FOREIGN KEY `FK_61F9A28999049ECE`');
        $this->addSql('ALTER TABLE project_attachment DROP FOREIGN KEY `FK_61F9A289B03A8386`');
        $this->addSql('ALTER TABLE project_image DROP FOREIGN KEY `FK_D6680DC1166D1F9C`');
        $this->addSql('ALTER TABLE project_image DROP FOREIGN KEY `FK_D6680DC199049ECE`');
        $this->addSql('ALTER TABLE project_image DROP FOREIGN KEY `FK_D6680DC1B03A8386`');
        $this->addSql('DROP TABLE project_attachment');
        $this->addSql('DROP TABLE project_image');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE project_attachment (id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, file_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, original_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, mime_type VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, size INT DEFAULT NULL, created_by_id BINARY(16) DEFAULT NULL, modified_by_id BINARY(16) DEFAULT NULL, project_id BINARY(16) NOT NULL, INDEX IDX_61F9A28999049ECE (modified_by_id), INDEX IDX_61F9A289166D1F9C (project_id), INDEX IDX_61F9A289B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE project_image (id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, image_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, original_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, mime_type VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, size INT DEFAULT NULL, alt VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, created_by_id BINARY(16) DEFAULT NULL, modified_by_id BINARY(16) DEFAULT NULL, project_id BINARY(16) NOT NULL, INDEX IDX_D6680DC1166D1F9C (project_id), INDEX IDX_D6680DC1B03A8386 (created_by_id), INDEX IDX_D6680DC199049ECE (modified_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE project_attachment ADD CONSTRAINT `FK_61F9A289166D1F9C` FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_attachment ADD CONSTRAINT `FK_61F9A28999049ECE` FOREIGN KEY (modified_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_attachment ADD CONSTRAINT `FK_61F9A289B03A8386` FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_image ADD CONSTRAINT `FK_D6680DC1166D1F9C` FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_image ADD CONSTRAINT `FK_D6680DC199049ECE` FOREIGN KEY (modified_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_image ADD CONSTRAINT `FK_D6680DC1B03A8386` FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }
}
