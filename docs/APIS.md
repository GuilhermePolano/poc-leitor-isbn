# APIs Consultadas — Referência Técnica

Documentação das três APIs gratuitas usadas pelo sistema para consulta de
ISBN, a estratégia de fallback/merge e detalhes de cobertura.

> **Para uso operacional:** veja [USO.md](USO.md).
> **Para implantação:** veja [../README.md](../README.md).

---

## 1. Resumo executivo

| API | Custo | Cobertura BR | Profundidade | Status atual |
|---|---|---|---|---|
| **BrasilAPI** | Grátis · sem chave | ★★★★★ | Variável (depende do CBL) | Estável |
| **Google Books** | Grátis · ~1k req/dia (sem chave) | ★★★ | Rica em metadados | Intermitente (503 ocasional) |
| **Open Library** | Grátis · sem chave | ★ | Variável | Estável, baixa cobertura BR |

**Ordem de consulta:** BrasilAPI → Google Books → Open Library.
**Estratégia:** ver §4 (Composite com merge).

---

## 2. BrasilAPI

### 2.1 Endpoint

```
GET https://brasilapi.com.br/api/isbn/v1/{isbn}
```

- **Autenticação:** nenhuma
- **Rate limit:** moderado (Cloudflare protege; sem limite oficial publicado)
- **Timeout configurado no projeto:** 5s (`APP_TIMEOUT_API_SEGUNDOS`)
- **Documentação:** https://brasilapi.com.br/docs#tag/ISBN

### 2.2 Provedores internos

A BrasilAPI é uma **camada agregadora** que consulta múltiplas fontes brasileiras:

| Provedor (`provider`) | Fonte | Observação |
|---|---|---|
| `cbl` | Câmara Brasileira do Livro | Maior cobertura de livros nacionais. Qualidade varia — depende do quanto a editora preencheu ao registrar o ISBN |
| `mercado-editorial` | Mercado Editorial | Cadastros geralmente bons, com sinopse e capa |
| `open-library` | Open Library | Fallback interno |
| `google-books` | Google Books | Fallback interno |

### 2.3 Schema da resposta

```json
{
  "isbn": "9788545702870",
  "title": "Akira",
  "subtitle": null,
  "authors": ["Katsuhiro Otomo"],
  "publisher": "JBC",
  "synopsis": "Um dos marcos da ficção...",
  "dimensions": { "width": 16, "height": 23, "unit": "CENTIMETER" },
  "year": 2017,
  "format": "PHYSICAL",
  "page_count": 364,
  "subjects": ["Mangá", "Ficção científica"],
  "location": "São Paulo",
  "retail_price": { "currency": "BRL", "amount": 49.90 },
  "cover_url": "https://...",
  "provider": "cbl"
}
```

### 2.4 Mapeamento para o modelo unificado (`DadosBibliograficos`)

| Campo BrasilAPI | Campo unificado |
|---|---|
| `title` | `titulo` |
| `subtitle` | `subtitulo` |
| `authors` | `autores` |
| `publisher` | `editora` |
| `synopsis` | `sinopse` |
| `dimensions.width/height` (mm → cm se necessário) | `dimensoes.largura/altura` |
| `year` | `ano_publicacao` e `data_publicacao` (= year) |
| `format` (`PHYSICAL`→`Físico`, `DIGITAL`→`Digital`) | `formato` |
| `page_count` | `paginas` |
| `subjects` | `assuntos` |
| `location` | `local_publicacao` |
| `retail_price.currency/amount` | `preco.moeda/valor` |
| `cover_url` | `capa_url` e `capa_thumbnail` |
| `provider` | `provider_origem` |
| (idioma) | sempre `pt-BR` (BrasilAPI é exclusiva BR) |

**Adaptador:** [src/Infrastructure/Adapter/Out/IsbnProvider/BrasilApiClient.php](../src/Infrastructure/Adapter/Out/IsbnProvider/BrasilApiClient.php)

### 2.5 Limitações conhecidas

- Qualidade depende do quanto cada editora preencheu ao registrar o ISBN no CBL. Pode vir só com título e editora.
- Livros publicados antes de ~2000 podem não estar no CBL eletrônico.
- Sem cobertura de livros estrangeiros importados.

---

## 3. Google Books

### 3.1 Endpoint

```
GET https://www.googleapis.com/books/v1/volumes?q=isbn:{isbn}
```

- **Autenticação:** opcional. Sem chave: ~1.000 req/dia por IP. Com chave gratuita do Google Cloud: ~100.000 req/dia.
- **Configurar chave:** definir `GOOGLE_BOOKS_API_KEY` no `.env`.
- **Documentação:** https://developers.google.com/books/docs/v1/using

### 3.2 Schema da resposta (campos relevantes em `items[0].volumeInfo`)

```json
{
  "totalItems": 1,
  "items": [{
    "volumeInfo": {
      "title": "Akira",
      "subtitle": "...",
      "authors": ["Katsuhiro Otomo"],
      "publisher": "JBC",
      "publishedDate": "2017-08-15",
      "description": "...",
      "industryIdentifiers": [
        { "type": "ISBN_10", "identifier": "8545702876" },
        { "type": "ISBN_13", "identifier": "9788545702870" }
      ],
      "pageCount": 364,
      "dimensions": { "height": "22.00 cm", "width": "15.00 cm", "thickness": "2.50 cm" },
      "printType": "BOOK",
      "mainCategory": "Comics & Graphic Novels",
      "categories": ["Comics & Graphic Novels / General"],
      "averageRating": 4.5,
      "ratingsCount": 23,
      "imageLinks": {
        "smallThumbnail": "https://...",
        "thumbnail": "https://...",
        "small": "...", "medium": "...", "large": "...", "extraLarge": "..."
      },
      "language": "pt-BR",
      "previewLink": "https://books.google.com/books?...",
      "infoLink": "https://..."
    },
    "saleInfo": {
      "listPrice": { "amount": 49.90, "currencyCode": "BRL" }
    }
  }]
}
```

### 3.3 Mapeamento

| Campo Google | Campo unificado |
|---|---|
| `volumeInfo.title` | `titulo` |
| `volumeInfo.subtitle` | `subtitulo` |
| `volumeInfo.authors` | `autores` |
| `volumeInfo.publisher` | `editora` |
| `volumeInfo.publishedDate` (parse year) | `ano_publicacao` + `data_publicacao` |
| `volumeInfo.description` | `sinopse` |
| `industryIdentifiers[type=ISBN_10]` | `isbn_10` |
| `volumeInfo.pageCount` | `paginas` |
| `volumeInfo.dimensions.height/width/thickness` (parse cm/mm) | `dimensoes.*` |
| `volumeInfo.printType` | `formato` |
| `volumeInfo.categories` | `categorias` |
| `volumeInfo.averageRating` / `ratingsCount` | `avaliacao_media` / `qtd_avaliacoes` |
| `imageLinks.extraLarge` (fallback até `smallThumbnail`) | `capa_url` |
| `imageLinks.thumbnail` | `capa_thumbnail` |
| `volumeInfo.language` | `idioma` |
| `volumeInfo.previewLink` ou `infoLink` | `link_preview` |
| `saleInfo.listPrice.amount/currencyCode` | `preco.valor/moeda` |

**Capas em HTTPS:** o adaptador força `https://` substituindo eventuais `http://` que o Google retorna.

**Adaptador:** [src/Infrastructure/Adapter/Out/IsbnProvider/GoogleBooksClient.php](../src/Infrastructure/Adapter/Out/IsbnProvider/GoogleBooksClient.php)

### 3.4 Limitações conhecidas

- Cobertura BR limitada — funciona melhor com livros publicados ou distribuídos internacionalmente.
- API tem instabilidade ocasional (503 "Service temporarily unavailable"). Quando isso acontece, o sistema segue para Open Library automaticamente.
- Sem chave: cota de ~1.000 req/dia/IP é compartilhada — em cenários de bipagem em volume, configurar a chave do Google Cloud (gratuita).

---

## 4. Open Library

### 4.1 Endpoints

```
GET https://openlibrary.org/isbn/{isbn}.json
GET https://openlibrary.org/{author_key}.json     # para resolver nome do autor
GET https://covers.openlibrary.org/b/isbn/{isbn}-L.jpg   # capa direta (fallback)
```

- **Autenticação:** nenhuma
- **Rate limit:** generoso
- **Documentação:** https://openlibrary.org/developers/api

### 4.2 Schema da resposta

```json
{
  "title": "Akira",
  "subtitle": null,
  "authors": [{ "key": "/authors/OL12345A" }],
  "publishers": ["JBC"],
  "publish_date": "August 2017",
  "number_of_pages": 364,
  "physical_dimensions": "9 x 6.5 x 1 inches",
  "weight": "1 pounds",
  "subjects": ["Manga"],
  "description": { "value": "..." },
  "covers": [12345678],
  "languages": [{ "key": "/languages/por" }],
  "isbn_10": ["8545702876"],
  "isbn_13": ["9788545702870"],
  "works": [{ "key": "/works/OL98765W" }],
  "publish_places": [{ "name": "São Paulo" }],
  "key": "/books/OL12345M"
}
```

### 4.3 Mapeamento

| Campo Open Library | Campo unificado |
|---|---|
| `title` | `titulo` |
| `subtitle` | `subtitulo` |
| `authors[].key` (segunda consulta) | `autores` (resolve nome) |
| `publishers[0]` | `editora` |
| `publish_date` (parse year) | `ano_publicacao` + `data_publicacao` |
| `description.value` ou `description` (string) | `sinopse` |
| `number_of_pages` | `paginas` |
| `physical_dimensions` (parse cm/inches) | `dimensoes.*` |
| `weight` | `peso` |
| `subjects` | `assuntos` |
| `covers[0]` → `https://covers.openlibrary.org/b/id/{id}-L.jpg` | `capa_url` |
| `languages[0].key` (`/languages/por` → `por`) | `idioma` |
| `publish_places[0].name` | `local_publicacao` |
| `key` → `https://openlibrary.org{key}` | `link_preview` |

**Resolução de autores:** Open Library retorna apenas referências para autores (`/authors/OLxxxxA`). O adaptador faz uma segunda chamada para cada autor para obter o nome. Isso pode aumentar o tempo total de consulta.

**Capa fallback:** se `covers` não vier preenchido, o adaptador usa o endpoint direto por ISBN (`https://covers.openlibrary.org/b/isbn/{isbn}-L.jpg`). Esta URL retorna sempre, mas pode dar erro 404 ao renderizar — o `<img>` do front fica vazio.

**Adaptador:** [src/Infrastructure/Adapter/Out/IsbnProvider/OpenLibraryClient.php](../src/Infrastructure/Adapter/Out/IsbnProvider/OpenLibraryClient.php)

### 4.4 Limitações conhecidas

- Cobertura BR é fraca — a maioria dos livros nacionais retorna 404.
- Dados frequentemente incompletos (sem ano, sem dimensões).
- Idioma vem como código de 3 letras (`por`, `eng`) e não BCP-47.

---

## 5. Estratégia de Fallback + Merge (`CompositeIsbnProvider`)

### 5.1 Comportamento atual

```
buscarPorIsbn(ISBN)
        │
        ▼
┌─ BrasilAPI ──── rica? ──── sim → retorna ─────────────────────┐
│                                                               │
│                pobre? (só título/editora)                     │
│                            │                                  │
│                            ▼                                  │
│             Google Books ──── retornou? ─── merge no array    │
│                            │                                  │
│                            ▼                                  │
│             Open Library ──── retornou? ─── merge no array    │
│                            │                                  │
│                            ▼                                  │
│                  NormalizadorDeLivro.mesclar(...todos)        │
│                            │                                  │
└────────────────────────────▼──────────────────────────────────┘
                      DadosBibliograficos
```

### 5.2 Definição de "rico" vs "pobre"

Um resultado é considerado **rico** se tem:

- Título não vazio **E**
- (autores não vazios **OU** sinopse não vazia **OU** capa não vazia)

Resultados rasos da BrasilAPI (só título + editora) são marcados como **pobres** e disparam consulta aos provedores seguintes.

### 5.3 Algoritmo de merge

Implementado em [src/Domain/Service/NormalizadorDeLivro.php](../src/Domain/Service/NormalizadorDeLivro.php) (método `mesclar`).

- Para cada campo: usa o **primeiro provider que preencheu** (precedência por ordem da lista).
- Campos vazios/null em todos = vazio no resultado final.
- A `fonte_api` e `provider_origem` permanecem os do **primeiro** (ex.: `brasilapi`/`cbl`), mesmo quando outros completaram campos. O log mostra "Mesclando resultados de múltiplas APIs" para rastreio.

### 5.4 Atalho de performance

Se a primeira API retornar **rica**, o composite para ali — não chama as outras. Isso garante que livros com cadastro bom no CBL retornam em ~300ms.

Tempo típico observado:

| Cenário | Tempo |
|---|---|
| BrasilAPI rica (curto-circuito) | 200–400 ms |
| BrasilAPI pobre + Google sucesso | 800–1500 ms |
| BrasilAPI pobre + Google 503 + Open Library 404 | 2000–2500 ms |
| Cache (mesmo ISBN <30 dias) | <50 ms |

---

## 6. Cache de consultas

- Implementado no banco — toda consulta bem-sucedida é gravada na tabela `livros`.
- Reconsultar o mesmo ISBN dentro de `APP_CACHE_DIAS` (default 30) retorna **direto do banco** sem chamar APIs.
- Para forçar reconsulta, enviar `"force": true` no body do `POST /api/consultar.php` ou usar querystring `?force=1`.

Pelo log:

```
[2026-05-12 12:05:43] [INFO] Provider acertou {"provider":"brasilapi","isbn":"...","qualidade":"rico"}
```

Quando `origem: cache` no JSON da resposta significa que veio do banco, não da API.

---

## 7. HTTP Client — detalhe técnico importante

O projeto usa **`file_get_contents` + `stream_context`** (PHP nativo) em vez de
libcurl. Motivo:

> O libcurl 7.88 + OpenSSL 3.x do Debian Bookworm (usado pelo container
> `php:8.2-apache`) gera um **JA3 fingerprint** que o **Cloudflare** (que
> serve a BrasilAPI) bloqueia ativamente. A conexão TLS é cortada com
> `TLS alert decode error (562)` antes mesmo de qualquer requisição HTTP.

O fix mantém a mesma interface (`HttpClient::get()`) mas usa a stack TLS
nativa do PHP, que tem fingerprint diferente e passa sem problemas.

**Arquivo:** [src/Infrastructure/Adapter/Out/IsbnProvider/HttpClient.php](../src/Infrastructure/Adapter/Out/IsbnProvider/HttpClient.php)

---

## 8. Validação local de ISBN

Antes de chamar qualquer API, o sistema valida o ISBN no domínio
(`App\Domain\ValueObject\ISBN`):

- Aceita ISBN-10 ou ISBN-13.
- Normaliza removendo hífens, espaços e caracteres não numéricos (preserva `X`).
- Valida dígito verificador (módulo 11 para ISBN-10, módulo 10 para ISBN-13).
- Converte automaticamente ISBN-10 para ISBN-13 (prefixo `978`).
- ISBN-13 que começa com `978` é convertido de volta para ISBN-10 para preencher o campo `Referência` dos campos HYB.
- ISBN inválido **não chama nenhuma API** — retorna erro 400 imediatamente.

Exemplo de erro:

```json
{ "sucesso": false, "erro": "ISBN-13 inválido: 9788545702871" }
```

---

## 9. Trocar provedores ou adicionar novos

A arquitetura hexagonal permite acrescentar/trocar providers sem mexer no domínio.

Para acrescentar um provedor pago (ex.: ISBNdb) ou de catálogo próprio:

1. Criar `MeuProviderClient implements IsbnProvider` em
   `src/Infrastructure/Adapter/Out/IsbnProvider/`
2. Implementar `buscarPorIsbn(ISBN $isbn): ?DadosBibliograficos`
3. Registrar no array `$providers` dentro de
   [bootstrap/container.php](../bootstrap/container.php), método `isbnProvider()`

A ordem do array define a precedência no merge. Nada mais precisa mudar — nem
domínio, nem casos de uso, nem controllers.

Para remover um provider, basta tirar do array. Sem outros impactos.

---

## 10. Logs de telemetria

Todas as consultas geram entradas em `logs/app-YYYY-MM-DD.log` no host (volume
do container). Exemplo:

```
[2026-05-12 12:05:43] [INFO] Provider acertou {"provider":"brasilapi","isbn":"9788545702870","qualidade":"rico"}
[2026-05-12 12:05:43] [INFO] ISBN consultado {"isbn":"9788545702870","origem":"brasilapi","tempo_ms":245}
[2026-05-12 12:08:11] [INFO] Provider acertou {"provider":"brasilapi","isbn":"9788576838494","qualidade":"pobre"}
[2026-05-12 12:08:12] [INFO] Provider sem dados {"provider":"google_books","isbn":"9788576838494"}
[2026-05-12 12:08:14] [INFO] Provider sem dados {"provider":"open_library","isbn":"9788576838494"}
[2026-05-12 12:08:14] [INFO] ISBN consultado {"isbn":"9788576838494","origem":"brasilapi","tempo_ms":2181}
```

Para acompanhar em tempo real:

```powershell
docker exec isbn_app tail -f logs/app-$(Get-Date -Format yyyy-MM-dd).log
```

Também existe a tabela `historico_bipagens` no banco, que registra cada
tentativa de bipagem (sucesso ou falha) com IP de origem para auditoria.

---

## 11. Referências externas

- BrasilAPI ISBN: https://brasilapi.com.br/docs#tag/ISBN
- Google Books API v1: https://developers.google.com/books/docs/v1/using
- Open Library API: https://openlibrary.org/developers/api
- Câmara Brasileira do Livro (CBL): https://www.cblservicos.org.br/
- Mercado Editorial: https://www.mercadoeditorial.org/
- JA3 TLS fingerprinting: https://github.com/salesforce/ja3
