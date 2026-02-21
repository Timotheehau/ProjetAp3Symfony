<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221164110 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE availability ADD sport_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE availability ADD CONSTRAINT FK_3FB7A2BFAC78BCF8 FOREIGN KEY (sport_id) REFERENCES sport (id)');
        $this->addSql('CREATE INDEX IDX_3FB7A2BFAC78BCF8 ON availability (sport_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE availability DROP FOREIGN KEY FK_3FB7A2BFAC78BCF8');
        $this->addSql('DROP INDEX IDX_3FB7A2BFAC78BCF8 ON availability');
        $this->addSql('ALTER TABLE availability DROP sport_id');
    }
}
