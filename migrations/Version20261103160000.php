<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep credit request applications when their credit request is deleted';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credit_request_applications DROP CONSTRAINT FK_9CCE7083B106031B');
        $this->addSql('ALTER TABLE credit_request_applications ADD CONSTRAINT FK_9CCE7083B106031B FOREIGN KEY (credit_request_id) REFERENCES credit_requests (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credit_request_applications DROP CONSTRAINT FK_9CCE7083B106031B');
        $this->addSql('ALTER TABLE credit_request_applications ADD CONSTRAINT FK_9CCE7083B106031B FOREIGN KEY (credit_request_id) REFERENCES credit_requests (id)');
    }
}
