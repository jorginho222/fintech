<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260623223108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE credit_requests (total_amount NUMERIC(14, 4) NOT NULL, nominal_interest_rate NUMERIC(4, 2) NOT NULL, status VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id VARCHAR(255) NOT NULL, company_id VARCHAR NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D06E6F6F979B1AD6 ON credit_requests (company_id)');
        $this->addSql('CREATE TABLE installments (period_number INT NOT NULL, capital_amount NUMERIC(14, 4) NOT NULL, interest_amount NUMERIC(14, 4) NOT NULL, tax_on_interest_amount NUMERIC(14, 4) NOT NULL, total_amount NUMERIC(14, 4) NOT NULL, due_date DATE NOT NULL, status VARCHAR(255) NOT NULL, id VARCHAR(255) NOT NULL, credit_request_id VARCHAR NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FE90068CB106031B ON installments (credit_request_id)');
        $this->addSql('ALTER TABLE credit_requests ADD CONSTRAINT FK_D06E6F6F979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('ALTER TABLE installments ADD CONSTRAINT FK_FE90068CB106031B FOREIGN KEY (credit_request_id) REFERENCES credit_requests (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE credit_requests DROP CONSTRAINT FK_D06E6F6F979B1AD6');
        $this->addSql('ALTER TABLE installments DROP CONSTRAINT FK_FE90068CB106031B');
        $this->addSql('DROP TABLE credit_requests');
        $this->addSql('DROP TABLE installments');
    }
}
