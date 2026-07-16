<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260714230107 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add penalty_interest_amount, penalty_iva21_tax and last_penalty_calculation_at to installments';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE installments ADD penalty_interest_amount NUMERIC(14, 4) DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE installments ADD penalty_iva21_tax NUMERIC(14, 4) DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE installments ADD last_penalty_calculation_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE installments DROP penalty_interest_amount');
        $this->addSql('ALTER TABLE installments DROP penalty_iva21_tax');
        $this->addSql('ALTER TABLE installments DROP last_penalty_calculation_at');
    }
}
