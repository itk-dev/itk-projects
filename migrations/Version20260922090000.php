<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make a project\'s department a many-to-many relation: add the project_department join table and drop project.organizational_anchoring_id (no data is carried over).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE project_department (project_id BINARY(16) NOT NULL, department_id BINARY(16) NOT NULL, INDEX IDX_D5BB9AB8166D1F9C (project_id), INDEX IDX_D5BB9AB8AE80F5DF (department_id), PRIMARY KEY (project_id, department_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE project_department ADD CONSTRAINT FK_D5BB9AB8166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_department ADD CONSTRAINT FK_D5BB9AB8AE80F5DF FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY `FK_2FB3D0EEA0A5E935`');
        $this->addSql('DROP INDEX IDX_2FB3D0EEA0A5E935 ON project');
        $this->addSql('ALTER TABLE project DROP organizational_anchoring_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD organizational_anchoring_id BINARY(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEA0A5E935 FOREIGN KEY (organizational_anchoring_id) REFERENCES department (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEA0A5E935 ON project (organizational_anchoring_id)');
        $this->addSql('ALTER TABLE project_department DROP FOREIGN KEY FK_D5BB9AB8166D1F9C');
        $this->addSql('ALTER TABLE project_department DROP FOREIGN KEY FK_D5BB9AB8AE80F5DF');
        $this->addSql('DROP TABLE project_department');
    }
}
