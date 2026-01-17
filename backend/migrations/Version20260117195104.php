<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260117195104 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create products feature tables: categories, locations, products, barcodes';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE barcodes (id UUID NOT NULL, barcode VARCHAR(50) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, product_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BF48A56497AE0266 ON barcodes (barcode)');
        $this->addSql('CREATE INDEX IDX_BF48A5644584665A ON barcodes (product_id)');
        $this->addSql('CREATE TABLE categories (id UUID NOT NULL, name VARCHAR(100) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3AF346685E237E06 ON categories (name)');
        $this->addSql('CREATE TABLE locations (id UUID NOT NULL, name VARCHAR(100) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_17E64ABA5E237E06 ON locations (name)');
        $this->addSql('CREATE TABLE products (id UUID NOT NULL, name VARCHAR(255) NOT NULL, default_expiry_days INT DEFAULT NULL, min_stock INT DEFAULT 0 NOT NULL, active BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, category_id UUID NOT NULL, default_location_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B3BA5A5A5E237E06 ON products (name)');
        $this->addSql('CREATE INDEX IDX_B3BA5A5A12469DE2 ON products (category_id)');
        $this->addSql('CREATE INDEX IDX_B3BA5A5AD2BE3238 ON products (default_location_id)');
        $this->addSql('ALTER TABLE barcodes ADD CONSTRAINT FK_BF48A5644584665A FOREIGN KEY (product_id) REFERENCES products (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5A12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5AD2BE3238 FOREIGN KEY (default_location_id) REFERENCES locations (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE barcodes DROP CONSTRAINT FK_BF48A5644584665A');
        $this->addSql('ALTER TABLE products DROP CONSTRAINT FK_B3BA5A5A12469DE2');
        $this->addSql('ALTER TABLE products DROP CONSTRAINT FK_B3BA5A5AD2BE3238');
        $this->addSql('DROP TABLE barcodes');
        $this->addSql('DROP INDEX UNIQ_3AF346685E237E06');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP INDEX UNIQ_17E64ABA5E237E06');
        $this->addSql('DROP TABLE locations');
        $this->addSql('DROP INDEX UNIQ_B3BA5A5A5E237E06');
        $this->addSql('DROP TABLE products');
    }
}
