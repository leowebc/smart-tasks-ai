<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria documents e document_chunks sem alterar users e tasks.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE documents (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(127) NOT NULL,
    extension VARCHAR(8) NOT NULL,
    size_bytes INT NOT NULL,
    status VARCHAR(20) NOT NULL,
    error_message LONGTEXT DEFAULT NULL,
    chunk_count INT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX IDX_documents_user (user_id),
    PRIMARY KEY(id),
    CONSTRAINT FK_documents_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE document_chunks (
    id INT AUTO_INCREMENT NOT NULL,
    document_id INT NOT NULL,
    chunk_index INT NOT NULL,
    content LONGTEXT NOT NULL,
    embedding JSON NOT NULL,
    embedding_model VARCHAR(100) NOT NULL,
    source_metadata JSON NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX IDX_chunks_document (document_id),
    UNIQUE INDEX uniq_chunk_document_index (document_id, chunk_index),
    PRIMARY KEY(id),
    CONSTRAINT FK_chunks_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE document_chunks');
        $this->addSql('DROP TABLE documents');
    }
}
