<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828113411 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create publisher table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE publisher (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, website VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE publisher');
    }
}
