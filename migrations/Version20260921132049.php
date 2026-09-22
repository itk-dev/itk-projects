<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921132049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename initiative to project: create the project tables and drop the initiative tables (no data is carried over).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE project (id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, title VARCHAR(255) NOT NULL, topic LONGTEXT DEFAULT NULL, description LONGTEXT DEFAULT NULL, project_type VARCHAR(32) DEFAULT NULL, status VARCHAR(32) DEFAULT NULL, status_additional LONGTEXT DEFAULT NULL, endorsement TINYINT NOT NULL, endorsement_author VARCHAR(32) DEFAULT NULL, budget INT DEFAULT NULL, funding JSON NOT NULL, time_period_start DATE DEFAULT NULL, time_period_end DATE DEFAULT NULL, links JSON NOT NULL, created_by_id BINARY(16) DEFAULT NULL, modified_by_id BINARY(16) DEFAULT NULL, area_id BINARY(16) DEFAULT NULL, organizational_anchoring_id BINARY(16) DEFAULT NULL, INDEX IDX_2FB3D0EEB03A8386 (created_by_id), INDEX IDX_2FB3D0EE99049ECE (modified_by_id), INDEX IDX_2FB3D0EEBD0F409C (area_id), INDEX IDX_2FB3D0EEA0A5E935 (organizational_anchoring_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_strategy (project_id BINARY(16) NOT NULL, term_id BINARY(16) NOT NULL, INDEX IDX_2908CE36166D1F9C (project_id), INDEX IDX_2908CE36E2C35FC (term_id), PRIMARY KEY (project_id, term_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_contact (project_id BINARY(16) NOT NULL, contact_id BINARY(16) NOT NULL, INDEX IDX_DA7CEA5D166D1F9C (project_id), INDEX IDX_DA7CEA5DE7A1254A (contact_id), PRIMARY KEY (project_id, contact_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_partner (project_id BINARY(16) NOT NULL, partner_id BINARY(16) NOT NULL, INDEX IDX_A7353273166D1F9C (project_id), INDEX IDX_A73532739393F8FE (partner_id), PRIMARY KEY (project_id, partner_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_stakeholder (project_id BINARY(16) NOT NULL, term_id BINARY(16) NOT NULL, INDEX IDX_4357D451166D1F9C (project_id), INDEX IDX_4357D451E2C35FC (term_id), PRIMARY KEY (project_id, term_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_tag (project_id BINARY(16) NOT NULL, term_id BINARY(16) NOT NULL, INDEX IDX_91F26D60166D1F9C (project_id), INDEX IDX_91F26D60E2C35FC (term_id), PRIMARY KEY (project_id, term_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_attachment (id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, file_name VARCHAR(255) DEFAULT NULL, original_name VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(255) DEFAULT NULL, size INT DEFAULT NULL, created_by_id BINARY(16) DEFAULT NULL, modified_by_id BINARY(16) DEFAULT NULL, project_id BINARY(16) NOT NULL, INDEX IDX_61F9A289B03A8386 (created_by_id), INDEX IDX_61F9A28999049ECE (modified_by_id), INDEX IDX_61F9A289166D1F9C (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_image (id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, image_name VARCHAR(255) DEFAULT NULL, original_name VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(255) DEFAULT NULL, size INT DEFAULT NULL, alt VARCHAR(255) DEFAULT NULL, created_by_id BINARY(16) DEFAULT NULL, modified_by_id BINARY(16) DEFAULT NULL, project_id BINARY(16) NOT NULL, INDEX IDX_D6680DC1B03A8386 (created_by_id), INDEX IDX_D6680DC199049ECE (modified_by_id), INDEX IDX_D6680DC1166D1F9C (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE99049ECE FOREIGN KEY (modified_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEBD0F409C FOREIGN KEY (area_id) REFERENCES area (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEA0A5E935 FOREIGN KEY (organizational_anchoring_id) REFERENCES department (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_strategy ADD CONSTRAINT FK_2908CE36166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_strategy ADD CONSTRAINT FK_2908CE36E2C35FC FOREIGN KEY (term_id) REFERENCES term (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_contact ADD CONSTRAINT FK_DA7CEA5D166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_contact ADD CONSTRAINT FK_DA7CEA5DE7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_partner ADD CONSTRAINT FK_A7353273166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_partner ADD CONSTRAINT FK_A73532739393F8FE FOREIGN KEY (partner_id) REFERENCES partner (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_stakeholder ADD CONSTRAINT FK_4357D451166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_stakeholder ADD CONSTRAINT FK_4357D451E2C35FC FOREIGN KEY (term_id) REFERENCES term (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_tag ADD CONSTRAINT FK_91F26D60166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_tag ADD CONSTRAINT FK_91F26D60E2C35FC FOREIGN KEY (term_id) REFERENCES term (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_attachment ADD CONSTRAINT FK_61F9A289B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_attachment ADD CONSTRAINT FK_61F9A28999049ECE FOREIGN KEY (modified_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_attachment ADD CONSTRAINT FK_61F9A289166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_image ADD CONSTRAINT FK_D6680DC1B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_image ADD CONSTRAINT FK_D6680DC199049ECE FOREIGN KEY (modified_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_image ADD CONSTRAINT FK_D6680DC1166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE initiative DROP FOREIGN KEY `FK_E115DEFE99049ECE`');
        $this->addSql('ALTER TABLE initiative DROP FOREIGN KEY `FK_E115DEFEA0A5E935`');
        $this->addSql('ALTER TABLE initiative DROP FOREIGN KEY `FK_E115DEFEB03A8386`');
        $this->addSql('ALTER TABLE initiative DROP FOREIGN KEY `FK_E115DEFEBD0F409C`');
        $this->addSql('ALTER TABLE initiative_attachment DROP FOREIGN KEY `FK_2954F89299049ECE`');
        $this->addSql('ALTER TABLE initiative_attachment DROP FOREIGN KEY `FK_2954F892AB7D9771`');
        $this->addSql('ALTER TABLE initiative_attachment DROP FOREIGN KEY `FK_2954F892B03A8386`');
        $this->addSql('ALTER TABLE initiative_contact DROP FOREIGN KEY `FK_6F980462AB7D9771`');
        $this->addSql('ALTER TABLE initiative_contact DROP FOREIGN KEY `FK_6F980462E7A1254A`');
        $this->addSql('ALTER TABLE initiative_image DROP FOREIGN KEY `FK_99BF4CA899049ECE`');
        $this->addSql('ALTER TABLE initiative_image DROP FOREIGN KEY `FK_99BF4CA8AB7D9771`');
        $this->addSql('ALTER TABLE initiative_image DROP FOREIGN KEY `FK_99BF4CA8B03A8386`');
        $this->addSql('ALTER TABLE initiative_partner DROP FOREIGN KEY `FK_12D1DC4C9393F8FE`');
        $this->addSql('ALTER TABLE initiative_partner DROP FOREIGN KEY `FK_12D1DC4CAB7D9771`');
        $this->addSql('ALTER TABLE initiative_stakeholder DROP FOREIGN KEY `FK_C97AB0E7AB7D9771`');
        $this->addSql('ALTER TABLE initiative_stakeholder DROP FOREIGN KEY `FK_C97AB0E7E2C35FC`');
        $this->addSql('ALTER TABLE initiative_strategy DROP FOREIGN KEY `FK_9FDB07E5AB7D9771`');
        $this->addSql('ALTER TABLE initiative_strategy DROP FOREIGN KEY `FK_9FDB07E5E2C35FC`');
        $this->addSql('ALTER TABLE initiative_tag DROP FOREIGN KEY `FK_4FF4E32DAB7D9771`');
        $this->addSql('ALTER TABLE initiative_tag DROP FOREIGN KEY `FK_4FF4E32DE2C35FC`');
        $this->addSql('DROP TABLE initiative');
        $this->addSql('DROP TABLE initiative_attachment');
        $this->addSql('DROP TABLE initiative_contact');
        $this->addSql('DROP TABLE initiative_image');
        $this->addSql('DROP TABLE initiative_partner');
        $this->addSql('DROP TABLE initiative_stakeholder');
        $this->addSql('DROP TABLE initiative_strategy');
        $this->addSql('DROP TABLE initiative_tag');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE initiative (id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, title VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, initiative_type VARCHAR(32) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, status VARCHAR(32) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, status_additional LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, endorsement TINYINT NOT NULL, endorsement_author VARCHAR(32) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, budget INT DEFAULT NULL, funding JSON NOT NULL, time_period_start DATE DEFAULT NULL, time_period_end DATE DEFAULT NULL, links JSON NOT NULL, created_by_id BINARY(16) DEFAULT NULL, modified_by_id BINARY(16) DEFAULT NULL, organizational_anchoring_id BINARY(16) DEFAULT NULL, area_id BINARY(16) DEFAULT NULL, topic LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX IDX_E115DEFEA0A5E935 (organizational_anchoring_id), INDEX IDX_E115DEFEBD0F409C (area_id), INDEX IDX_E115DEFEB03A8386 (created_by_id), INDEX IDX_E115DEFE99049ECE (modified_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE initiative_attachment (id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, file_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, original_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, mime_type VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, size INT DEFAULT NULL, created_by_id BINARY(16) DEFAULT NULL, modified_by_id BINARY(16) DEFAULT NULL, initiative_id BINARY(16) NOT NULL, INDEX IDX_2954F892B03A8386 (created_by_id), INDEX IDX_2954F89299049ECE (modified_by_id), INDEX IDX_2954F892AB7D9771 (initiative_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE initiative_contact (initiative_id BINARY(16) NOT NULL, contact_id BINARY(16) NOT NULL, INDEX IDX_6F980462AB7D9771 (initiative_id), INDEX IDX_6F980462E7A1254A (contact_id), PRIMARY KEY (initiative_id, contact_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE initiative_image (id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, image_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, original_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, mime_type VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, size INT DEFAULT NULL, alt VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, created_by_id BINARY(16) DEFAULT NULL, modified_by_id BINARY(16) DEFAULT NULL, initiative_id BINARY(16) NOT NULL, INDEX IDX_99BF4CA899049ECE (modified_by_id), INDEX IDX_99BF4CA8AB7D9771 (initiative_id), INDEX IDX_99BF4CA8B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE initiative_partner (initiative_id BINARY(16) NOT NULL, partner_id BINARY(16) NOT NULL, INDEX IDX_12D1DC4CAB7D9771 (initiative_id), INDEX IDX_12D1DC4C9393F8FE (partner_id), PRIMARY KEY (initiative_id, partner_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE initiative_stakeholder (initiative_id BINARY(16) NOT NULL, term_id BINARY(16) NOT NULL, INDEX IDX_C97AB0E7AB7D9771 (initiative_id), INDEX IDX_C97AB0E7E2C35FC (term_id), PRIMARY KEY (initiative_id, term_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE initiative_strategy (initiative_id BINARY(16) NOT NULL, term_id BINARY(16) NOT NULL, INDEX IDX_9FDB07E5AB7D9771 (initiative_id), INDEX IDX_9FDB07E5E2C35FC (term_id), PRIMARY KEY (initiative_id, term_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE initiative_tag (initiative_id BINARY(16) NOT NULL, term_id BINARY(16) NOT NULL, INDEX IDX_4FF4E32DAB7D9771 (initiative_id), INDEX IDX_4FF4E32DE2C35FC (term_id), PRIMARY KEY (initiative_id, term_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE initiative ADD CONSTRAINT `FK_E115DEFE99049ECE` FOREIGN KEY (modified_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE initiative ADD CONSTRAINT `FK_E115DEFEA0A5E935` FOREIGN KEY (organizational_anchoring_id) REFERENCES department (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE initiative ADD CONSTRAINT `FK_E115DEFEB03A8386` FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE initiative ADD CONSTRAINT `FK_E115DEFEBD0F409C` FOREIGN KEY (area_id) REFERENCES area (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE initiative_attachment ADD CONSTRAINT `FK_2954F89299049ECE` FOREIGN KEY (modified_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE initiative_attachment ADD CONSTRAINT `FK_2954F892AB7D9771` FOREIGN KEY (initiative_id) REFERENCES initiative (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE initiative_attachment ADD CONSTRAINT `FK_2954F892B03A8386` FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE initiative_contact ADD CONSTRAINT `FK_6F980462AB7D9771` FOREIGN KEY (initiative_id) REFERENCES initiative (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE initiative_contact ADD CONSTRAINT `FK_6F980462E7A1254A` FOREIGN KEY (contact_id) REFERENCES contact (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE initiative_image ADD CONSTRAINT `FK_99BF4CA899049ECE` FOREIGN KEY (modified_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE initiative_image ADD CONSTRAINT `FK_99BF4CA8AB7D9771` FOREIGN KEY (initiative_id) REFERENCES initiative (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE initiative_image ADD CONSTRAINT `FK_99BF4CA8B03A8386` FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE initiative_partner ADD CONSTRAINT `FK_12D1DC4C9393F8FE` FOREIGN KEY (partner_id) REFERENCES partner (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE initiative_partner ADD CONSTRAINT `FK_12D1DC4CAB7D9771` FOREIGN KEY (initiative_id) REFERENCES initiative (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE initiative_stakeholder ADD CONSTRAINT `FK_C97AB0E7AB7D9771` FOREIGN KEY (initiative_id) REFERENCES initiative (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE initiative_stakeholder ADD CONSTRAINT `FK_C97AB0E7E2C35FC` FOREIGN KEY (term_id) REFERENCES term (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE initiative_strategy ADD CONSTRAINT `FK_9FDB07E5AB7D9771` FOREIGN KEY (initiative_id) REFERENCES initiative (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE initiative_strategy ADD CONSTRAINT `FK_9FDB07E5E2C35FC` FOREIGN KEY (term_id) REFERENCES term (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE initiative_tag ADD CONSTRAINT `FK_4FF4E32DAB7D9771` FOREIGN KEY (initiative_id) REFERENCES initiative (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE initiative_tag ADD CONSTRAINT `FK_4FF4E32DE2C35FC` FOREIGN KEY (term_id) REFERENCES term (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EEB03A8386');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE99049ECE');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EEBD0F409C');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EEA0A5E935');
        $this->addSql('ALTER TABLE project_strategy DROP FOREIGN KEY FK_2908CE36166D1F9C');
        $this->addSql('ALTER TABLE project_strategy DROP FOREIGN KEY FK_2908CE36E2C35FC');
        $this->addSql('ALTER TABLE project_contact DROP FOREIGN KEY FK_DA7CEA5D166D1F9C');
        $this->addSql('ALTER TABLE project_contact DROP FOREIGN KEY FK_DA7CEA5DE7A1254A');
        $this->addSql('ALTER TABLE project_partner DROP FOREIGN KEY FK_A7353273166D1F9C');
        $this->addSql('ALTER TABLE project_partner DROP FOREIGN KEY FK_A73532739393F8FE');
        $this->addSql('ALTER TABLE project_stakeholder DROP FOREIGN KEY FK_4357D451166D1F9C');
        $this->addSql('ALTER TABLE project_stakeholder DROP FOREIGN KEY FK_4357D451E2C35FC');
        $this->addSql('ALTER TABLE project_tag DROP FOREIGN KEY FK_91F26D60166D1F9C');
        $this->addSql('ALTER TABLE project_tag DROP FOREIGN KEY FK_91F26D60E2C35FC');
        $this->addSql('ALTER TABLE project_attachment DROP FOREIGN KEY FK_61F9A289B03A8386');
        $this->addSql('ALTER TABLE project_attachment DROP FOREIGN KEY FK_61F9A28999049ECE');
        $this->addSql('ALTER TABLE project_attachment DROP FOREIGN KEY FK_61F9A289166D1F9C');
        $this->addSql('ALTER TABLE project_image DROP FOREIGN KEY FK_D6680DC1B03A8386');
        $this->addSql('ALTER TABLE project_image DROP FOREIGN KEY FK_D6680DC199049ECE');
        $this->addSql('ALTER TABLE project_image DROP FOREIGN KEY FK_D6680DC1166D1F9C');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE project_strategy');
        $this->addSql('DROP TABLE project_contact');
        $this->addSql('DROP TABLE project_partner');
        $this->addSql('DROP TABLE project_stakeholder');
        $this->addSql('DROP TABLE project_tag');
        $this->addSql('DROP TABLE project_attachment');
        $this->addSql('DROP TABLE project_image');
    }
}
