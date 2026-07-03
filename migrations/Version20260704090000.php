<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260704090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add employee document center.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE employee_documents (id INT AUTO_INCREMENT NOT NULL, owner_id INT DEFAULT NULL, uploaded_by_id INT DEFAULT NULL, title VARCHAR(180) NOT NULL, category VARCHAR(40) NOT NULL, original_filename VARCHAR(255) NOT NULL, stored_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, file_size INT NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_5E30396C7E3C61F9 (owner_id), INDEX IDX_5E30396C95FB417D (uploaded_by_id), INDEX IDX_5E30396C12469DE2 (category), INDEX IDX_5E30396CB03A8386 (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE employee_documents ADD CONSTRAINT FK_5E30396C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE employee_documents ADD CONSTRAINT FK_5E30396C95FB417D FOREIGN KEY (uploaded_by_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE employee_documents DROP FOREIGN KEY FK_5E30396C7E3C61F9');
        $this->addSql('ALTER TABLE employee_documents DROP FOREIGN KEY FK_5E30396C95FB417D');
        $this->addSql('DROP TABLE employee_documents');
    }
}
