-- =====================================================================
-- Migração 0001 — Cria tabela categoria + FK em livros
--
-- Aplicar com:
--   docker compose exec -T db mariadb -u isbn_user -pchangeme isbn_app \
--     < sql/migrations/0001_categoria.sql
--
-- Decisões referenciadas: #3 (HYB_CATEGORIA_DEFAULT_ID), #4 (dropdown linear
-- ordenado por indice), #11 (col D do XLSX = categoria.descricao).
-- =====================================================================

USE isbn_app;

-- ---------------------------------------------------------------------
-- Tabela: categoria
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categoria (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo        INT UNSIGNED NOT NULL UNIQUE,
    indice        VARCHAR(10)  NOT NULL,
    descricao     VARCHAR(255) NOT NULL,
    parent_id     INT UNSIGNED NULL,
    ativo         TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_indice (indice),
    INDEX idx_parent (parent_id),
    CONSTRAINT fk_categoria_parent
        FOREIGN KEY (parent_id) REFERENCES categoria(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ALTER TABLE livros — adiciona categoria_id (FK p/ categoria)
--
-- A coluna hyb_categoria (string) permanece por compatibilidade com
-- registros legados; categoria_id é o novo vínculo canônico.
-- ---------------------------------------------------------------------
ALTER TABLE livros
    ADD COLUMN categoria_id INT UNSIGNED NULL AFTER hyb_categoria,
    ADD CONSTRAINT fk_livro_categoria
        FOREIGN KEY (categoria_id) REFERENCES categoria(id)
        ON DELETE SET NULL,
    ADD INDEX idx_livro_categoria (categoria_id);
