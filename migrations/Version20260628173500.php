<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628173500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add employee leave requests.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE leave_requests (id INT AUTO_INCREMENT NOT NULL, employee_id INT NOT NULL, leave_type VARCHAR(40) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, reason LONGTEXT DEFAULT NULL, status VARCHAR(32) NOT NULL, reviewed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_9F5BAE1E8C03F15C (employee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE leave_requests ADD CONSTRAINT FK_9F5BAE1E8C03F15C FOREIGN KEY (employee_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leave_requests DROP FOREIGN KEY FK_9F5BAE1E8C03F15C');
        $this->addSql('DROP TABLE leave_requests');
    }
}
