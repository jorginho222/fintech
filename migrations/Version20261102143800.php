<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102143800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last payment attempt to installments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE installments ADD last_payment_attempt_status VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE installments ADD last_payment_attempt_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE installments ADD last_payment_attempt_error_message TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE installments DROP last_payment_attempt_status');
        $this->addSql('ALTER TABLE installments DROP last_payment_attempt_at');
        $this->addSql('ALTER TABLE installments DROP last_payment_attempt_error_message');
    }
}
