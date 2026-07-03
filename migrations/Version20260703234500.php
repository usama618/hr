<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703234500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user notes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notes (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, title VARCHAR(160) NOT NULL, body LONGTEXT NOT NULL, notebook VARCHAR(80) DEFAULT NULL, is_pinned TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_11BA68C97E3C61F9 (owner_id), INDEX IDX_11BA68C9EC21163F (is_pinned), INDEX IDX_11BA68C98CDEE217 (updated_at), INDEX IDX_11BA68C9C43F392C (notebook), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notes ADD CONSTRAINT FK_11BA68C97E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notes DROP FOREIGN KEY FK_11BA68C97E3C61F9');
        $this->addSql('DROP TABLE notes');
    }
}
