<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260803102539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE companies (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, ticker VARCHAR(20) NOT NULL, logo_url VARCHAR(255) DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, description LONGTEXT DEFAULT NULL, website_url VARCHAR(255) DEFAULT NULL, sector_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_8244AA3A7EC30896 (ticker), INDEX IDX_8244AA3ADE95C867 (sector_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sectors (id INT AUTO_INCREMENT NOT NULL, ticker_symbol VARCHAR(100) NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, gics_code VARCHAR(100) DEFAULT NULL, gics_name VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_B594069850E555B5 (ticker_symbol), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stock_prices (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, open DOUBLE PRECISION NOT NULL, high DOUBLE PRECISION NOT NULL, low DOUBLE PRECISION NOT NULL, close DOUBLE PRECISION NOT NULL, volume BIGINT DEFAULT NULL, company_id INT NOT NULL, INDEX IDX_2EEDBFCF979B1AD6 (company_id), INDEX idx_stock_price_date (date), UNIQUE INDEX company_date_unique (company_id, date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE companies ADD CONSTRAINT FK_8244AA3ADE95C867 FOREIGN KEY (sector_id) REFERENCES sectors (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_prices ADD CONSTRAINT FK_2EEDBFCF979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE companies DROP FOREIGN KEY FK_8244AA3ADE95C867');
        $this->addSql('ALTER TABLE stock_prices DROP FOREIGN KEY FK_2EEDBFCF979B1AD6');
        $this->addSql('DROP TABLE companies');
        $this->addSql('DROP TABLE sectors');
        $this->addSql('DROP TABLE stock_prices');
    }
}
