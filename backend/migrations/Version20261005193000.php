<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona status às tarefas existentes e novas.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tasks ADD status VARCHAR(20) DEFAULT 'pending' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tasks DROP status');
    }
}
