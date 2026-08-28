<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828114639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create book table. Add relations between tables and book table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE book (
    id INT AUTO_INCREMENT NOT NULL, 
    title VARCHAR(255) NOT NULL, 
    isbn VARCHAR(20) NOT NULL, 
    description LONGTEXT DEFAULT NULL, 
    price DOUBLE PRECISION NOT NULL, 
    stock INT NOT NULL, 
    published_at DATE DEFAULT NULL, 
    slug VARCHAR(255) NOT NULL, 
    category_id INT NOT NULL, 
    publisher_id INT DEFAULT NULL, 
    INDEX IDX_CBE5A33112469DE2 (category_id), 
    INDEX IDX_CBE5A33140C86FCE (publisher_id), 
    PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4'
        );

        $this->addSql('CREATE TABLE book_author (book_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_9478D34516A2B381 (book_id), INDEX IDX_9478D345F675F31B (author_id), PRIMARY KEY (book_id, author_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE book ADD CONSTRAINT FK_CBE5A33112469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE book ADD CONSTRAINT FK_CBE5A33140C86FCE FOREIGN KEY (publisher_id) REFERENCES publisher (id)');
        $this->addSql('ALTER TABLE book_author ADD CONSTRAINT FK_9478D34516A2B381 FOREIGN KEY (book_id) REFERENCES book (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE book_author ADD CONSTRAINT FK_9478D345F675F31B FOREIGN KEY (author_id) REFERENCES author (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book DROP FOREIGN KEY FK_CBE5A33112469DE2');
        $this->addSql('ALTER TABLE book DROP FOREIGN KEY FK_CBE5A33140C86FCE');
        $this->addSql('ALTER TABLE book_author DROP FOREIGN KEY FK_9478D34516A2B381');
        $this->addSql('ALTER TABLE book_author DROP FOREIGN KEY FK_9478D345F675F31B');
        $this->addSql('DROP TABLE book');
        $this->addSql('DROP TABLE book_author');
    }
}
