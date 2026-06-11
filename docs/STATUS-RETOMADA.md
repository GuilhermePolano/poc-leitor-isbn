# Status da implementação — CONCLUÍDA

> Última atualização: 2026-06-03

## Estado: ✅ TODAS AS FASES IMPLEMENTADAS E VERIFICADAS

Implementação dos 4 ajustes + 3 extras da [spec-ajustes.md](spec-ajustes.md) completa.
Total: **19 agentes executados em 2 workflows** (`wf_85f872e6-f9f` parcial + `wf_68230798-bb4` completo).

---

## ✅ Verificações end-to-end (todas passaram)

| Teste | Resultado |
|---|---|
| **V1** GET `/api/categorias.php` | 200 · 39 itens · 1 raiz + 38 filhos · ordem `9 → 9.01 → … → 9.38` |
| **V2** GET `/api/consultar.php?isbn=9788535914849` | 200 · título="1984" (Orwell) · idioma normalizado=`pt-BR` · campos novos serializados |
| **V3** Export E2E (criar + baixar + validar) | XLSX 9975 bytes · B1=`Titulo` · M1 com espaços preservados · D2=`Referência Biblioteca` · M2=5 (override) · histórico +1 |

---

## ✅ O que foi implementado (resumo por fase)

### Correção pré-implementação — categoria default

- `.env`: `HYB_CATEGORIA_DEFAULT_ID=1` → **`HYB_CATEGORIA_DEFAULT_CODIGO=96`** (semântica estável)
- `public/index.php`: resolve `codigo→id` no server-side via `repositorioCategorias()->buscarPorCodigo()`; expõe `categoria_id` já resolvido em `window.HYB_DEFAULTS`
- Log de warning quando código não bate (fallback string)

### Fase 1 — Categorias (Ajuste 2) — ✅

- `sql/migrations/0001_categoria.sql` — DDL aplicado (39 linhas: 1 raiz + 38 filhos)
- `scripts/importar_categorias.php` — idempotente, lê Índice como string preservando `9.10/9.20/9.30`
- `scripts/migrar_categoria_legada.php` — 1 órfão ("Livros") esperado
- Domain: `Categoria.php`, `CategoriaRepository.php`
- Adapter: `MySqlCategoriaRepository.php` (ordenação numérica natural)
- Use case: `ListarCategoriasService.php`
- Endpoint: `api/categorias.php`
- `CamposHyb.php` refatorado: `?int $categoriaId` + `$estoqueInicialQtd` int
- `MySqlLivroRepository.php` ajustado: INSERT/UPDATE com `categoria_id`
- `public/index.php` + `app.js`: dropdown linear ordenado por índice
- `MapeadorParaFormatoHyb.php`: coluna D resolve `categoria.descricao` (decisão #11)

### Fase 2 — Export histórico + Modal (Ajuste 1) — ✅

- `sql/migrations/0002_exportacoes.sql` — `exportacoes_hyb` aprimorada (`gerado_em`, `usuario`, `origem`) + nova `exportacoes_hyb_itens` com FKs CASCADE
- Domain: `Exportacao.php`, `ExportacaoItem.php`, `ExportacaoRepository.php`
- Adapter: `MySqlExportacaoRepository.php` — transação 4 etapas (INSERT cabeçalho → INSERT itens → UPDATE livros.exportado_em → COMMIT)
- `ExportarLivrosParaHybService.php` refatorado: aceita `quantidades` override, registra histórico APÓS `save()` OK
- `api/exportar.php`: aceita POST JSON `{ids, quantidades, origem}` + retro-compat GET
- `api/livros.php` + entidade `Livro`: payload agora expõe `exportado_em`, `exportado_em_br`, `qtd_baixas`, `quantidade`
- `public/lista.php`: 3 botões antigos → **1 botão `📥 Exportar para HYB`** + `<dialog>` com tabela paginada e quantidade editável
- `public/assets/js/lista.js`: lógica do modal (busca/filtro client-side, "Selecionar todos", contagem dinâmica, POST + download)
- `public/index.php`: botão `💾⬇ Salvar e baixar XLSX` (atalho pós-cadastro)

### Fase 3 — HTTPS + APIs + Câmera — ✅

**HTTPS (decisão #10)**:
- `.docker/Dockerfile`: `a2enmod ssl rewrite headers`
- `.docker/entrypoint.sh`: gera cert autoassinado em `/etc/ssl/certs/app.crt` se não existir
- `.docker/apache-vhost.conf`: VHost `:80` com redirect 301 → VHost `:443` com SSL + `Permissions-Policy: camera=(self)` + HSTS
- `docker-compose.yml`: porta `8443:443` adicionada

**APIs (Extra E1 / M16)**:
- `DadosBibliograficos.php`: 6 campos novos opcionais (`contributors`, `maturityRating`, `mainCategory`, `physicalFormat`, `editionName`, `series`)
- **`IdiomaNormalizer.php` novo** — tabela ISO 639-2/639-1 → BCP-47 (por→pt-BR, eng→en, etc.)
- `OpenLibraryClient.php`: extrai `contributors` (mapeia translator/illustrator/editor), `physical_format`, `edition_name`, `series`
- `GoogleBooksClient.php`: extrai `mainCategory` (fallback `categories[0]`), `maturityRating`
- `BrasilApiClient.php`: idioma normalizado
- `NormalizadorDeLivro.php` merge propaga campos novos

**Câmera (Ajustes 3+4)**:
- `public/index.php`: botão `📷` ao lado do `#campo-isbn` + overlay `<div id="overlay-camera">` com `<video>` + linha-guia
- `style.css`: estilos do overlay, modal, linha-guia, botão câmera
- `app.js`: `iniciarCameraEDecodificar()` com BarcodeDetector (formats `ean_13`, `ean_8`) + `validarIsbn13()` checksum (sem bloqueio de prefixo — decisão #7) + `fecharCamera()` para tracks; pausa `focarInput()` enquanto câmera ativa; tratamento de permissão negada com toast

**Descrição (M16)**:
- `GeradorDescricaoLivro.php` novo — template fixo: Autores → Tradução → Ilustrações → Edição → Série → Editora+Ano → Páginas → Idioma → Classificação → Sinopse
- Truncamento defensivo em 30.000 chars
- Deduplicação case-insensitive em autores/contributors
- Idioma só aparece se ≠ `pt-BR`; Classificação só se `MATURE`

---

## 🌐 Como acessar agora

| Rota | URL | Observação |
|---|---|---|
| HTTP (redireciona) | http://localhost:8080 | Redirect 301 → HTTPS |
| **HTTPS** | https://localhost:8443 | **Use este para a câmera** |
| API livros | https://localhost:8443/api/livros.php | |
| API categorias | https://localhost:8443/api/categorias.php | |
| API consultar | https://localhost:8443/api/consultar.php?isbn=... | |
| API exportar (POST) | https://localhost:8443/api/exportar.php | JSON: `{ids:[...], quantidades:{...}, origem:"lista"}` |

⚠️ **Primeiro acesso HTTPS**: certificado autoassinado vai mostrar warning no Chrome/Edge. Clicar em **Avançado → Continuar para localhost**. Necessário apenas 1 vez.

---

## ⚠️ Pontos de atenção

1. **Câmera requer HTTPS + Chrome/Edge**. Se o usuário tentar em Firefox/Safari, vai aparecer alerta orientando a usar Chrome/Edge (decisão #6 — sem ZXing-js).
2. **1 livro órfão de categoria**: o livro `id=2` (criado em testes anteriores com `hyb_categoria='Livros'`) ficou com `categoria_id=NULL`. Ao reabrir na UI, o operador seleciona uma categoria real. Esperado.
3. **Cert autoassinado expira em 365 dias**. Para renovar: `docker compose exec app rm /etc/ssl/certs/app.crt && docker compose restart app`.
4. **Banco com dados de teste**: existem 9 livros e 4 exportações de testes acumulados. Para resetar: `docker compose down -v && docker compose up -d` (re-roda as migrations automaticamente).

---

## 📦 Arquivos novos (resumo)

```
sql/migrations/0001_categoria.sql
sql/migrations/0002_exportacoes.sql
scripts/importar_categorias.php
scripts/migrar_categoria_legada.php
src/Domain/Entity/Categoria.php
src/Domain/Entity/Exportacao.php
src/Domain/Entity/ExportacaoItem.php
src/Domain/Port/Out/CategoriaRepository.php
src/Domain/Port/Out/ExportacaoRepository.php
src/Domain/Service/IdiomaNormalizer.php
src/Domain/Service/GeradorDescricaoLivro.php
src/Application/ListarCategoriasService.php
src/Infrastructure/Adapter/Out/Persistence/MySqlCategoriaRepository.php
src/Infrastructure/Adapter/Out/Persistence/MySqlExportacaoRepository.php
api/categorias.php
```

## 📝 Arquivos alterados

```
.env (HYB_CATEGORIA_DEFAULT_CODIGO + remoção do _ID antigo)
docker-compose.yml (porta 8443)
.docker/Dockerfile (mod_ssl)
.docker/entrypoint.sh (gerar cert)
.docker/apache-vhost.conf (VHost :443 + redirect :80)
bootstrap/container.php (wiring de tudo novo)
src/Domain/Entity/CamposHyb.php (categoriaId + quantidade int)
src/Domain/Entity/Livro.php (exportadoEmIso, exportadoEmBr, quantidade)
src/Domain/Entity/DadosBibliograficos.php (6 campos novos opcionais)
src/Domain/Service/MapeadorParaFormatoHyb.php (Categoria + GeradorDescricaoLivro)
src/Domain/Service/NormalizadorDeLivro.php (merge propaga campos novos)
src/Application/ExportarLivrosParaHybService.php (transação + override + origem)
src/Infrastructure/Adapter/Out/Persistence/MySqlLivroRepository.php (categoria_id + qtd_baixas via LEFT JOIN)
src/Infrastructure/Adapter/Out/IsbnProvider/OpenLibraryClient.php (contributors etc)
src/Infrastructure/Adapter/Out/IsbnProvider/GoogleBooksClient.php (mainCategory etc)
src/Infrastructure/Adapter/Out/IsbnProvider/BrasilApiClient.php (idioma normalizado)
api/exportar.php (POST JSON)
api/livros.php (passthrough — entidade já expõe campos novos)
api/consultar.php (usa GeradorDescricaoLivro)
public/index.php (dropdown categoria + quantidade + atalho + câmera)
public/lista.php (botão único + modal dialog)
public/assets/js/app.js (carregarCategorias + atalho + câmera)
public/assets/js/lista.js (modal completo)
public/assets/css/style.css (modal + câmera + linha-bipagem)
```
