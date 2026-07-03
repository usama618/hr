<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create core HR tables for users, projects, tasks, attendance, breaks, and task timers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, full_name VARCHAR(120) NOT NULL, role VARCHAR(32) NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_users_email (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE projects (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(160) NOT NULL, client_name VARCHAR(160) DEFAULT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(32) NOT NULL, start_date DATE DEFAULT NULL, deadline DATE DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE project_members (project_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_1B759BE5166D1F9C (project_id), INDEX IDX_1B759BE5A76ED395 (user_id), PRIMARY KEY(project_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tasks (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, assigned_to_id INT DEFAULT NULL, title VARCHAR(180) NOT NULL, description LONGTEXT DEFAULT NULL, priority VARCHAR(32) NOT NULL, status VARCHAR(32) NOT NULL, estimated_minutes INT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_50586597166D1F9C (project_id), INDEX IDX_50586597F4BD7827 (assigned_to_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE attendance_entries (id INT AUTO_INCREMENT NOT NULL, employee_id INT NOT NULL, check_in_at DATETIME NOT NULL, check_out_at DATETIME DEFAULT NULL, INDEX IDX_44EF1AFE8C03F15C (employee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE break_entries (id INT AUTO_INCREMENT NOT NULL, attendance_id INT NOT NULL, employee_id INT NOT NULL, started_at DATETIME NOT NULL, ended_at DATETIME DEFAULT NULL, INDEX IDX_3F69E3558308A1C (attendance_id), INDEX IDX_3F69E3558C03F15C (employee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE task_time_entries (id INT AUTO_INCREMENT NOT NULL, task_id INT NOT NULL, employee_id INT NOT NULL, started_at DATETIME NOT NULL, ended_at DATETIME DEFAULT NULL, note LONGTEXT DEFAULT NULL, INDEX IDX_4CA1BC618DB60186 (task_id), INDEX IDX_4CA1BC618C03F15C (employee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE project_members ADD CONSTRAINT FK_1B759BE5166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_members ADD CONSTRAINT FK_1B759BE5A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT FK_50586597166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT FK_50586597F4BD7827 FOREIGN KEY (assigned_to_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE attendance_entries ADD CONSTRAINT FK_44EF1AFE8C03F15C FOREIGN KEY (employee_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE break_entries ADD CONSTRAINT FK_3F69E3558308A1C FOREIGN KEY (attendance_id) REFERENCES attendance_entries (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE break_entries ADD CONSTRAINT FK_3F69E3558C03F15C FOREIGN KEY (employee_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_time_entries ADD CONSTRAINT FK_4CA1BC618DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_time_entries ADD CONSTRAINT FK_4CA1BC618C03F15C FOREIGN KEY (employee_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_members DROP FOREIGN KEY FK_1B759BE5166D1F9C');
        $this->addSql('ALTER TABLE project_members DROP FOREIGN KEY FK_1B759BE5A76ED395');
        $this->addSql('ALTER TABLE tasks DROP FOREIGN KEY FK_50586597166D1F9C');
        $this->addSql('ALTER TABLE tasks DROP FOREIGN KEY FK_50586597F4BD7827');
        $this->addSql('ALTER TABLE attendance_entries DROP FOREIGN KEY FK_44EF1AFE8C03F15C');
        $this->addSql('ALTER TABLE break_entries DROP FOREIGN KEY FK_3F69E3558308A1C');
        $this->addSql('ALTER TABLE break_entries DROP FOREIGN KEY FK_3F69E3558C03F15C');
        $this->addSql('ALTER TABLE task_time_entries DROP FOREIGN KEY FK_4CA1BC618DB60186');
        $this->addSql('ALTER TABLE task_time_entries DROP FOREIGN KEY FK_4CA1BC618C03F15C');

        $this->addSql('DROP TABLE task_time_entries');
        $this->addSql('DROP TABLE break_entries');
        $this->addSql('DROP TABLE attendance_entries');
        $this->addSql('DROP TABLE tasks');
        $this->addSql('DROP TABLE project_members');
        $this->addSql('DROP TABLE projects');
        $this->addSql('DROP TABLE users');
    }
}
