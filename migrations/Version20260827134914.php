<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827134914 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename credit_requests.created_at to proposal_date and add nullable activation_date';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credit_requests RENAME COLUMN created_at TO proposal_date');
        $this->addSql('ALTER TABLE credit_requests ADD activation_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credit_requests DROP activation_date');
        $this->addSql('ALTER TABLE credit_requests RENAME COLUMN proposal_date TO created_at');
    }
}
