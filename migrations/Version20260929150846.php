<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929150846 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make a project\'s area a many-to-many relation: add the project_area join table, copy each project\'s area into it and drop project.area_id.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE project_area (project_id BINARY(16) NOT NULL, area_id BINARY(16) NOT NULL, INDEX IDX_EE05F570166D1F9C (project_id), INDEX IDX_EE05F570BD0F409C (area_id), PRIMARY KEY (project_id, area_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE project_area ADD CONSTRAINT FK_EE05F570166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_area ADD CONSTRAINT FK_EE05F570BD0F409C FOREIGN KEY (area_id) REFERENCES area (id) ON DELETE CASCADE');
        // Carry the existing single area over before the column goes.
        $this->addSql('INSERT INTO project_area (project_id, area_id) SELECT id, area_id FROM project WHERE area_id IS NOT NULL');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY `FK_2FB3D0EEBD0F409C`');
        $this->addSql('DROP INDEX IDX_2FB3D0EEBD0F409C ON project');
        $this->addSql('ALTER TABLE project DROP area_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD area_id BINARY(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT `FK_2FB3D0EEBD0F409C` FOREIGN KEY (area_id) REFERENCES area (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEBD0F409C ON project (area_id)');
        // A project with several areas keeps an arbitrary one of them.
        $this->addSql('UPDATE project p JOIN project_area pa ON pa.project_id = p.id SET p.area_id = pa.area_id');
        $this->addSql('ALTER TABLE project_area DROP FOREIGN KEY FK_EE05F570166D1F9C');
        $this->addSql('ALTER TABLE project_area DROP FOREIGN KEY FK_EE05F570BD0F409C');
        $this->addSql('DROP TABLE project_area');
    }
}
