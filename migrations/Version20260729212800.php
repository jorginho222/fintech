<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260729212800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE credit_request_applications (amount NUMERIC(14, 4) NOT NULL, installment_quantity INT NOT NULL, status VARCHAR(255) NOT NULL, id VARCHAR(255) NOT NULL, company_id VARCHAR NOT NULL, credit_request_id VARCHAR DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9CCE7083979B1AD6 ON credit_request_applications (company_id)');
        $this->addSql('CREATE INDEX IDX_9CCE7083B106031B ON credit_request_applications (credit_request_id)');
        $this->addSql('ALTER TABLE credit_request_applications ADD CONSTRAINT FK_9CCE7083979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('ALTER TABLE credit_request_applications ADD CONSTRAINT FK_9CCE7083B106031B FOREIGN KEY (credit_request_id) REFERENCES credit_requests (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE credit_request_applications DROP CONSTRAINT FK_9CCE7083979B1AD6');
        $this->addSql('ALTER TABLE credit_request_applications DROP CONSTRAINT FK_9CCE7083B106031B');
        $this->addSql('DROP TABLE credit_request_applications');
    }
}
