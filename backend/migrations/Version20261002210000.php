<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona source_url em documents sem alterar users, tasks ou chunks.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documents ADD source_url VARCHAR(2048) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documents DROP source_url');
    }
}
