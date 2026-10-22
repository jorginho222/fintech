<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022020205 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add users (admin and member) and move company credentials to an admin user per company';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (tax_identifier VARCHAR(11) NOT NULL, password VARCHAR(255) NOT NULL, active BOOLEAN NOT NULL, id VARCHAR(255) NOT NULL, company_id VARCHAR NOT NULL, type VARCHAR(20) NOT NULL, display_name VARCHAR(255) DEFAULT NULL, permissions JSON DEFAULT NULL, must_change_password BOOLEAN DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_users_company_id ON users (company_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_tax_identifier ON users (tax_identifier)');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_company_admin ON users (company_id) WHERE ((type)::text = \'admin\'::text)');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');

        // Each existing company becomes the admin user of itself: same CUIT, same password hash,
        // so current logins keep working.
        $this->addSql('INSERT INTO users (id, company_id, tax_identifier, password, active, type) SELECT gen_random_uuid()::text, id, cuit, password, true, \'admin\' FROM companies');

        $this->addSql('ALTER TABLE companies DROP password');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE companies ADD password VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE companies c SET password = u.password FROM users u WHERE u.company_id = c.id AND u.type = \'admin\'');
        $this->addSql('ALTER TABLE companies ALTER password SET NOT NULL');

        $this->addSql('ALTER TABLE users DROP CONSTRAINT FK_1483A5E9979B1AD6');
        $this->addSql('DROP TABLE users');
    }
}
