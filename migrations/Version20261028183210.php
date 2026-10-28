<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028183210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add creation date to credit request applications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credit_request_applications ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        // The real creation date of existing applications is unknown: approved ones use the proposal date of their
        // credit request (created when the bureau answered), the rest fall back to the migration time.
        $this->addSql('UPDATE credit_request_applications cra SET created_at = cr.proposal_date FROM credit_requests cr WHERE cr.id = cra.credit_request_id');
        $this->addSql('UPDATE credit_request_applications SET created_at = NOW() WHERE created_at IS NULL');

        $this->addSql('ALTER TABLE credit_request_applications ALTER created_at SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credit_request_applications DROP created_at');
    }
}
