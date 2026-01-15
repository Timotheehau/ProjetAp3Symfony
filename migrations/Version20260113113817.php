<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260113113817 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE availability ADD day_of_week INT DEFAULT NULL, ADD start_time TIME NOT NULL, ADD end_time TIME NOT NULL, ADD is_recurring TINYINT NOT NULL, ADD specific_date DATE DEFAULT NULL, ADD is_available TINYINT NOT NULL, ADD created_at DATETIME NOT NULL, ADD profile_id INT NOT NULL');
        $this->addSql('ALTER TABLE availability ADD CONSTRAINT FK_3FB7A2BFCCFA12B8 FOREIGN KEY (profile_id) REFERENCES profile (id)');
        $this->addSql('CREATE INDEX IDX_3FB7A2BFCCFA12B8 ON availability (profile_id)');
        $this->addSql('ALTER TABLE donation ADD amount NUMERIC(10, 2) NOT NULL, ADD currency VARCHAR(3) NOT NULL, ADD message LONGTEXT DEFAULT NULL, ADD is_anonymous TINYINT NOT NULL, ADD status VARCHAR(50) NOT NULL, ADD payment_method VARCHAR(100) DEFAULT NULL, ADD transaction_id VARCHAR(255) DEFAULT NULL, ADD created_at DATETIME NOT NULL, ADD processed_at DATETIME DEFAULT NULL, ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE donation ADD CONSTRAINT FK_31E581A0A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_31E581A0A76ED395 ON donation (user_id)');
        $this->addSql('ALTER TABLE review_like ADD created_at DATETIME NOT NULL, ADD review_id INT NOT NULL, ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE review_like ADD CONSTRAINT FK_4ED70DAB3E2E969B FOREIGN KEY (review_id) REFERENCES review (id)');
        $this->addSql('ALTER TABLE review_like ADD CONSTRAINT FK_4ED70DABA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_4ED70DAB3E2E969B ON review_like (review_id)');
        $this->addSql('CREATE INDEX IDX_4ED70DABA76ED395 ON review_like (user_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_review_user ON review_like (review_id, user_id)');
        $this->addSql('ALTER TABLE session_history ADD session_date DATETIME NOT NULL, ADD duration INT NOT NULL, ADD notes LONGTEXT DEFAULT NULL, ADD client_feedback LONGTEXT DEFAULT NULL, ADD professional_feedback LONGTEXT DEFAULT NULL, ADD created_at DATETIME NOT NULL, ADD booking_id INT NOT NULL, ADD profile_id INT NOT NULL, ADD client_id INT NOT NULL');
        $this->addSql('ALTER TABLE session_history ADD CONSTRAINT FK_3562F2113301C60 FOREIGN KEY (booking_id) REFERENCES booking (id)');
        $this->addSql('ALTER TABLE session_history ADD CONSTRAINT FK_3562F211CCFA12B8 FOREIGN KEY (profile_id) REFERENCES profile (id)');
        $this->addSql('ALTER TABLE session_history ADD CONSTRAINT FK_3562F21119EB6921 FOREIGN KEY (client_id) REFERENCES `user` (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3562F2113301C60 ON session_history (booking_id)');
        $this->addSql('CREATE INDEX IDX_3562F211CCFA12B8 ON session_history (profile_id)');
        $this->addSql('CREATE INDEX IDX_3562F21119EB6921 ON session_history (client_id)');
        $this->addSql('ALTER TABLE venue ADD name VARCHAR(200) NOT NULL, ADD type VARCHAR(50) NOT NULL, ADD address VARCHAR(255) NOT NULL, ADD city VARCHAR(100) NOT NULL, ADD postal_code VARCHAR(20) DEFAULT NULL, ADD latitude NUMERIC(10, 8) NOT NULL, ADD longitude NUMERIC(11, 8) NOT NULL, ADD capacity INT DEFAULT NULL, ADD facilities JSON DEFAULT NULL, ADD contact_email VARCHAR(180) DEFAULT NULL, ADD contact_phone VARCHAR(20) DEFAULT NULL, ADD website VARCHAR(255) DEFAULT NULL, ADD is_active TINYINT NOT NULL, ADD created_at DATETIME NOT NULL, ADD sport_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE venue ADD CONSTRAINT FK_91911B0DAC78BCF8 FOREIGN KEY (sport_id) REFERENCES sport (id)');
        $this->addSql('CREATE INDEX IDX_91911B0DAC78BCF8 ON venue (sport_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE availability DROP FOREIGN KEY FK_3FB7A2BFCCFA12B8');
        $this->addSql('DROP INDEX IDX_3FB7A2BFCCFA12B8 ON availability');
        $this->addSql('ALTER TABLE availability DROP day_of_week, DROP start_time, DROP end_time, DROP is_recurring, DROP specific_date, DROP is_available, DROP created_at, DROP profile_id');
        $this->addSql('ALTER TABLE donation DROP FOREIGN KEY FK_31E581A0A76ED395');
        $this->addSql('DROP INDEX IDX_31E581A0A76ED395 ON donation');
        $this->addSql('ALTER TABLE donation DROP amount, DROP currency, DROP message, DROP is_anonymous, DROP status, DROP payment_method, DROP transaction_id, DROP created_at, DROP processed_at, DROP user_id');
        $this->addSql('ALTER TABLE review_like DROP FOREIGN KEY FK_4ED70DAB3E2E969B');
        $this->addSql('ALTER TABLE review_like DROP FOREIGN KEY FK_4ED70DABA76ED395');
        $this->addSql('DROP INDEX IDX_4ED70DAB3E2E969B ON review_like');
        $this->addSql('DROP INDEX IDX_4ED70DABA76ED395 ON review_like');
        $this->addSql('DROP INDEX unique_review_user ON review_like');
        $this->addSql('ALTER TABLE review_like DROP created_at, DROP review_id, DROP user_id');
        $this->addSql('ALTER TABLE session_history DROP FOREIGN KEY FK_3562F2113301C60');
        $this->addSql('ALTER TABLE session_history DROP FOREIGN KEY FK_3562F211CCFA12B8');
        $this->addSql('ALTER TABLE session_history DROP FOREIGN KEY FK_3562F21119EB6921');
        $this->addSql('DROP INDEX UNIQ_3562F2113301C60 ON session_history');
        $this->addSql('DROP INDEX IDX_3562F211CCFA12B8 ON session_history');
        $this->addSql('DROP INDEX IDX_3562F21119EB6921 ON session_history');
        $this->addSql('ALTER TABLE session_history DROP session_date, DROP duration, DROP notes, DROP client_feedback, DROP professional_feedback, DROP created_at, DROP booking_id, DROP profile_id, DROP client_id');
        $this->addSql('ALTER TABLE venue DROP FOREIGN KEY FK_91911B0DAC78BCF8');
        $this->addSql('DROP INDEX IDX_91911B0DAC78BCF8 ON venue');
        $this->addSql('ALTER TABLE venue DROP name, DROP type, DROP address, DROP city, DROP postal_code, DROP latitude, DROP longitude, DROP capacity, DROP facilities, DROP contact_email, DROP contact_phone, DROP website, DROP is_active, DROP created_at, DROP sport_id');
    }
}
