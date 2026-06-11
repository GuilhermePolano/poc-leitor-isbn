-- =====================================================================
-- Migração 0002 — Histórico de baixas (batch + itens)
--
-- Aplicar com:
--   docker compose exec -T db mariadb -u isbn_user -pchangeme isbn_app \
--     < sql/migrations/0002_exportacoes.sql
--
-- Decisões referenciadas: #13 (batch + itens reaproveitando exportacoes_hyb),
-- #1 (registrar após Writer::save() OK), #8 (sem purga), #12 (snapshot de
-- quantidade no item — override só da baixa, não persiste em livros).
--
-- Notas de design:
--  - A tabela exportacoes_hyb já existia como "órfã" (sem uso). Aproveitamos
--    o id para evitar quebra de consistência futura e preservar o histórico
--    pré-existente, caso haja. Por isso usamos ALTER, não DROP+CREATE.
--  - "criado_em" foi renomeado para "gerado_em" para refletir o momento
--    exato da geração do XLSX (decisão #1: a baixa é o save() OK).
--  - "origem" registra se o batch veio do fluxo de lista (botão na index)
--    ou do atalho pós-cadastro "Salvar e baixar XLSX deste livro" (decisão #2).
--  - exportacoes_hyb_itens.quantidade é nullable porque captura o override
--    do modal (decisão #12). NULL = "usou o valor padrão do livro".
-- =====================================================================

USE isbn_app;

-- ---------------------------------------------------------------------
-- ALTER TABLE exportacoes_hyb — cabeçalho do batch
-- ---------------------------------------------------------------------

-- Renomeia criado_em -> gerado_em (semântica: instante do Writer::save() OK)
ALTER TABLE exportacoes_hyb
    CHANGE COLUMN criado_em gerado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Acrescenta usuario (opcional — placeholder p/ quando houver login)
ALTER TABLE exportacoes_hyb
    ADD COLUMN usuario VARCHAR(100) NULL AFTER gerado_em;

-- Acrescenta origem (lista vs atalho pós-cadastro — decisão #2)
ALTER TABLE exportacoes_hyb
    ADD COLUMN origem ENUM('lista','atalho_bipagem') NOT NULL DEFAULT 'lista' AFTER usuario;

-- Índices para consultas típicas (auditoria por data, busca por arquivo)
ALTER TABLE exportacoes_hyb
    ADD INDEX idx_gerado_em (gerado_em DESC),
    ADD INDEX idx_arquivo (arquivo);

-- ---------------------------------------------------------------------
-- Tabela: exportacoes_hyb_itens — linhas do batch
--
-- Um INSERT por (livro, batch). Permite reconstruir o XLSX gerado e
-- responder "quantas vezes esse livro já foi baixado?" e "quando foi
-- a última baixa?" (decisão #8 — sem purga).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exportacoes_hyb_itens (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exportacao_id  BIGINT UNSIGNED NOT NULL,
    livro_id       BIGINT UNSIGNED NOT NULL,
    quantidade     INT UNSIGNED NULL,
    CONSTRAINT fk_item_exportacao
        FOREIGN KEY (exportacao_id) REFERENCES exportacoes_hyb(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_item_livro
        FOREIGN KEY (livro_id) REFERENCES livros(id)
        ON DELETE CASCADE,
    INDEX idx_livro_baixa (livro_id),
    INDEX idx_exportacao (exportacao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
