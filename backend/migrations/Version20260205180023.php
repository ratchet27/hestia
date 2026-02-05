<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260205180023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chores (id UUID NOT NULL, name VARCHAR(255) NOT NULL, schedule_type VARCHAR(255) NOT NULL, schedule_value INT NOT NULL, assignee VARCHAR(100) DEFAULT NULL, last_done_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, next_due_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE tasks (id UUID NOT NULL, name VARCHAR(255) NOT NULL, due_date DATE DEFAULT NULL, priority VARCHAR(255) NOT NULL, done BOOLEAN DEFAULT false NOT NULL, done_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE chores');
        $this->addSql('DROP TABLE tasks');
    }
}
