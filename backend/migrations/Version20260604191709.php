<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604191709 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create recipes and recipe_ingredients tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE recipe_ingredients (id UUID NOT NULL, required_count INT DEFAULT 1 NOT NULL, consume_on_cook BOOLEAN DEFAULT true NOT NULL, recipe_id UUID NOT NULL, product_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX recipe_ingredient_recipe_idx ON recipe_ingredients (recipe_id)');
        $this->addSql('CREATE INDEX recipe_ingredient_product_idx ON recipe_ingredients (product_id)');
        $this->addSql('CREATE UNIQUE INDEX recipe_ingredient_unique ON recipe_ingredients (recipe_id, product_id)');
        $this->addSql('CREATE TABLE recipes (id UUID NOT NULL, name VARCHAR(255) NOT NULL, instructions TEXT DEFAULT NULL, source_url VARCHAR(1024) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE recipe_ingredients ADD CONSTRAINT FK_9F925F2B59D8A214 FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE recipe_ingredients ADD CONSTRAINT FK_9F925F2B4584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe_ingredients DROP CONSTRAINT FK_9F925F2B59D8A214');
        $this->addSql('ALTER TABLE recipe_ingredients DROP CONSTRAINT FK_9F925F2B4584665A');
        $this->addSql('DROP TABLE recipe_ingredients');
        $this->addSql('DROP TABLE recipes');
    }
}
