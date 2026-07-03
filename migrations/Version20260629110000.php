<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260629110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user notifications.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notifications (id INT AUTO_INCREMENT NOT NULL, recipient_id INT NOT NULL, actor_id INT DEFAULT NULL, task_id INT DEFAULT NULL, type VARCHAR(80) NOT NULL, title VARCHAR(180) NOT NULL, body LONGTEXT NOT NULL, url VARCHAR(255) NOT NULL, read_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_6000B0D6E92F8F78 (recipient_id), INDEX IDX_6000B0D610DAF24A (actor_id), INDEX IDX_6000B0D68DB60186 (task_id), INDEX IDX_6000B0D6B393D2FB (read_at), INDEX IDX_6000B0D6B03A8386 (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D6E92F8F78 FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D610DAF24A FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D68DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D6E92F8F78');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D610DAF24A');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D68DB60186');
        $this->addSql('DROP TABLE notifications');
    }
}
