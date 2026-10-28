<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028173726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a company-unique user-provided reference to credit request applications and credit requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credit_request_applications ADD reference VARCHAR(60) DEFAULT NULL');
        $this->addSql('ALTER TABLE credit_requests ADD reference VARCHAR(60) DEFAULT NULL');

        // Existing rows have no user-provided reference. Credit requests get "Solicitud dd/mm/yyyy" from their
        // activation date (proposal date if never activated), numbered when a company has several on the same day.
        $this->addSql(<<<'SQL'
            WITH numbered AS (
                SELECT
                    id,
                    TO_CHAR(COALESCE(activation_date, proposal_date), 'DD/MM/YYYY') AS day,
                    ROW_NUMBER() OVER same_day AS position,
                    COUNT(*) OVER (PARTITION BY company_id, COALESCE(activation_date, proposal_date)::date) AS same_day_count
                FROM credit_requests
                WINDOW same_day AS (PARTITION BY company_id, COALESCE(activation_date, proposal_date)::date ORDER BY COALESCE(activation_date, proposal_date), id)
            )
            UPDATE credit_requests cr
            SET reference = 'Solicitud ' || numbered.day || CASE WHEN numbered.same_day_count > 1 THEN ' ' || numbered.position ELSE '' END
            FROM numbered
            WHERE numbered.id = cr.id
            SQL);

        // Applications share the reference of their credit request; those without one have no date to use,
        // so they are numbered per company.
        $this->addSql('UPDATE credit_request_applications cra SET reference = cr.reference FROM credit_requests cr WHERE cr.id = cra.credit_request_id');
        $this->addSql(<<<'SQL'
            WITH numbered AS (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY company_id ORDER BY id) AS position
                FROM credit_request_applications
                WHERE credit_request_id IS NULL
            )
            UPDATE credit_request_applications cra
            SET reference = 'Solicitud ' || numbered.position
            FROM numbered
            WHERE numbered.id = cra.id
            SQL);

        $this->addSql('ALTER TABLE credit_request_applications ALTER reference SET NOT NULL');
        $this->addSql('ALTER TABLE credit_requests ALTER reference SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_credit_request_applications_company_reference ON credit_request_applications (company_id, reference)');
        $this->addSql('CREATE UNIQUE INDEX uniq_credit_requests_company_reference ON credit_requests (company_id, reference)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_credit_request_applications_company_reference');
        $this->addSql('ALTER TABLE credit_request_applications DROP reference');
        $this->addSql('DROP INDEX uniq_credit_requests_company_reference');
        $this->addSql('ALTER TABLE credit_requests DROP reference');
    }
}
