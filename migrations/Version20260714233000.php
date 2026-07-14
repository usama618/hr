<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714233000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add hierarchical rich tasks and multiple assignees.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tasks ADD parent_id INT DEFAULT NULL, ADD source_occurrence_id INT DEFAULT NULL, ADD start_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', ADD due_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', ADD tags JSON DEFAULT NULL, ADD reminder_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD recurrence VARCHAR(16) DEFAULT NULL, ADD billing_type VARCHAR(32) NOT NULL DEFAULT \'billable\', ADD manager_note LONGTEXT DEFAULT NULL');
        $this->addSql('UPDATE tasks SET tags = JSON_ARRAY() WHERE tags IS NULL');
        $this->addSql('ALTER TABLE tasks MODIFY tags JSON NOT NULL');
        $this->addSql('CREATE INDEX IDX_50586597727ACA70 ON tasks (parent_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5058659756D27D5D ON tasks (source_occurrence_id)');
        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT FK_50586597727ACA70 FOREIGN KEY (parent_id) REFERENCES tasks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT FK_5058659756D27D5D FOREIGN KEY (source_occurrence_id) REFERENCES tasks (id) ON DELETE SET NULL');
        $this->addSql('CREATE TABLE task_assignees (task_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_6B25B7D88DB60186 (task_id), INDEX IDX_6B25B7D8A76ED395 (user_id), PRIMARY KEY(task_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE task_assignees ADD CONSTRAINT FK_6B25B7D88DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_assignees ADD CONSTRAINT FK_6B25B7D8A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('INSERT INTO task_assignees (task_id, user_id) SELECT id, assigned_to_id FROM tasks WHERE assigned_to_id IS NOT NULL');
        $this->addSql('ALTER TABLE tasks DROP FOREIGN KEY FK_50586597F4BD7827');
        $this->addSql('DROP INDEX IDX_50586597F4BD7827 ON tasks');
        $this->addSql('ALTER TABLE tasks DROP assigned_to_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tasks ADD assigned_to_id INT DEFAULT NULL');
        $this->addSql('UPDATE tasks t SET assigned_to_id = (SELECT MIN(ta.user_id) FROM task_assignees ta WHERE ta.task_id = t.id)');
        $this->addSql('CREATE INDEX IDX_50586597F4BD7827 ON tasks (assigned_to_id)');
        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT FK_50586597F4BD7827 FOREIGN KEY (assigned_to_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('DROP TABLE task_assignees');
        $this->addSql('ALTER TABLE tasks DROP FOREIGN KEY FK_50586597727ACA70');
        $this->addSql('ALTER TABLE tasks DROP FOREIGN KEY FK_5058659756D27D5D');
        $this->addSql('DROP INDEX IDX_50586597727ACA70 ON tasks');
        $this->addSql('DROP INDEX UNIQ_5058659756D27D5D ON tasks');
        $this->addSql('ALTER TABLE tasks DROP parent_id, DROP source_occurrence_id, DROP start_date, DROP due_date, DROP tags, DROP reminder_at, DROP recurrence, DROP billing_type, DROP manager_note');
    }
}
