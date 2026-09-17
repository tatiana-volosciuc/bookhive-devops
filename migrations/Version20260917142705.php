<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917142705 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add photo key for authors table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE author ADD photo_key VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE author DROP photo_key');
    }
}
