-- =====================================================================
-- POC Código de Barras — Schema MySQL/MariaDB
-- Banco: isbn_app (ou o nome configurado em .env)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS isbn_app
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE isbn_app;

-- ---------------------------------------------------------------------
-- Tabela: livros
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS livros (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    isbn_13         VARCHAR(13) NOT NULL UNIQUE,
    isbn_10         VARCHAR(10) NULL,
    titulo          VARCHAR(500) NOT NULL,
    subtitulo       VARCHAR(500) NULL,
    autores         JSON NULL,
    editora         VARCHAR(255) NULL,
    ano_publicacao  SMALLINT UNSIGNED NULL,
    data_publicacao DATE NULL,
    idioma          VARCHAR(10) NULL,
    paginas         INT UNSIGNED NULL,
    sinopse         TEXT NULL,
    assuntos        JSON NULL,
    categorias      JSON NULL,
    formato         VARCHAR(50) NULL,
    altura_cm       DECIMAL(6,2) NULL,
    largura_cm      DECIMAL(6,2) NULL,
    espessura_cm    DECIMAL(6,2) NULL,
    peso            VARCHAR(50) NULL,
    preco_moeda     VARCHAR(3) NULL,
    preco_valor     DECIMAL(10,2) NULL,
    local_publicacao VARCHAR(255) NULL,
    capa_url        TEXT NULL,
    capa_thumbnail  TEXT NULL,
    link_preview    TEXT NULL,
    avaliacao_media DECIMAL(3,2) NULL,
    qtd_avaliacoes  INT UNSIGNED NULL,
    payload_bruto   JSON NULL,
    fonte_api       VARCHAR(50) NOT NULL,
    provider_origem VARCHAR(50) NULL,
    consultado_em   DATETIME NOT NULL,
    atualizado_em   DATETIME NOT NULL,
    exportado_em    DATETIME NULL,

    -- Campos complementares HYB (preenchidos manualmente, todos opcionais)
    hyb_bem_produto       VARCHAR(50)    NULL,
    hyb_unidade           VARCHAR(20)    NULL,
    hyb_categoria         VARCHAR(255)   NULL,
    hyb_ncm               VARCHAR(20)    NULL,
    hyb_preco_venda       DECIMAL(10,2)  NULL,
    hyb_estoque_minimo    INT            NULL,
    hyb_referencia        VARCHAR(100)   NULL,
    hyb_patrimonio        CHAR(1)        NULL,
    hyb_depreciacao_pct   DECIMAL(5,2)   NULL,
    hyb_tipo              VARCHAR(20)    NULL,
    hyb_estoque_ini_qtd   DECIMAL(15,4)  NULL,
    hyb_estoque_ini_custo DECIMAL(15,4)  NULL,
    hyb_descricao         TEXT           NULL,

    INDEX idx_isbn_10 (isbn_10),
    INDEX idx_titulo (titulo(100)),
    INDEX idx_editora (editora),
    INDEX idx_exportado_em (exportado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: historico_bipagens
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS historico_bipagens (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    isbn_lido      VARCHAR(20) NOT NULL,
    livro_id       BIGINT UNSIGNED NULL,
    sucesso        TINYINT(1) NOT NULL DEFAULT 0,
    fonte_api      VARCHAR(50) NULL,
    mensagem_erro  VARCHAR(500) NULL,
    ip_origem      VARCHAR(45) NULL,
    bipado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (livro_id) REFERENCES livros(id) ON DELETE SET NULL,
    INDEX idx_bipado_em (bipado_em),
    INDEX idx_isbn_lido (isbn_lido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Tabela: exportacoes_hyb
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exportacoes_hyb (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    arquivo        VARCHAR(255) NOT NULL,
    qtd_registros  INT UNSIGNED NOT NULL,
    criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
