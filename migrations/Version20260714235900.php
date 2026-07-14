<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714235900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize task workspace columns and index names to the Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_comments RENAME INDEX idx_6fb35b6d8db60186 TO IDX_1F5E7C668DB60186, RENAME INDEX idx_6fb35b6df675f31b TO IDX_1F5E7C66F675F31B');
        $this->addSql('ALTER TABLE task_time_entries RENAME INDEX idx_4ca1bc618db60186 TO IDX_B1EE368E8DB60186, RENAME INDEX idx_4ca1bc618c03f15c TO IDX_B1EE368E8C03F15C');
        $this->addSql('ALTER TABLE task_dependencies CHANGE created_at created_at DATETIME NOT NULL, RENAME INDEX idx_task_dep_task TO IDX_229E54A08DB60186, RENAME INDEX idx_task_dep_req TO IDX_229E54A0276AF86B');
        $this->addSql('ALTER TABLE task_status_history CHANGE created_at created_at DATETIME NOT NULL, RENAME INDEX idx_task_hist_task TO IDX_334371618DB60186, RENAME INDEX idx_task_hist_actor TO IDX_3343716110DAF24A');
        $this->addSql('ALTER TABLE tasks CHANGE start_date start_date DATE DEFAULT NULL, CHANGE due_date due_date DATE DEFAULT NULL, CHANGE reminder_at reminder_at DATETIME DEFAULT NULL, CHANGE billing_type billing_type VARCHAR(32) NOT NULL, RENAME INDEX uniq_5058659756d27d5d TO UNIQ_50586597F53BFECE');
        $this->addSql('ALTER TABLE task_assignees RENAME INDEX idx_6b25b7d88db60186 TO IDX_6DEED38D8DB60186, RENAME INDEX idx_6b25b7d8a76ed395 TO IDX_6DEED38DA76ED395');
        $this->addSql('ALTER TABLE task_documents CHANGE created_at created_at DATETIME NOT NULL, RENAME INDEX idx_task_doc_task TO IDX_DD9ABFD98DB60186, RENAME INDEX idx_task_doc_user TO IDX_DD9ABFD9A2B28FE8');
        $this->addSql('ALTER TABLE task_problems CHANGE created_at created_at DATETIME NOT NULL, CHANGE resolved_at resolved_at DATETIME DEFAULT NULL, RENAME INDEX idx_task_prob_task TO IDX_CEA688098DB60186, RENAME INDEX idx_task_prob_author TO IDX_CEA68809F675F31B, RENAME INDEX idx_task_prob_resolver TO IDX_CEA688096713A32B');
        $this->addSql('ALTER TABLE task_activities CHANGE created_at created_at DATETIME NOT NULL, RENAME INDEX idx_task_act_task TO IDX_A9E2E44A8DB60186, RENAME INDEX idx_task_act_actor TO IDX_A9E2E44A10DAF24A');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_comments RENAME INDEX IDX_1F5E7C668DB60186 TO idx_6fb35b6d8db60186, RENAME INDEX IDX_1F5E7C66F675F31B TO idx_6fb35b6df675f31b');
        $this->addSql('ALTER TABLE task_time_entries RENAME INDEX IDX_B1EE368E8DB60186 TO idx_4ca1bc618db60186, RENAME INDEX IDX_B1EE368E8C03F15C TO idx_4ca1bc618c03f15c');
        $this->addSql('ALTER TABLE task_dependencies RENAME INDEX IDX_229E54A08DB60186 TO idx_task_dep_task, RENAME INDEX IDX_229E54A0276AF86B TO idx_task_dep_req');
        $this->addSql('ALTER TABLE task_status_history RENAME INDEX IDX_334371618DB60186 TO idx_task_hist_task, RENAME INDEX IDX_3343716110DAF24A TO idx_task_hist_actor');
        $this->addSql('ALTER TABLE tasks ALTER billing_type SET DEFAULT \'billable\', RENAME INDEX UNIQ_50586597F53BFECE TO uniq_5058659756d27d5d');
        $this->addSql('ALTER TABLE task_assignees RENAME INDEX IDX_6DEED38D8DB60186 TO idx_6b25b7d88db60186, RENAME INDEX IDX_6DEED38DA76ED395 TO idx_6b25b7d8a76ed395');
        $this->addSql('ALTER TABLE task_documents RENAME INDEX IDX_DD9ABFD98DB60186 TO idx_task_doc_task, RENAME INDEX IDX_DD9ABFD9A2B28FE8 TO idx_task_doc_user');
        $this->addSql('ALTER TABLE task_problems RENAME INDEX IDX_CEA688098DB60186 TO idx_task_prob_task, RENAME INDEX IDX_CEA68809F675F31B TO idx_task_prob_author, RENAME INDEX IDX_CEA688096713A32B TO idx_task_prob_resolver');
        $this->addSql('ALTER TABLE task_activities RENAME INDEX IDX_A9E2E44A8DB60186 TO idx_task_act_task, RENAME INDEX IDX_A9E2E44A10DAF24A TO idx_task_act_actor');
    }
}
