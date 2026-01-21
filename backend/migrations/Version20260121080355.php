<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260121080355 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE shopping_list_items (id UUID NOT NULL, custom_name VARCHAR(255) DEFAULT NULL, amount INT DEFAULT 1 NOT NULL, note VARCHAR(500) DEFAULT NULL, source VARCHAR(20) DEFAULT \'manual\' NOT NULL, done BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, product_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX shopping_list_product_idx ON shopping_list_items (product_id)');
        $this->addSql('CREATE INDEX shopping_list_source_idx ON shopping_list_items (source)');
        $this->addSql('CREATE UNIQUE INDEX shopping_list_product_unique ON shopping_list_items (product_id) WHERE product_id IS NOT NULL');
        $this->addSql('ALTER TABLE shopping_list_items ADD CONSTRAINT FK_27429A184584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shopping_list_items DROP CONSTRAINT FK_27429A184584665A');
        $this->addSql('DROP INDEX shopping_list_product_unique');
        $this->addSql('DROP TABLE shopping_list_items');
    }
}
