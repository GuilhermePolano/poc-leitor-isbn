# Spec — Ajustes POC Código de Barras

## Sumário executivo

Esta spec consolida 4 ajustes principais e 3 extras solicitados sobre a POC atual (leitor ISBN → enriquecimento por APIs → cadastro HYB → export XLSX):

1. **Exportação XLSX HYB com modal de seleção de baixas** — o pipeline de export já existe e gera o arquivo no formato `HYBIntegrador_bens.xlsx`, mas falta tela/modal mostrando livros já baixados com data/hora e checkbox de re-baixa, além de quantidade editável por linha.
2. **Categorias selecionáveis (dropdown)** — hoje o campo Categoria é texto livre em `[public/index.php]`. Precisa virar dropdown alimentado pela planilha `Categorias (3).xlsx`, com nova tabela `categoria` no banco guardando `Código` e `Índice`.
3. **Leitor por câmera (webcam/integrada)** — não existe hoje. Importante: **navegadores web não têm acesso ao "validador nativo do Windows"**; o que existe é `BarcodeDetector API` (nativa do Chromium) + fallback ZXing-js.
4. **Câmera → consulta automática** — o EAN-13 decodificado deve disparar o mesmo endpoint `POST /api/consultar.php` que o leitor USB já usa, abrindo o formulário HYB abaixo.

Extras: (E1) enriquecimento de campos de APIs subutilizados (tradutor/ilustrador da Open Library, classificação etária do Google Books, etc.); (E2) campo quantidade numérico inteiro mapeado para coluna `M` do HYB; (E3) modal de baixas já coberto no Ajuste 1.

> **Contexto estratégico (decisão #10)**: o usuário confirmou que o **objetivo é transformar a POC em aplicação web** (acessível pela internet via HTTPS), não apenas em ferramenta local. Isso impacta: (a) configuração HTTPS obrigatória; (b) preparação para multi-usuário (coluna `usuario` em `exportacoes_hyb` já reservada); (c) cookies seguros; (d) eventual login no futuro. Implementações desta spec devem evitar atalhos que assumam ambiente single-user/localhost.

> **Status do review**: 13 decisões de comportamento ✅ confirmadas em 2026-06-02. Faltam 14 confirmações de mapeamento coluna-a-coluna (M1-M4, M6-M13, M15, M16) — ver §"Decisões pendentes — mapeamento coluna-a-coluna" abaixo.

---

## Estado atual (snapshot)

| Módulo | Arquivo | Papel atual | Gap (item do pedido) |
|---|---|---|---|
| Endpoint export | [api/exportar.php] | Recebe `?ids=`, `?apenas_nao_exportados=1` ou nada; faz download direto | (1) Não tem botão "baixar essa bipagem" pós-cadastro; (1.4) sem modal de seleção |
| Use case export | [src/Application/ExportarLivrosParaHybService.php] | Busca livros, chama exportador, marca `exportado_em` | (1.5) Não registra histórico; sobrescreve único timestamp |
| Adapter XLSX | [src/Infrastructure/Adapter/Out/Export/XlsxHybExporter.php] | Monta 15 colunas A–O + aba Legenda; preserva espaços críticos em M1/N1; força TEXT em E/F | (1) OK — paridade com template confirmada |
| Mapeador domínio→XLSX | [src/Domain/Service/MapeadorParaFormatoHyb.php] | Mapeia 15 campos do `Livro` para colunas | (E1) Não consome campos extras da API; (E2) coluna M já existe mas pode receber `quantidade` |
| Entidade HYB | [src/Domain/Entity/CamposHyb.php] | 13 campos opcionais; `?string $categoria` texto livre | (2) Categoria precisa virar `?int $categoriaId` |
| Schema DB | [sql/schema.sql] | `livros`, `historico_bipagens`, `exportacoes_hyb` (órfã) | (2) Falta tabela `categoria`; (1.5) falta `export_baixa`/`exportacoes_hyb_itens`; (E2) falta coluna `quantidade` |
| Repositório livros | [src/Infrastructure/Adapter/Out/Persistence/MySqlLivroRepository.php] | UPSERT por `isbn_13`; `marcarComoExportados()` faz UPDATE em massa | (E2) ON DUPLICATE não incrementa contador; (1.5) sem histórico |
| Tela bipagem | [public/index.php] | Input `#campo-isbn` autofocus + form HYB com 13 campos; Categoria é `<input type="text">` (linhas 85-87) | (2) Categoria precisa virar `<select>`; (3) sem UI de câmera; (E2) sem campo quantidade dedicado |
| Tela lista | [public/lista.php] | Filtros + 3 botões de export + badge "Sim/Não" | (1.4) badge sem data/hora; sem modal; sem quantidade por linha |
| JS bipagem | [public/assets/js/app.js] | Keydown Enter → `consultarIsbn`; F2/F8/ESC; `focarInput()` agressivo | (3/4) Sem getUserMedia/BarcodeDetector; precisa pausar focarInput durante câmera |
| JS lista | [public/assets/js/lista.js] | Paginação 50; export selecionados via `?ids=` | (1.4) Sem modal de re-baixa |
| Adapter BrasilAPI | [src/Infrastructure/Adapter/Out/IsbnProvider/BrasilApiClient.php] | Extrai todos campos públicos | (E1) `cover_url` duplicada em capa+thumb |
| Adapter Google Books | [src/Infrastructure/Adapter/Out/IsbnProvider/GoogleBooksClient.php] | Extrai 90% dos campos | (E1) Não captura `mainCategory`, `maturityRating`, `panelizationSummary`, `searchInfo.textSnippet` |
| Adapter Open Library | [src/Infrastructure/Adapter/Out/IsbnProvider/OpenLibraryClient.php] | Extrai básico | (E1) Não captura `contributors[]` (tradutor/ilustrador), `physical_format`, `series`, `edition_name`, `first_sentence` |

---

## Ajuste 1 — Exportação XLSX no formato HYB

### 1.1 Estrutura da planilha-modelo HYBIntegrador_bens.xlsx

Aba ativa: `Dados` (linha 1 = cabeçalhos; linhas 2-30 vazias). Aba secundária: `Legenda de Cores`.

Obrigatoriedade por **cor da fonte** do cabeçalho:
- **VERMELHO** `FFFF0000` = Obrigatório
- **VERDE** (theme 9) = Obrigatório somente na edição
- **AZUL** `FF0070C0` = Obrigatório em alguns casos (não aplicado neste template)
- **CINZA** (theme 1, tint 0.5) = Não obrigatório

| Letra | Cabeçalho EXATO | Tipo inferido | Obrigatoriedade | Comentário/Notas críticas |
|---|---|---|---|---|
| A | `Bem/Produto` | string | Obrigatório só em edição (VERDE) | Comentário (autor 'cechin'): identifica registro para UPSERT por Código no HYB. Vazio na criação. |
| B | `Titulo` | string | **Obrigatório** (VERMELHO) | **SEM acento** — não "corrigir" para "Título". NumberFormat aplicado é `m/d/yyyy` (anomalia do template) — gravar como TEXT `s` explícito. |
| C | `Unidade` | string | **Obrigatório** (VERMELHO) | UN, KG, L, etc. |
| D | `Categoria` | string | **Obrigatório** (VERMELHO) | Comentário (autor 'prg2'): "buscará pelo nome registrado da categoria ou pelo ID". Aceita nome ou ID numérico. |
| E | `Código de Barras (EAN)` | string | Não obrigatório (CINZA) | EAN-13/EAN-8/ISBN. Gravar como TEXT `s` para preservar zeros à esquerda. |
| F | `NCM` | string | Não obrigatório (CINZA) | 8 dígitos. Gravar como TEXT `s`. |
| G | `Preço de Venda` | currency | Não obrigatório (CINZA) | Formato BRL `_-"R$" * #,##0.00_-...` (numFmtId 44). |
| H | `Estoque Mínimo` | number | Não obrigatório (CINZA) | Inteiro. |
| I | `Referência` | string | Não obrigatório (CINZA) | SKU/código interno. Hoje pré-preenchido com ISBN-10. |
| J | `Patrimônio(S,N)` | enum | Não obrigatório (CINZA) | Valores 'S' ou 'N' (sem dropdown nativo no template). |
| K | `Depreciação(%)` | number | Não obrigatório (CINZA) | Percentual numérico — formato General (não % nativo). |
| L | `Tipo` | enum | Não obrigatório (CINZA) | Comentário (autor 'prg2'): `Desconhecido`/`Móvel`/`Imóvel`. |
| M | `  Estoque Inicial  -Quantidade` | number | Não obrigatório (CINZA) | **CRÍTICO**: 2 espaços iniciais, 2 espaços antes do `-`, **SEM espaço** após `-`. Preservar EXATO. |
| N | `  Estoque Inicial  - Custo Unitário ` | currency | Não obrigatório (CINZA) | **CRÍTICO**: 2 espaços iniciais, 2 espaços antes do `-`, espaço após `-`, **espaço final** após "Unitário". |
| O | `Descrição` | string | Não obrigatório (CINZA) | Texto livre / descrição longa. |

### 1.2 Estado atual do export

| Item | Onde | Status |
|---|---|---|
| Endpoint disparador | [api/exportar.php:13-39] | OK — aceita 3 modos: `?ids=`, `?apenas_nao_exportados=1`, sem params |
| Use case | [src/Application/ExportarLivrosParaHybService.php:21-58] | OK — busca, gera nome `HYBIntegrador_bens_YmdHis.xlsx`, marca `exportado_em` (efeito colateral) |
| Cabeçalhos exatos | [src/Infrastructure/Adapter/Out/Export/XlsxHybExporter.php:44-60] | OK — espaços de M1/N1 preservados |
| Comentários A1/D1/L1 | [XlsxHybExporter.php:66-80] | OK — textos idênticos ao template |
| TEXT em E/F | [XlsxHybExporter.php:106-107] | OK — `setCellValueExplicit(... TYPE_STRING)` |
| Larguras de coluna | [XlsxHybExporter.php:83-90] | OK — idênticas ao template |
| Aba Legenda | [XlsxHybExporter.php] | OK |
| Mapeamento domínio→XLSX | [src/Domain/Service/MapeadorParaFormatoHyb.php:29-60] | OK para 15 colunas (lê só `hyb` persistido, não aplica defaults) |
| Botão pós-cadastro na bipagem | [public/index.php] | **FALTA** (item 1) |
| Modal de seleção com data/hora | [public/lista.php] | **FALTA** (item 1.4) |
| Histórico de baixas | [sql/schema.sql] tabela `exportacoes_hyb` órfã | **FALTA** (item 1.5) |

### 1.3 Gap e plano

Ordem proposta de implementação:

1. **Criar tabela `export_baixa`** (ver §1.5) substituindo a `exportacoes_hyb` atual (órfã) ou populando-a + nova tabela de itens.
2. **Adapter `MySqlExportacaoRepository`** + porta `ExportacaoRepository` em [src/Domain/Port/Out/].
3. **Ajustar `ExportarLivrosParaHybService`** para, em uma única transação, gravar `export_baixa`, vincular livros e atualizar cache `livros.exportado_em`.
4. **Endpoint `GET /api/livros.php`** passa a expor `criado_em`, `atualizado_em`, `exportado_em` (já existe — só faltam alguns campos no payload da lista).
5. **Modal "Livros para exportar"** em [public/lista.php] (ver §1.4) substituindo os 3 botões atuais.
6. **Botão "Baixar esta bipagem"** em [public/index.php] que chama `?ids=<id_recém_criado>` após save bem-sucedido (atalho rápido para o operador).
7. **Quantidade editável por linha** no modal — sobrescreve `hyb_estoque_ini_qtd` antes da exportação (decisão em aberto — ver §Decisões).

### 1.4 Modal "Livros para exportar"

UX proposta para substituir os 3 botões atuais em `lista.php`:

- Botão único **"Exportar para HYB"** abre modal full-screen ou centralizado (overlay 80vh).
- Tabela paginada (reaproveita paginação atual de 50) com colunas:
  - `[ ]` Checkbox de seleção
  - Capa miniatura
  - Título (+ subtítulo)
  - Autores
  - ISBN-13
  - **Última baixa em** (`exportado_em` formatado `dd/MM/yyyy HH:mm`; se nulo, "—")
  - **Re-baixar?** (badge automático; explicação abaixo)
  - **Qtd** (`<input type="number" min="1" step="1" value="1">` — default 1 ou `hyb_estoque_ini_qtd` salvo)
- **Comportamento dos checkboxes** (resposta direta ao requisito):
  - Livros **sem** `exportado_em` → checkbox **MARCADO** por padrão
  - Livros **com** `exportado_em` → checkbox **DESMARCADO** por padrão (mas operador pode marcar para re-baixar)
- Filtros no topo do modal: busca, editora, ano, "Mostrar apenas: [todos | não baixados | já baixados]".
- Rodapé: "X selecionados de Y" + botão **"Gerar XLSX (X livros)"**.
- Ao clicar gerar: POST `/api/exportar.php` com body `{ ids:[...], quantidades:{id1:qtd1,...} }`. Resposta dispara download e atualiza o modal (recarrega para mostrar nova "Última baixa em").

### 1.5 Persistência de baixas

**Decisão #13 confirmada**: modelo **batch + itens** reaproveitando a tabela `exportacoes_hyb` existente como cabeçalho e criando `exportacoes_hyb_itens` para o detalhamento.

DDL proposto:

```sql
-- Cabeçalho (1 registro por arquivo XLSX gerado)
-- Adapta a tabela exportacoes_hyb órfã existente — verificar colunas atuais e fazer ALTER se necessário
CREATE TABLE IF NOT EXISTS exportacoes_hyb (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  arquivo VARCHAR(255) NOT NULL,             -- nome do XLSX gerado (HYBIntegrador_bens_YmdHis.xlsx)
  qtd_registros INT UNSIGNED NOT NULL,       -- contagem de livros incluídos
  gerado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario VARCHAR(100) NULL,                 -- preenchido quando houver login (preparado para web)
  origem ENUM('lista','atalho_bipagem') NOT NULL DEFAULT 'lista',
  INDEX idx_gerado_em (gerado_em DESC),
  INDEX idx_arquivo (arquivo)
);

-- Itens (N registros — 1 por livro de cada batch)
CREATE TABLE exportacoes_hyb_itens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exportacao_id BIGINT UNSIGNED NOT NULL,    -- FK para o cabeçalho
  livro_id BIGINT UNSIGNED NOT NULL,
  quantidade INT UNSIGNED NULL,              -- snapshot da Qtd usada NESTA baixa (decisão #12)
  CONSTRAINT fk_item_exportacao FOREIGN KEY (exportacao_id) REFERENCES exportacoes_hyb(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_livro FOREIGN KEY (livro_id) REFERENCES livros(id) ON DELETE CASCADE,
  INDEX idx_livro_baixa (livro_id),
  INDEX idx_exportacao (exportacao_id)
);
```

**Comportamento**:
- A baixa só é registrada **após** `Writer::save()` retornar com sucesso (decisão #1) — se o write falhar, transação rolla back e nenhum livro fica como baixado.
- `livros.exportado_em` continua sendo atualizado como **cache da última baixa** (performance no filtro `apenas_nao_exportados` e badge na lista). Mantido na mesma transação.
- Para saber "todas as vezes que o livro X foi baixado": `SELECT eh.* FROM exportacoes_hyb eh JOIN exportacoes_hyb_itens ei ON ei.exportacao_id = eh.id WHERE ei.livro_id = ? ORDER BY eh.gerado_em DESC`.
- A quantidade no item (`exportacoes_hyb_itens.quantidade`) é o **snapshot** da Qtd usada naquela baixa (decisão #12) — NÃO sobrescreve `livros.hyb_estoque_ini_qtd`.
- Retenção: sem purga (decisão #8).

---

## Ajuste 2 — Categorias selecionáveis

### 2.1 Estrutura da planilha Categorias (3).xlsx

Aba `Dados`, 40 linhas (1 cabeçalho + 39 categorias).

| Letra | Cabeçalho | Tipo | Amostra | Interpretação semântica |
|---|---|---|---|---|
| A | `Código` | number (int 2-3 dígitos) | 96, 98, 100, 84, 188 | **ID interno** da categoria no HYB (provável PK). NÃO sequencial, NÃO codifica hierarquia. 39 valores únicos. |
| B | `Nome` | string | "Filosofia/Psicologia", "Literatura Brasileira", "HQs e Gibis" | Descrição textual. 38 únicos (1 duplicado: "Geografia/Biografia/História/Viagens" em códigos 133 e 152). A barra `/` é separador de assuntos afins, **não** é hierarquia. |
| C | `Índice` | number (decimal "dotted") | 9, 9.01, 9.02, 9.1, 9.38 | **Hierarquia em notação pai.filho**. Profundidade 2: raiz `9` (Referência Biblioteca) + 38 filhos `9.01`–`9.38`. **Armadilha**: armazenado como float → perde zeros à direita (`9.10` vira `9.1`, `9.20` vira `9.2`). Para preservar, ler como string formatada. |

Hierarquia: **2 níveis apenas**. 1 raiz + 38 filhos diretos. Sem netos. Relação pai-filho é **implícita** pelo prefixo `floor(indice)`.

Armadilhas de importação:
- Índice como float perde zeros (`9.10`→`9.1`) — preservar como string com `sprintf('%.2f', $v)` ou ler valor formatado da planilha.
- Nome duplicado (`Geografia/...`) — usar `Código` como chave única.
- Espaços duplos em "Brinquedos e  Jogos" (linha 32).
- Possível truncamento em "Literatura Oriental (...Ta" (linha 37, 103 chars).
- UTF-8 com acentos e símbolos `/`, `+`, `()`.

### 2.2 Modelagem proposta

DDL sugerido:

```sql
CREATE TABLE categoria (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo INT UNSIGNED NOT NULL UNIQUE,        -- coluna A da planilha (ID no HYB)
  indice VARCHAR(10) NOT NULL,                -- coluna C como string ("9", "9.01", "9.10")
  descricao VARCHAR(255) NOT NULL,            -- coluna B
  parent_id INT UNSIGNED NULL,                -- auto-FK para hierarquia (raiz = NULL)
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_categoria_parent FOREIGN KEY (parent_id) REFERENCES categoria(id) ON DELETE SET NULL,
  INDEX idx_indice (indice),
  INDEX idx_parent (parent_id)
);

ALTER TABLE livros
  ADD COLUMN categoria_id INT UNSIGNED NULL AFTER hyb_categoria,
  ADD CONSTRAINT fk_livro_categoria FOREIGN KEY (categoria_id) REFERENCES categoria(id) ON DELETE SET NULL,
  ADD INDEX idx_livro_categoria (categoria_id);
-- hyb_categoria VARCHAR(255) PERMANECE como snapshot do nome no momento do save (HYB aceita nome ou ID)
```

Por que `parent_id` mesmo com apenas 2 níveis: caso o HYB adicione netos futuramente, a estrutura suporta sem migração. Para os 39 registros atuais, o pai de tudo é a raiz `9` (Referência Biblioteca).

### 2.3 Ingestão inicial

Opção recomendada: **comando PHP de import** standalone (`scripts/importar_categorias.php`), idempotente:
- Lê `Docs_para_Consulta/Categorias (3).xlsx` via PhpSpreadsheet.
- Para cada linha 2..40:
  - Lê `Código`, `Nome`, `Índice` (formatado como string).
  - Se `Índice` for inteiro puro (`9`) → `parent_id = NULL`.
  - Caso contrário → resolve `parent_id` por `floor(indice)` (busca categoria onde `indice = floor(valor)`).
  - `INSERT … ON DUPLICATE KEY UPDATE descricao=..., indice=..., parent_id=...` (chave única em `codigo`).
- Executa via `php scripts/importar_categorias.php`.

Por que não SQL puro no `schema.sql`: a planilha pode ser atualizada (vide nome do arquivo "Categorias (3).xlsx" sugerindo 3ª versão); um script PHP permite re-importar quando vier nova versão sem editar SQL.

### 2.4 Integração no fluxo

- **Novo endpoint** `GET /api/categorias.php` → retorna `[{id, codigo, indice, descricao, parent_id}, ...]` ordenado por `indice`.
- **Front [public/index.php] linhas 85-87**: trocar `<input type="text" name="categoria">` por `<select name="categoria_id">` populado no load via fetch. Manter `<option value="">— selecione —</option>` no topo.
- **app.js `preencherHyb()`**: para `<select>`, se o valor salvo (`hyb.categoria_id`) não existir entre as `<option>`, criar option dinâmica preservando o valor legado.
- **app.js `coletarHyb()`**: envia `categoria_id` (int) em vez de `categoria` (string). Backend deve aceitar ambos durante migração.
- **Default do `.env` (decisão #3 confirmada)**: adicionar `HYB_CATEGORIA_DEFAULT_ID` (int — código numérico, ex.: `96` para Referência Biblioteca) com **fallback para `HYB_CATEGORIA_DEFAULT`** (string) caso o ID não exista no banco. No bootstrap, validar `HYB_CATEGORIA_DEFAULT_ID` contra `categoria.codigo`; se não bater, logar warning e usar a string.
- **`MapeadorParaFormatoHyb` coluna D (decisão #11 confirmada)**: escreve **nome** (`categoria.descricao`) — legível e auditável ao abrir o XLSX manualmente. O HYB aceita "nome ou ID" segundo comentário do template.
- **Migração de dados legados**: script paralelo que extrai `DISTINCT(hyb_categoria)` de `livros`, tenta match exato com `categoria.descricao` e popula `livros.categoria_id`; o que não bater fica nulo para o operador revisar.

---

## Ajuste 3 — Câmera para leitura de código de barras

### 3.1 Esclarecimento técnico importante

**Navegadores Web NÃO têm acesso ao "validador de código de barras nativo do Windows".** O Windows tem o app Câmera embutido e a API `Windows.Media.Capture.BarcodeScanner` (UWP), mas estes só são acessíveis por apps nativos (UWP/WinUI/.NET), **não por páginas web**.

O que existe no browser:

| Recurso | Padrão | Suporte | O que faz |
|---|---|---|---|
| `navigator.mediaDevices.getUserMedia()` | W3C Media Capture | Universal (Chrome/Edge/Firefox/Safari) | Solicita permissão e abre stream da webcam/câmera traseira |
| `BarcodeDetector` API | WICG Shape Detection | Chrome/Edge (Chromium) — **nativo no Windows** | Decodifica EAN-13, EAN-8, QR, code-128, ITF, UPC, etc. sem biblioteca externa. Confiável para EAN-13 (ISBN). |
| ZXing-js (`@zxing/browser`) | Lib JS pura | Universal | Fallback para Firefox/Safari que não têm `BarcodeDetector` |
| Quagga2 | Lib JS | Universal mas mais lenta | Alternativa ao ZXing |

**Requisito de contexto seguro**: `getUserMedia` exige **HTTPS ou localhost**. Decisão #10 confirma: a aplicação **vai migrar para HTTPS** (objetivo é virar aplicação web). Em produção: certificado Let's Encrypt; em dev: certificado autoassinado ou tunneling (ngrok/cloudflared) para testar.

**Decisão #6 confirmada**: usar **somente `BarcodeDetector`** (nativa Chrome/Edge). **Sem fallback ZXing-js** nesta fase. Para browsers sem suporte (`'BarcodeDetector' in window === false`), mostrar mensagem clara:

> "Esta funcionalidade exige Chrome ou Edge. No seu navegador, use o leitor de código de barras USB ou digite o ISBN manualmente."

ZXing-js entra no backlog para quando houver demanda real.

### 3.2 UX proposta

- **Botão "Bipar com câmera"** ao lado direito de `#campo-isbn` em [public/index.php] (com ícone de câmera).
- Ao clicar:
  1. Solicita permissão via `getUserMedia({video:{facingMode:'environment'}, audio:false})`.
  2. Se aprovado, abre **overlay modal** com:
     - `<video>` em primeiro plano (~400x300px ou full em mobile).
     - **Linha-guia horizontal** vermelha no centro (orienta posicionamento do EAN).
     - Texto: "Aponte o código de barras para a linha vermelha".
     - Botão "Cancelar" no canto.
  3. Inicia loop de detecção:
     - `BarcodeDetector` com `formats:['ean_13','ean_8']` → `detect(video)` a cada `requestAnimationFrame`.
     - Ao primeiro hit válido (EAN-13 com 13 dígitos e checksum OK), para o loop.
  4. Toca beep (reaproveita `beepOk` de [app.js]), fecha overlay, para o stream (`track.stop()`).
  5. Insere valor em `#campo-isbn` e dispara `consultarIsbn(ean)`.
- **Pausar `focarInput()` agressivo** enquanto a câmera está ativa (hoje [app.js:256-261] rouba foco em qualquer clique fora de input/textarea/select/button/a — vai brigar com o overlay).
- **Bloquear leitor USB** enquanto a câmera está ativa (desabilitar Enter handler para evitar dupla consulta).
- **Tratamento de erro**: se permissão negada, mostra toast "Permissão de câmera negada" e volta foco ao input.

### 3.3 Arquivos a tocar

| Arquivo | Mudança |
|---|---|
| [public/index.php] | Adicionar botão "Bipar com câmera", markup do overlay (`<dialog>` ou `<div>` posicionada), elemento `<video>` |
| [public/assets/css/style.css] | Estilos do overlay, linha-guia, botão, animação |
| [public/assets/js/app.js] | Função `iniciarCameraEDecodificar()`, detecção de suporte BarcodeDetector, mensagem clara para browsers sem suporte (decisão #6 — sem ZXing-js), pausa do `focarInput`, integração com `consultarIsbn` |
| HTTP headers (Apache `.htaccess` ou `index.php`) | `Permissions-Policy: camera=(self)` |
| Apache vhost / Docker | Configuração HTTPS (decisão #10) — autoassinado em dev, Let's Encrypt em prod |

---

## Ajuste 4 — Câmera → Consulta automática

### 4.1 Fluxo

```
[Operador clica "Bipar com câmera"]
         │
         ▼
[Permissão getUserMedia]
         │
         ▼
[Overlay com video + linha-guia]
         │
         ▼
[BarcodeDetector.detect() em loop]
         │
         ▼ (primeiro EAN-13 válido)
[validarChecksumIsbn13(ean)]   ◀── §4.2
         │ válido
         ▼
[Beep + fecha overlay + para stream]
         │
         ▼
[POST /api/consultar.php { isbn: ean }]   ◀── MESMA chamada do leitor USB
         │
         ▼
[Renderiza #painel-livro]
         │
         ▼
[preencherHyb(hyb_defaults)]
         │
         ▼
[Formulário HYB liberado abaixo]
   - Categoria (dropdown — Ajuste 2)
   - Quantidade (input number — Extra E2)
   - Demais campos complementares
         │
         ▼
[focarInput() volta a estar ativo]
```

Reaproveita 100% do pipeline atual: o EAN decodificado entra em `consultarIsbn(isbn)` em [app.js:126-131], que já chama `POST /api/consultar.php` e renderiza painéis. **Nenhuma mudança no backend de consulta** — apenas no front.

### 4.2 Validação

Antes de chamar a API, validar:https://localhost:8443
1. **Comprimento**: 13 dígitos exatos (ou 10 para ISBN-10 / 8 para EAN-8 conforme suporte).
2. **Checksum ISBN-13** (Mod 10 com pesos 1,3,1,3,...): reaproveitar/criar `validarIsbn13(str)` em [app.js].
3. **Prefixo**: ISBNs começam com `978` ou `979`. Se vier outro EAN-13 (ex.: produto comercial), pode-se ou (a) bloquear com aviso "Não é um livro", ou (b) tentar consulta mesmo assim (decisão em aberto — §Decisões).

Se inválido: não fecha overlay, exibe toast "Código inválido, tente novamente" e segue detectando.

---

## Extras

### E1 — Enriquecimento de APIs

Campos atualmente capturados pelos adapters mas **NÃO usados** em nenhuma coluna HYB nem na descrição auto-gerada (`MapeadorParaFormatoHyb`/`gerarDescricaoSimples` em [api/consultar.php]):

| Campo candidato | API que retorna | Coluna HYB destino | Impacto | Esforço |
|---|---|---|---|---|
| `subtitulo` (já vai concatenado em B) | Todas | O (Descrição) | Médio — sinopse mais completa | Baixo (já no domínio) |
| `idioma` legível ("Português") | Todas (normalizar) | O (Descrição) | Baixo — útil em filtragem futura | Médio (normalizar `por`→`pt-BR`) |
| `formato` | BrasilAPI (traduzido), Google (cru), Open Library (não usado) | O (Descrição) | Médio | Baixo |
| `paginas` | Todas | O (Descrição) | Já em uso parcial | — |
| `dimensoes` (alt/larg/esp em cm) | BrasilAPI, Google, Open Library | O (Descrição) | Baixo | Baixo |
| `peso` | Open Library (string crua "1 pounds") | O (Descrição) | Baixo — exige normalização | Médio |
| `assuntos` / `categorias` | Todas | **D (Categoria)** como sugestão de match + O | **Alto** — pode pré-selecionar categoria no dropdown | Médio |
| `local_publicacao` | BrasilAPI, Open Library | O (Descrição) | Baixo | Baixo |
| `link_preview` | Google `previewLink`, Open Library `key` | n/a (UI só) | Baixo | Baixo |
| `avaliacao_media` + `qtd_avaliacoes` | Google Books | O (Descrição) opcional | Baixo | Baixo |

Campos **NÃO capturados hoje** pelos adapters (estão no `payload_bruto` mas não no `DadosBibliograficos`):

| Campo | API | Coluna destino sugerida | Impacto |
|---|---|---|---|
| `contributors[{role,name}]` (tradutor, ilustrador, editor) | Open Library | O (Descrição) — "Tradução: X. Ilustrações: Y" | **Alto** — pedido explicitamente |
| `maturityRating` | Google Books | O (Descrição) — "Classificação: NOT_MATURE/MATURE" | Médio — granularidade pobre (binário) |
| `mainCategory` / `categories[0]` | Google Books | **D (Categoria)** sugestão | Alto |
| `physical_format` (Paperback/Hardcover) | Open Library | preenche `formato` (hoje null para OL) | Médio |
| `edition_name`, `series`, `pagination` | Open Library | O (Descrição) | Médio |
| `first_sentence` | Open Library | Fallback de sinopse | Baixo |
| `searchInfo.textSnippet` | Google Books | Fallback de sinopse | Baixo |
| `panelizationSummary` (HQ?) | Google Books | sinal lógico para Categoria=HQ | Baixo |

**Recomendação E1**: priorizar (a) `contributors` da Open Library (tradutor/ilustrador são pedido explícito); (b) `mainCategory`/`categories` Google para pré-selecionar dropdown D; (c) normalizar idioma entre adapters; (d) Descrição (coluna O) virar template configurável (mistura de campos) em vez de concatenação cega — limite de 32k chars do XLSX.

### E2 — Campo quantidade

Pedido: campo numérico que vai para a coluna correspondente do HYB.

**Mapeamento**: a coluna do template HYB que casa com "quantidade" é **M `  Estoque Inicial  -Quantidade`** (já existe e já é mapeada de `hyb.estoqueInicialQtd`).

**Decisões aplicáveis**:
- #5: "quantidade" = **exemplares físicos editáveis** (default 1). Sem contador automático em bipagens repetidas. `ON DUPLICATE KEY UPDATE` continua sobrescrevendo.
- #12: o modal de baixa permite override por linha, **sem persistir** em `livros`. O override vai apenas para `exportacoes_hyb_itens.quantidade` daquela baixa.

Plano:
- **Domínio**: `CamposHyb::$estoqueInicialQtd` passa de `?float` para `?int`. Renomear getter para `getQuantidade()` mantendo `getEstoqueInicialQtd()` como alias deprecated durante migração.
- **UI [public/index.php] linhas 126-128**: trocar `<input type="text" inputmode="decimal">` por `<input type="number" min="1" step="1" value="1">` com label visível **"Quantidade"** (separada do bloco de campos avançados HYB, próxima ao painel principal).
- **Default**: `HYB_ESTOQUE_INICIAL_QTD_DEFAULT=1` no `.env` já cobre.
- **Aparição na UI**: imediatamente após o `#painel-livro` renderizar (pós-câmera ou pós-USB), antes dos demais campos HYB avançados. Operador pode editar antes de salvar.
- **Modal de baixa (§1.4)**: coluna `Qtd` editável por linha — valor inicial = `livros.hyb_estoque_ini_qtd` (ou 1 se nulo); o que o operador editar vai para `exportacoes_hyb_itens.quantidade` desta baixa específica.

### E3 — Tela/modal de baixas

Já detalhado em **§1.4 Modal "Livros para exportar"**. Reaproveita endpoint `GET /api/livros.php` (com novos campos `exportado_em` formatado e talvez histórico de baixas via JOIN com `export_baixa`).

---

## Decisões confirmadas

> Validadas pelo usuário em 2026-06-02. Implementação deve seguir estes parâmetros.

| # | Tópico | Decisão | Implicação |
|---|---|---|---|
| 1 | Definição de "baixa" | ✅ **Geração efetiva do XLSX** marca como baixado | `INSERT` em `exportacoes_hyb_itens` acontece na mesma transação que escreve o arquivo no disco. Clique de download no browser NÃO é rastreado. |
| 2 | Atalho pós-cadastro | ✅ **Criar botão** "Salvar e baixar XLSX deste livro" em [public/index.php] | Após save bem-sucedido, exibe botão que chama `/api/exportar.php?ids=<id_recém_criado>`. Atalho rápido para operador. |
| 3 | Default da Categoria no `.env` | ✅ **`HYB_CATEGORIA_DEFAULT_ID`** (ID numérico) com **fallback para string** se ID não existir | Adicionar nova var; manter `HYB_CATEGORIA_DEFAULT` como fallback durante transição. Default sugerido: 96 (Referência Biblioteca). |
| 4 | Hierarquia no dropdown | ✅ **Linear simples** ordenado por `indice` | 39 `<option>` no formato `"9.05 — HQs e Gibis"`. Sem `<optgroup>`, sem componente de árvore. |
| 5 | Significado de "quantidade" | ✅ **Exemplares físicos** (editável, default 1) | Campo numérico inteiro na UI; vai direto para coluna M. **Reaproveita** `hyb_estoque_ini_qtd`. SEM contador automático em bipagens repetidas. |
| 6 | Fallback de câmera | ✅ **BarcodeDetector + ZXing-js lazy** (revisada em 2026-06-03) | Detecta `'BarcodeDetector' in window`: usa nativo no Edge Windows / Chrome macOS+Android; **carrega `@zxing/library@0.21.3` do CDN jsdelivr sob demanda** em Chrome Windows/Linux e Firefox. Decisão original era "só BarcodeDetector" mas precisou ser revisada porque o Chrome desktop no Windows **não tem** `BarcodeDetector` nativo (só macOS via Vision Framework do sistema). |
| 7 | EAN não-livro (sem prefixo 978/979) | ✅ **Tentar consulta sempre** | API retorna "não encontrado" e operador bipa de novo. Sem bloqueio prévio. |
| 8 | Retenção do histórico de baixas | ✅ **Manter para sempre** | Sem purge automático. Pode virar `.env` configurável quando for produção. |
| 9 | Defaults `.env` na exportação | ✅ **Manter comportamento atual** | `MapeadorParaFormatoHyb` continua NÃO aplicando defaults no export — se operador apagou no form, vai vazio. Mais previsível. |
| 10 | HTTPS | ✅ **Sim — migrar para HTTPS** | Intuito do usuário: transformar em **aplicação web** (acessível pela internet). Câmera funcionará. Implica configurar certificado (Let's Encrypt em produção; autoassinado em dev). |
| 11 | Categoria no XLSX (col D) | ✅ **Nome** (texto) | `MapeadorParaFormatoHyb` escreve `categoria.descricao` na coluna D. Legível e auditável ao abrir o XLSX manualmente. |
| 12 | Quantidade no modal de baixa | ✅ **Override só na baixa** | Edição de Qtd no modal NÃO atualiza `livros.hyb_estoque_ini_qtd`. Snapshot da quantidade vai para `exportacoes_hyb_itens.quantidade` desta baixa específica. |
| 13 | Modelagem de histórico | ✅ **Batch + Itens** — reaproveitar `exportacoes_hyb` como cabeçalho de batch + criar `exportacoes_hyb_itens` para os livros | Aproveita tabela existente. Cada arquivo gerado = 1 linha em `exportacoes_hyb`; cada livro daquele arquivo = 1 linha em `exportacoes_hyb_itens`. |

### Implicações cruzadas das decisões

- **Decisão #5 + #12**: o domínio mantém `CamposHyb::$estoqueInicialQtd` mas renomeia conceitualmente para `quantidade` na UI. O modal de baixa permite override de `quantidade` por linha, mas esse override não escreve em `livros` — vai apenas para o snapshot em `exportacoes_hyb_itens.quantidade` e daí para a coluna M do XLSX.
- **Decisão #10 + #3**: como vai virar aplicação web, o ID da categoria default no `.env` precisa ser **válido no banco** após a importação inicial. Adicionar verificação no bootstrap: se `HYB_CATEGORIA_DEFAULT_ID` não existe em `categoria`, logar warning e cair no fallback `HYB_CATEGORIA_DEFAULT` (string).
- **Decisão #13 + #1**: a tabela `exportacoes_hyb` (batch) ganha colunas `qtd_registros`, `gerado_em`, `arquivo`. A baixa só é registrada após o `Writer::save()` retornar com sucesso — se o write falhar, transação rolla back e nenhum livro é marcado.
- **Decisão #6 + #10**: HTTPS resolve o requisito de contexto seguro para `getUserMedia`. Sem ZXing-js, o cenário "outro browser" precisa de mensagem clara: "Esta funcionalidade exige Chrome ou Edge."

---

## Decisões pendentes — mapeamento coluna-a-coluna

Ainda preciso da sua validação nas **16 perguntas de mapeamento** abaixo (§"Perguntas de mapeamento coluna-a-coluna" — replicadas aqui em formato resumido para resposta rápida). Estas não estavam no bloco de 13 que você respondeu.

| # | Coluna HYB | Pergunta resumida | Recomendação |
|---|---|---|---|
| M1 | A — Bem/Produto | Vazio na criação? | ✅ Sim |
| M2 | B — Titulo | Manter "Titulo" sem acento? | ✅ Sim (template original) |
| M3 | B — Titulo | Continuar `titulo — subtitulo`? | ✅ Sim (atual) |
| M4 | C — Unidade | UN default editável? | ✅ Sim (atual) |
| M5 | D — Categoria | Nome no XLSX? | ✅ Já confirmado em Decisão #11 (nome) |
| M6 | E — EAN | ISBN-13 da API, não editável? | ✅ Sim (atual) |
| M7 | F — NCM | Default fixo no .env, sem campo na UI? | Sugiro **manter editável** (atual) — operador pode ajustar para casos atípicos |
| M8 | G — Preço | Pré-preencher com `api.preco.valor`? | ✅ Sim (atual) |
| M9 | H — Estoque Mínimo | Editável (atual) ou fixo 0? | ✅ Editável |
| M10 | I — Referência | ISBN-10 (atual) ou ID interno? | ✅ ISBN-10 |
| M11 | J — Patrimônio | Default N editável? | ✅ Sim |
| M12 | K — Depreciação | Vazio sempre? | ✅ Sim |
| M13 | L — Tipo | Dropdown 3 opções, default Móvel? | ✅ Sim |
| M14 | M — Quantidade | Reaproveita `estoqueInicialQtd` (renomeia UI para "Quantidade")? | ✅ Já implícito na Decisão #5 — reaproveita |
| M15 | N — Custo Unitário | Vazio (atual), espelhar G, ou editável sem auto-fill? | Sugiro **editável sem auto-fill** — livro doado tem custo 0 ou simbólico |
| M16 | O — Descrição | Manter simples, incluir enriquecimento E1, ou template configurável? | Sugiro **incluir enriquecimento E1** (tradutor, ilustrador, edição) — alinha com pedido extra |

> **Como responder**: pode responder em bloco (ex.: "M1-M6: ok como sugerido; M7: editável; ..."). As que ficarem "ok" eu assumo a recomendação.

---

## Mapeamento de colunas HYB ↔ origem de dados (provisório)

Mapeamento provisório (precisa validação do usuário nos casos marcados ⚠️):

| Col | Cabeçalho | Origem proposta | Status |
|---|---|---|---|
| A | `Bem/Produto` | Vazio (só na edição HYB) | OK — mantém atual |
| B | `Titulo` | `api.titulo` + " — " + `api.subtitulo` (estado atual) | ⚠️ pendente M2/M3 (sem acento? concatenar?) |
| C | `Unidade` | `hyb.unidade` (default `.env` = "UN") | ⚠️ pendente M4 |
| D | `Categoria` | `categoria.descricao` (nome via FK `livros.categoria_id`) | ✅ Decisão #11 — **nome** |
| E | `Código de Barras (EAN)` | `api.isbn13` (TEXT) | ⚠️ pendente M6 |
| F | `NCM` | `hyb.ncm` (default `.env` = "4901.99.00") | ⚠️ pendente M7 |
| G | `Preço de Venda` | `hyb.precoVenda` (pré-preenchido com `api.preco.valor` no form) | ⚠️ pendente M8 |
| H | `Estoque Mínimo` | `hyb.estoqueMinimo` (default `.env` = 0) | ⚠️ pendente M9 |
| I | `Referência` | `hyb.referencia` (pré-preenchido com `api.isbn10`) | ⚠️ pendente M10 |
| J | `Patrimônio(S,N)` | `hyb.patrimonio` (default `.env` = "N") | ⚠️ pendente M11 |
| K | `Depreciação(%)` | `hyb.depreciacaoPct` | ⚠️ pendente M12 |
| L | `Tipo` | `hyb.tipo` (default `.env` = "Móvel") | ⚠️ pendente M13 |
| M | `  Estoque Inicial  -Quantidade` | `hyb.estoqueInicialQtd` renomeado como **"Quantidade"** na UI (input numérico inteiro, default 1) | ✅ Decisão #5 — reaproveita estoqueInicialQtd |
| N | `  Estoque Inicial  - Custo Unitário ` | `hyb.estoqueInicialCusto` | ⚠️ pendente M15 (vazio / espelhar G / editável sem auto-fill?) |
| O | `Descrição` | `gerarDescricaoSimples` (atual) **+ enriquecimento E1** (tradutor, ilustrador, edição, classificação etária, etc.) | ⚠️ pendente M16 (manter simples / E1 / template configurável?) |

### Perguntas de mapeamento coluna-a-coluna (resposta consolidada acima na seção "Decisões pendentes — mapeamento coluna-a-coluna")

As decisões #5 e #11 já cobriram as colunas D e M. Restam M1, M2, M3, M4, M6–M13, M15 e M16 — todos com recomendação na tabela acima.

---

## Critérios de aceitação por ajuste

**Ajuste 1 (Export XLSX HYB)**:
- [ ] XLSX gerado tem 15 colunas A–O com cabeçalhos byte-a-byte iguais ao template (incluindo espaços críticos em M1/N1).
- [ ] Comentários em A1, D1, L1 preservados.
- [ ] EAN (col E) e NCM (col F) gravados como TEXT (preservam zeros à esquerda).
- [ ] Modal em `lista.php` mostra "Última baixa em" formatado para cada livro.
- [ ] Livros sem baixa vêm com checkbox MARCADO; livros com baixa vêm DESMARCADO mas remarcáveis.
- [ ] Qtd editável por linha (default 1), vai para coluna M do XLSX.
- [ ] Após gerar, tabela `export_baixa` recebe N inserts (1 por livro exportado) na mesma transação que atualiza `livros.exportado_em`.
- [ ] Botão "Baixar esta bipagem" em `index.php` gera XLSX só com o livro recém-salvo.

**Ajuste 2 (Categorias)**:
- [ ] Tabela `categoria` criada e populada com 39 registros (1 raiz + 38 filhos).
- [ ] Coluna `livros.categoria_id` com FK para `categoria.id`.
- [ ] Dropdown em `index.php` populado via `GET /api/categorias.php` ordenado por `indice`.
- [ ] Categoria salva no banco via `categoria_id`; coluna D do XLSX recebe `descricao` (snapshot).
- [ ] Script de migração para livros legados rodado (match exato de `hyb_categoria` → `categoria_id`).

**Ajuste 3 (Câmera)**:
- [ ] Botão "Bipar com câmera" em `index.php`.
- [ ] Permissão solicitada via `getUserMedia({video:{facingMode:'environment'}})`.
- [ ] Overlay com `<video>` e linha-guia visível.
- [ ] BarcodeDetector decodifica EAN-13 corretamente.
- [ ] `focarInput()` pausado enquanto câmera ativa.
- [ ] Tratamento de erro de permissão (toast).

**Ajuste 4 (Câmera → Consulta)**:
- [ ] EAN-13 detectado dispara `consultarIsbn(ean)` automaticamente.
- [ ] Validação de checksum ISBN-13 antes de chamar API.
- [ ] Câmera fecha + stream encerrado ao detectar primeiro válido.
- [ ] Painel HYB liberado abaixo com defaults pré-preenchidos.

**Extras**:
- [ ] (E1) Open Library `contributors` capturado em `DadosBibliograficos` e incluído na coluna O.
- [ ] (E1) Google `mainCategory`/`categories` usado como sugestão no dropdown D.
- [ ] (E1) Idioma normalizado para BCP-47 entre os 3 adapters.
- [ ] (E2) Campo `<input type="number" min="1" step="1" value="1">` visível pós-consulta, persistido em coluna M.
- [ ] (E3) Coberto por Ajuste 1.

---

## Plano de implementação faseado

**Fase 0 — Pré-requisitos**
- ✅ Decisões #1-#13 confirmadas pelo usuário.
- ⚠️ Pendente: respostas das 16 perguntas de mapeamento coluna-a-coluna (decisão M5/M14 já cobertas por #5 e #11).
- ⚠️ Pendente: confirmar uso das recomendações para M1-M4, M6-M13, M15, M16.

**Fase 1 — Schema + Categorias (Ajuste 2)** — base para tudo
1. DDL: `CREATE TABLE categoria`, `ALTER TABLE livros ADD categoria_id`.
2. Script `scripts/importar_categorias.php` + execução.
3. Domínio: `src/Domain/Entity/Categoria.php`, `src/Domain/Port/Out/CategoriaRepository.php`.
4. Adapter: `src/Infrastructure/Adapter/Out/Persistence/MySqlCategoriaRepository.php`.
5. Use case: `ListarCategoriasUseCase` + `Service`.
6. Endpoint: `api/categorias.php`.
7. Refactor `CamposHyb`: `?int $categoriaId` substituindo `?string $categoria` (manter `categoria_snapshot` opcional).
8. Front: trocar input por `<select>` em `index.php`; ajustar `preencherHyb`/`coletarHyb` em `app.js`.
9. `MapeadorParaFormatoHyb`: resolver `Categoria` para escrever nome na coluna D.
10. Script de migração de dados legados.

**Fase 2 — Quantidade (Extra E2)** — depende de Fase 1 (UI já mexida)
1. **Decisão #5**: reaproveita `hyb_estoque_ini_qtd` — sem DDL adicional. Trocar tipo no domínio (`?float` → `?int`).
2. Front: campo `<input type="number" min="1" step="1" value="1">` em `index.php` próximo ao painel principal, label "Quantidade".
3. Backend: `SalvarLivroService` aceita quantidade; mapeador escreve em coluna M; `ON DUPLICATE KEY UPDATE` continua sobrescrevendo (não incrementa).

**Fase 3 — Histórico de baixas + Modal (Ajuste 1)** — depende de Fase 2 (Qtd editável)
1. **DDL (decisão #13)**: ajustar `exportacoes_hyb` como cabeçalho de batch + `CREATE TABLE exportacoes_hyb_itens`. Ver §1.5.
2. Porta + Adapter `ExportacaoRepository` / `MySqlExportacaoRepository`.
3. Refactor `ExportarLivrosParaHybService`:
   - **Decisão #1**: registrar batch e itens **após** `Writer::save()` retornar com sucesso. Transação cobre INSERT em `exportacoes_hyb` + N INSERTs em `exportacoes_hyb_itens` + UPDATE `livros.exportado_em`.
   - **Decisão #12**: ler quantidade override do payload do modal e gravar em `exportacoes_hyb_itens.quantidade` (sem tocar em `livros`).
4. Endpoint `api/livros.php` expõe `exportado_em` formatado + última baixa.
5. Modal em `lista.php` + JS de seleção/qtd/filtro (default: não-baixados marcados, já-baixados desmarcados — §1.4).
6. **Decisão #2**: botão atalho "Salvar e baixar XLSX deste livro" em `index.php`.

**Fase 4 — Câmera (Ajustes 3 + 4)** — depende de HTTPS (Fase 4.0)
1. **Fase 4.0 — HTTPS (decisão #10)**: configurar certificado autoassinado no Docker (dev) e Let's Encrypt no Apache vhost (produção). Headers `Permissions-Policy: camera=(self)`.
2. Markup overlay + CSS em `index.php`/`style.css`.
3. Função `iniciarCameraEDecodificar()` em `app.js`.
4. **Decisão #6**: detecção de suporte `BarcodeDetector`. Browsers sem suporte → mensagem clara (sem ZXing-js).
5. Validação ISBN-13 (checksum). **Decisão #7**: NÃO bloquear EAN sem prefixo 978/979 — tenta consulta sempre.
6. Integração com `consultarIsbn` existente.
7. Pausa de `focarInput` + bloqueio de Enter do input durante câmera.

**Fase 5 — Enriquecimento APIs (Extra E1)** — independente, baixo risco
1. Capturar `contributors` em [OpenLibraryClient].
2. Capturar `mainCategory`/`maturityRating` em [GoogleBooksClient].
3. Normalizar idioma (`por`→`pt-BR`) em todos adapters.
4. Refatorar `gerarDescricaoSimples` para template configurável.
5. (Opcional) Migração que reprocessa `payload_bruto` de livros antigos para popular campos novos sem rebipar.

**Fase 6 — Polimento**
1. Testes de fixture para adapters.
2. Documentação (README).
3. Validação bit-a-bit do XLSX gerado vs. template oficial.

---

## Riscos e mitigações

| Risco | Mitigação |
|---|---|
| `BarcodeDetector` não disponível em Firefox/Safari | **Decisão #6**: mostrar mensagem clara "Use Chrome ou Edge"; sem ZXing-js no MVP. Usuários de Firefox/Safari caem para leitor USB. |
| `getUserMedia` exige HTTPS/localhost | **Decisão #10**: migração para HTTPS já confirmada. Fase 4.0 do plano cuida disso. |
| Espaços críticos em M1/N1 podem ser quebrados por normalização (`trim`) | Já preservados em [XlsxHybExporter.php:57-58]. Adicionar teste comparando bytes do cabeçalho. |
| Índice da planilha Categorias perde zeros como float (`9.10`→`9.1`) | Armazenar como `VARCHAR(10)` no banco e ler valor formatado da planilha no import. |
| `focarInput()` agressivo brigando com modal/câmera | Adicionar flag global `cameraAtiva` que desativa o listener `mousedown`. |
| Migração de `hyb_categoria` texto para `categoria_id` deixa livros legados nulos | Script de match exato + revisão manual; manter `hyb_categoria` como fallback no XLSX. |
| `HYB_CATEGORIA_DEFAULT_ID` (decisão #3) pode apontar para categoria inexistente | Bootstrap valida ID contra tabela `categoria`; se não existir, loga warning e usa fallback `HYB_CATEGORIA_DEFAULT` (string). |
| Histórico de baixas em tabela 1:N pode crescer (decisão #8: sem purga) | Índices em `(livro_id)` e `(exportacao_id)` cobrem filtros. Quando virar produção, expor `.env` `EXPORT_RETENTION_DAYS` opcional. |
| Coluna O (Descrição) pode estourar 32k chars com enriquecimento E1 | Template configurável + truncamento defensivo em 30000 chars. |
| Transação de export rolla back após XLSX gerado mas antes do INSERT | **Decisão #1**: INSERT só acontece após `Writer::save()` retornar OK. Se INSERT falhar depois, o arquivo fica no disco mas livros não são marcados — risco mitigado por: (a) transação curta; (b) cron de limpeza de arquivos órfãos em `storage/exports/`. |
| Race condition em bipagens simultâneas (web app multi-usuário pós-decisão #10) | Quando virar web, considerar coluna `usuario` em `exportacoes_hyb` (já no DDL) e locking otimista no UPSERT de livros. POC monousuário: risco baixo. |
| HTTPS autoassinado em dev gera warning do browser | Documentar no README como aceitar o certificado uma vez; alternativa: usar `mkcert` para gerar CA local confiável. |
| Migrar de HTTP para HTTPS pode quebrar consultas a APIs externas | BrasilAPI / Google Books / Open Library já são HTTPS — sem impacto. Validar que cookies de sessão (quando existirem) ganhem `Secure` + `HttpOnly`. |
