<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add employee profile fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD profile_image VARCHAR(255) DEFAULT NULL, ADD job_title VARCHAR(160) DEFAULT NULL, ADD bio LONGTEXT DEFAULT NULL, ADD skills LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP profile_image, DROP job_title, DROP bio, DROP skills');
    }
}
