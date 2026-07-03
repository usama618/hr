<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260629100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add employee-created tasks and task comments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tasks ADD created_by_id INT DEFAULT NULL, ADD INDEX IDX_50586597B03A8386 (created_by_id)');
        $this->addSql('CREATE TABLE task_comments (id INT AUTO_INCREMENT NOT NULL, task_id INT NOT NULL, author_id INT DEFAULT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_6FB35B6D8DB60186 (task_id), INDEX IDX_6FB35B6DF675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT FK_50586597B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE task_comments ADD CONSTRAINT FK_6FB35B6D8DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_comments ADD CONSTRAINT FK_6FB35B6DF675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_comments DROP FOREIGN KEY FK_6FB35B6D8DB60186');
        $this->addSql('ALTER TABLE task_comments DROP FOREIGN KEY FK_6FB35B6DF675F31B');
        $this->addSql('ALTER TABLE tasks DROP FOREIGN KEY FK_50586597B03A8386');
        $this->addSql('DROP TABLE task_comments');
        $this->addSql('ALTER TABLE tasks DROP created_by_id');
    }
}
