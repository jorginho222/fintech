<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260812220657 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add company password for authentication and make CUIT unique (it is the login identifier)';
    }

    public function up(Schema $schema): void
    {
        // Pre-existing companies get a placeholder that cannot match any hash: they must reset
        // their password before they can log in.
        $this->addSql("ALTER TABLE companies ADD password VARCHAR(255) DEFAULT '!' NOT NULL");
        $this->addSql('ALTER TABLE companies ALTER COLUMN password DROP DEFAULT');
        $this->addSql('CREATE UNIQUE INDEX uniq_companies_cuit ON companies (cuit)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_companies_cuit');
        $this->addSql('ALTER TABLE companies DROP password');
    }
}
