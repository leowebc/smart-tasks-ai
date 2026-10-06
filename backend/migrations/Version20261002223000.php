<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrupa páginas de uma importação sem alterar chunks nem embeddings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE source_imports (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    root_url VARCHAR(2048) NOT NULL,
    title VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX IDX_source_imports_user (user_id),
    PRIMARY KEY(id),
    CONSTRAINT FK_source_imports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
SQL);
        $this->addSql('ALTER TABLE documents ADD import_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE documents ADD CONSTRAINT FK_documents_import FOREIGN KEY (import_id) REFERENCES source_imports (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_documents_import ON documents (import_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documents DROP FOREIGN KEY FK_documents_import');
        $this->addSql('DROP INDEX IDX_documents_import ON documents');
        $this->addSql('ALTER TABLE documents DROP import_id');
        $this->addSql('DROP TABLE source_imports');
    }
}
