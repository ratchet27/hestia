<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260118145001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_movements (id UUID NOT NULL, type VARCHAR(255) NOT NULL, quantity INT NOT NULL, notes VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, stock_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX stock_movement_stock_idx ON stock_movements (stock_id)');
        $this->addSql('CREATE TABLE stocks (id UUID NOT NULL, quantity INT DEFAULT 0 NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, product_id UUID NOT NULL, location_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX stock_product_idx ON stocks (product_id)');
        $this->addSql('CREATE INDEX stock_location_idx ON stocks (location_id)');
        $this->addSql('CREATE UNIQUE INDEX stock_product_location_unique ON stocks (product_id, location_id)');
        $this->addSql('ALTER TABLE stock_movements ADD CONSTRAINT FK_A0BE93C9DCD6110 FOREIGN KEY (stock_id) REFERENCES stocks (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stocks ADD CONSTRAINT FK_56F798054584665A FOREIGN KEY (product_id) REFERENCES products (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stocks ADD CONSTRAINT FK_56F7980564D218E FOREIGN KEY (location_id) REFERENCES locations (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_movements DROP CONSTRAINT FK_A0BE93C9DCD6110');
        $this->addSql('ALTER TABLE stocks DROP CONSTRAINT FK_56F798054584665A');
        $this->addSql('ALTER TABLE stocks DROP CONSTRAINT FK_56F7980564D218E');
        $this->addSql('DROP TABLE stock_movements');
        $this->addSql('DROP TABLE stocks');
    }
}
