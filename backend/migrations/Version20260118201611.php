<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260118201611 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_entries (id UUID NOT NULL, best_before DATE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, product_id UUID NOT NULL, location_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX stock_entry_product_idx ON stock_entries (product_id)');
        $this->addSql('CREATE INDEX stock_entry_location_idx ON stock_entries (location_id)');
        $this->addSql('CREATE INDEX stock_entry_fifo_idx ON stock_entries (product_id, location_id, best_before, created_at)');
        $this->addSql('ALTER TABLE stock_entries ADD CONSTRAINT FK_345EA3664584665A FOREIGN KEY (product_id) REFERENCES products (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stock_entries ADD CONSTRAINT FK_345EA36664D218E FOREIGN KEY (location_id) REFERENCES locations (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE products ADD unit VARCHAR(50) DEFAULT \'piece\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_entries DROP CONSTRAINT FK_345EA3664584665A');
        $this->addSql('ALTER TABLE stock_entries DROP CONSTRAINT FK_345EA36664D218E');
        $this->addSql('DROP TABLE stock_entries');
        $this->addSql('ALTER TABLE products DROP unit');
    }
}
