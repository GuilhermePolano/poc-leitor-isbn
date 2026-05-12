# Documentação de Utilização

Guia operacional do **POC Código de Barras** — para quem vai usar o sistema no
dia a dia (bipar livros, persistir, exportar para o HYB Integrador).

> **Para implantação/instalação:** veja [../README.md](../README.md).
> **Para detalhes técnicos das APIs:** veja [APIS.md](APIS.md).

---

## 1. Visão geral

A aplicação tem **duas telas**:

1. **Bipagem** (`/` ou `/index.php`) — leitura do código de barras, consulta nas APIs, preenchimento dos campos complementares HYB e salvamento.
2. **Lista de livros cadastrados** (`/lista.php`) — busca, filtro, edição, exportação XLSX em lote.

Fluxo típico de operação:

```
Bipar → API consultada → Campos HYB revisados → Salvar → (repetir) → Exportar XLSX
```

---

## 2. Hardware: leitor de código de barras

### Modelo testado
- Genérico USB HID-keyboard (VID/PID `3333:5555`, modelo TCS-1032)
- **Nenhum driver é necessário** — Windows reconhece como teclado HID

### Como funciona
O leitor **emula um teclado** — quando bipa um código, "digita" os caracteres do ISBN e termina com **Enter**. A aplicação tem `autofocus` permanente no campo de leitura, então:

1. Abrir o navegador em http://localhost:8080
2. O cursor já estará no campo grande de ISBN
3. Apontar o leitor para o código de barras do livro
4. Disparar — o ISBN aparece e a consulta dispara automaticamente

### Pré-requisito do leitor: sufixo Enter

Alguns leitores vêm com sufixo desabilitado de fábrica. Se ao bipar o ISBN aparece mas a consulta não dispara, é porque ele não está enviando Enter ao final.

Para configurar, consulte o **manual do leitor** — geralmente vem com um cartão de códigos de barras de configuração. Procure por **"Add CR Suffix"** ou **"Enable Enter after scan"** e bipe esse código.

**Teste rápido:** abra o Bloco de Notas, bipe um código. Se aparece o número e o cursor pula linha, o leitor está OK. Se aparece o número mas o cursor não pula, falta configurar o Enter.

---

## 3. Tela 1 — Bipagem (`/index.php`)

### 3.1 Estrutura da tela

```
┌──────────────────────────────────────────────────────────────┐
│  📚 CONSULTA DE LIVROS POR ISBN          [ Ver cadastrados ]│
├──────────────────────────────────────────────────────────────┤
│  Bipe o código de barras do livro:                          │
│  [_________________________ campo com autofocus ___________]│
│  Aguardando leitura...                                       │
├──────────────────────────────────────────────────────────────┤
│  ┌──────────┐  Título: ...                                   │
│  │   CAPA   │  Autor(es): ...                                │
│  │          │  Editora: ...                                  │
│  │          │  Ano: ...                                      │
│  └──────────┘  ISBN-13/10, Idioma, Formato, Páginas,         │
│                Dimensões, Preço, Local                       │
│                                                              │
│  Assuntos · Categorias · Sinopse · Fonte da consulta         │
├──────────────────────────────────────────────────────────────┤
│  📋 CAMPOS COMPLEMENTARES (HYB) — todos opcionais            │
│  Unidade · Categoria · NCM · Preço de Venda · Estoque Mínimo │
│  Referência · Patrimônio · Depreciação · Tipo · Quantidade…  │
│  Descrição (auto-gerada, editável)                           │
│                                                              │
│  [💾 Salvar] [📋 Copiar JSON] [↺ Nova consulta]              │
└──────────────────────────────────────────────────────────────┘
```

### 3.2 Estados da tela

| Estado | O que aparece |
|---|---|
| **Inicial** | Apenas o campo de bipagem com status "Aguardando leitura..." |
| **Consultando** | Mensagem azul "Consultando '9788...' …" |
| **Sucesso** | Painel com dados do livro + capa + painel HYB pré-preenchido |
| **Não encontrado** | Card vermelho com mensagem amigável |
| **Já cadastrado** | Aparece "Já cadastrado" no rodapé com a `Fonte` |

### 3.3 Campos da API

Tudo que vem da API é **somente leitura** nesta versão (v1). Para campos vazios da API, aparece `—`.

| Campo | Origem |
|---|---|
| Título, subtítulo | Provider (BrasilAPI / Google / OpenLibrary) |
| Autor(es) | Provider |
| Editora, Ano, Idioma, Formato | Provider |
| ISBN-13, ISBN-10 | Validado/normalizado no domínio |
| Páginas, Dimensões | Provider |
| Preço sugerido | Provider (BRL quando vem da BrasilAPI) |
| Capa | URL do provider |
| Assuntos, Categorias | Provider |
| Sinopse | Provider |
| Fonte | `brasilapi` · `google_books` · `open_library` · `cache` |

### 3.4 Campos Complementares HYB

Esta é a parte **editável** — preenche colunas do HYB Integrador que dependem de decisão operacional.

| Campo | Pré-preenchimento | Observação |
|---|---|---|
| Bem/Produto | vazio | Só usar quando for editar registro existente no HYB |
| Unidade | `HYB_UNIDADE_DEFAULT` do `.env` (default: `UN`) | |
| Categoria | `HYB_CATEGORIA_DEFAULT` (default: `Livros`) | |
| NCM | `HYB_NCM_DEFAULT` (default: `4901.99.00`) | |
| Preço de Venda | Preço do livro vindo da API (se houver) | |
| Estoque Mínimo | `HYB_ESTOQUE_MINIMO_DEFAULT` (default: `0`) | |
| Referência | ISBN-10 do livro (se houver) | |
| Patrimônio | `HYB_PATRIMONIO_DEFAULT` (default: `N`) | `S` / `N` / vazio |
| Depreciação (%) | vazio | |
| Tipo | `HYB_TIPO_DEFAULT` (default: `Móvel`) | `Desconhecido` / `Móvel` / `Imóvel` / vazio |
| Estoque Inicial — Quantidade | `HYB_ESTOQUE_INICIAL_QTD_DEFAULT` (default: `1`) | |
| Estoque Inicial — Custo Unitário | vazio | |
| Descrição | Auto-gerada: autor + editora + ano + páginas + sinopse | Editável |

> **Regra importante de precedência:**
> O valor digitado pelo operador sempre prevalece. Se o operador **apagar** um campo (deixar em branco), ele vai **vazio** para o XLSX — o default do `.env` **não** sobrescreve no momento do export.
> Isso permite forçar célula vazia no HYB caso o livro não tenha aquela informação.

### 3.5 Atalhos de teclado

| Tecla | Ação |
|---|---|
| `Enter` (após bipar) | Dispara a consulta |
| `ESC` | Limpa tela e devolve foco ao campo de bipagem |
| `F2` | Copia JSON (livro + campos HYB atuais) para a área de transferência |
| `F8` | Salva no banco (mesmo que clicar em **💾 Salvar**) |

---

## 4. Tela 2 — Livros cadastrados (`/lista.php`)

### 4.1 Estrutura

```
┌──────────────────────────────────────────────────────────────┐
│  📚 LIVROS CADASTRADOS (157 registros)    [← Voltar]         │
├──────────────────────────────────────────────────────────────┤
│  [Buscar...] [Editora] [Ano] [Filtrar] [Limpar]              │
│                                                              │
│  [📥 Exportar todos] [📥 Apenas não exportados]              │
│  [📥 Exportar selecionados]                                  │
│                                                              │
│  ☐ │ Capa │ Título   │ Autor  │ Editora │ Ano │ ISBN-13 │Exp│
│  ☐ │ [im] │ Akira    │ Otomo  │ JBC     │ 2017│ 9788... │Sim│
│  ☐ │ [im] │ Pollyana │ E.Por… │ Ciranda │ 2018│ 9788... │Não│
│                                                              │
│  [< anterior]   Página 1 de 4   [próxima >]                  │
└──────────────────────────────────────────────────────────────┘
```

### 4.2 Recursos

- **Paginação:** 50 registros por página
- **Busca:** procura no título, autores, ISBN-10 e ISBN-13
- **Filtros:** editora, ano, idioma
- **Seleção múltipla:** checkbox no cabeçalho marca/desmarca tudo da página
- **Status "Exportado?":** badge verde "Sim" se já saiu em algum XLSX, vermelho "Não" caso contrário

### 4.3 Botões de exportação

| Botão | Comportamento |
|---|---|
| **Exportar todos** | Gera XLSX com **toda** a base, sem filtros |
| **Apenas não exportados** | XLSX só com livros que ainda têm `exportado_em IS NULL` no banco |
| **Exportar selecionados** | XLSX só dos livros marcados via checkbox (precisa selecionar ao menos um) |

> Após exportar, os livros incluídos no XLSX são marcados com a data/hora em `exportado_em`. Isso permite controlar o que já foi enviado pro HYB.

---

## 5. Fluxos completos

### 5.1 Cadastrar um livro novo

1. Abrir http://localhost:8080
2. Bipar o ISBN do livro
3. Aguardar ~300ms — dados aparecem
4. Conferir/ajustar os campos HYB (geralmente os defaults estão OK)
5. Clicar **💾 Salvar** ou pressionar **F8**
6. Toast verde "Livro cadastrado!" — campo de ISBN limpa-se automaticamente
7. Bipar o próximo livro

### 5.2 Cadastrar um livro já bipado antes

Mesmo ISBN dentro de 30 dias é retornado do cache (`origem: cache`) sem chamar APIs. Os campos HYB já preenchidos voltam preenchidos. Salvar atualiza o registro (status 200 em vez de 201).

### 5.3 Exportar lote para o HYB

1. Abrir **Ver cadastrados** ou ir direto em `/lista.php`
2. Conferir a lista
3. Clicar **📥 Exportar todos** (ou **Apenas não exportados** para lote incremental)
4. Download automático do arquivo `HYBIntegrador_bens_YYYYMMDD_HHMMSS.xlsx`
5. Importar esse arquivo no HYB Integrador

O XLSX gerado segue o template oficial com 15 colunas e os cabeçalhos exatos esperados pelo HYB (incluindo espaços propositais nas colunas M e N). Comentários nas células A1, D1 e L1 são preservados.

### 5.4 Reexportar apenas livros novos

Use **Apenas não exportados**. O sistema controla via campo `exportado_em` — depois de exportado, o livro fica "marcado" e não entra em uma exportação incremental subsequente.

Se precisar **reexportar tudo** (mesmo já exportados), use **Exportar todos**.

---

## 6. Solução de problemas operacionais

### "Bipei e não acontece nada"
- Confirma que o cursor está piscando no campo grande de ISBN (deve ter borda azul).
- Testa o leitor no Bloco de Notas: se digitar mas não pular linha, falta configurar **CR Suffix** no leitor.
- Se digitar no Bloco de Notas mas não no navegador, clica uma vez no campo de ISBN.

### "Apareceu 'ISBN não encontrado em nenhuma fonte'"
- Verifica se o ISBN tem 13 dígitos (ou 10) e está completo.
- Alguns livros muito antigos ou independentes não estão em nenhuma das 3 APIs gratuitas. Veja [APIS.md](APIS.md) sobre cobertura.

### "O livro apareceu, mas sem autor/capa/sinopse"
- A BrasilAPI agrega o CBL — alguns livros têm cadastro raso na origem (a editora cadastrou só título e editora).
- O sistema já tenta o Google Books / Open Library automaticamente quando o resultado vem pobre. Se nenhum dos 3 tem dados, não há o que fazer pela API.
- Caso queira complementar manualmente, basta editar o campo **Descrição** dos Campos HYB antes de salvar.

### "Erro 500 ao consultar"
- Verifica `docker compose ps` — o container `isbn_db` precisa estar `(healthy)`.
- Olha os logs: `docker compose logs --tail 30 app`.

### "F2 (copiar JSON) não funcionou"
- O navegador exige HTTPS para `navigator.clipboard` em alguns contextos. Em http://localhost funciona normalmente, mas se você está acessando por IP de outra máquina, pode falhar. Use copiar manual nesse caso.

---

## 7. Endpoints HTTP (referência rápida)

Para automação ou integrações:

| Método | Rota | Descrição |
|---|---|---|
| GET | `/` | Tela de bipagem |
| GET | `/lista.php` | Tela de listagem |
| POST | `/api/consultar.php` | Consulta ISBN. Body: `{"isbn":"...","force":false}` |
| GET | `/api/livros.php?busca=&limite=50&offset=0` | Lista paginada |
| POST | `/api/livros.php` | Persiste livro. Body: `{"livro_api":{...},"hyb":{...}}` |
| GET | `/api/exportar.php` | Exporta tudo |
| GET | `/api/exportar.php?ids=1,5,7` | Exporta IDs específicos |
| GET | `/api/exportar.php?apenas_nao_exportados=1` | Exporta lote incremental |

Exemplo de consulta via PowerShell:

```powershell
$bytes = [System.Text.Encoding]::UTF8.GetBytes('{"isbn":"9788545702870"}')
Invoke-WebRequest -Uri http://localhost:8080/api/consultar.php `
    -Method POST -Body $bytes `
    -ContentType 'application/json; charset=utf-8' `
    -UseBasicParsing
```

---

## 8. Boas práticas no dia a dia

1. **Bipar com o livro na mão** — confira que o título exibido bate com o livro físico. Caso a editora tenha errado o ISBN no cadastro, isso evita salvar dados errados.
2. **Conferir o preço pré-preenchido** — quando vem da BrasilAPI, é o preço de capa cadastrado pela editora, que pode estar defasado.
3. **Aproveitar a Descrição auto-gerada** — ela já consolida autor/editora/ano/páginas/sinopse num único parágrafo, suficiente na maioria dos casos.
4. **Exportar em lotes** — vá acumulando bipes e exporte em blocos (semanal/mensal) usando **Apenas não exportados**.
5. **Salvar mesmo livros sem capa/sinopse** — o título + ISBN já são suficientes para o HYB; capa/sinopse são opcionais.
