<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714234500 extends AbstractMigration
{
    public function getDescription(): string { return 'Add task documents, dependencies, problems, status history and activity.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE task_documents (id INT AUTO_INCREMENT NOT NULL, task_id INT NOT NULL, uploaded_by_id INT DEFAULT NULL, original_filename VARCHAR(255) NOT NULL, stored_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, file_size INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_TASK_DOC_TASK (task_id), INDEX IDX_TASK_DOC_USER (uploaded_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE task_dependencies (id INT AUTO_INCREMENT NOT NULL, task_id INT NOT NULL, prerequisite_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_TASK_DEP_TASK (task_id), INDEX IDX_TASK_DEP_REQ (prerequisite_id), UNIQUE INDEX uniq_task_prerequisite (task_id, prerequisite_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE task_problems (id INT AUTO_INCREMENT NOT NULL, task_id INT NOT NULL, author_id INT DEFAULT NULL, resolved_by_id INT DEFAULT NULL, description LONGTEXT NOT NULL, resolved TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', resolved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_TASK_PROB_TASK (task_id), INDEX IDX_TASK_PROB_AUTHOR (author_id), INDEX IDX_TASK_PROB_RESOLVER (resolved_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE task_status_history (id INT AUTO_INCREMENT NOT NULL, task_id INT NOT NULL, actor_id INT DEFAULT NULL, previous_status VARCHAR(32) NOT NULL, new_status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_TASK_HIST_TASK (task_id), INDEX IDX_TASK_HIST_ACTOR (actor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE task_activities (id INT AUTO_INCREMENT NOT NULL, task_id INT NOT NULL, actor_id INT DEFAULT NULL, type VARCHAR(50) NOT NULL, summary VARCHAR(255) NOT NULL, metadata JSON NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_TASK_ACT_TASK (task_id), INDEX IDX_TASK_ACT_ACTOR (actor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        foreach ([['task_documents','task_id','tasks','CASCADE'],['task_documents','uploaded_by_id','users','SET NULL'],['task_dependencies','task_id','tasks','CASCADE'],['task_dependencies','prerequisite_id','tasks','CASCADE'],['task_problems','task_id','tasks','CASCADE'],['task_problems','author_id','users','SET NULL'],['task_problems','resolved_by_id','users','SET NULL'],['task_status_history','task_id','tasks','CASCADE'],['task_status_history','actor_id','users','SET NULL'],['task_activities','task_id','tasks','CASCADE'],['task_activities','actor_id','users','SET NULL']] as [$table,$column,$target,$delete]) {
            $this->addSql(sprintf('ALTER TABLE %s ADD FOREIGN KEY (%s) REFERENCES %s (id) ON DELETE %s', $table, $column, $target, $delete));
        }
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE task_activities');
        $this->addSql('DROP TABLE task_status_history');
        $this->addSql('DROP TABLE task_problems');
        $this->addSql('DROP TABLE task_dependencies');
        $this->addSql('DROP TABLE task_documents');
    }
}
