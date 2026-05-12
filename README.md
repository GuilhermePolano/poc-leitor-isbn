# POC Código de Barras — Implantação

Aplicação web em **PHP 8.2 + Apache + MariaDB** para bipar livros via ISBN,
consultar APIs gratuitas, persistir e exportar XLSX no formato HYB Integrador.

> **Para usuários finais:** veja [docs/USO.md](docs/USO.md).
> **Para detalhes das APIs consultadas:** veja [docs/APIS.md](docs/APIS.md).

---

## Requisitos

Único pré-requisito na máquina hospedeira:

- **Docker Desktop** (Windows/macOS) ou **Docker Engine** (Linux) — testado com Docker 29.x

PHP, Composer e MySQL **não** precisam ser instalados — tudo roda em containers.

---

## Subir a aplicação

Na pasta do projeto:

```powershell
docker compose up -d --build
```

Na primeira execução (~3–5 min):

1. Baixa as imagens (`php:8.2-apache`, `mariadb:11`) — ~600 MB
2. Instala extensões PHP (`pdo_mysql`, `curl`, `mbstring`, `zip`, `gd`, `intl`)
3. Instala Composer no container
4. Roda `composer install` (popula `vendor/`)
5. Inicializa o banco a partir de `sql/schema.sql`
6. Apache começa a servir em `:80`

Quando os 2 containers ficam **healthy**, abrir:

- **App:** http://localhost:8080
- **MariaDB (host):** `localhost:3307` · user `isbn_user` / senha `changeme` / db `isbn_app`

---

## Comandos do dia a dia

```powershell
# Status dos containers
docker compose ps

# Logs em tempo real
docker compose logs -f app           # Apache + PHP
docker compose logs -f db            # MariaDB
docker exec isbn_app tail -f logs/app-$(Get-Date -Format yyyy-MM-dd).log

# Shell dentro do container
docker compose exec app bash

# Acessar o banco
docker compose exec db mariadb -u isbn_user -pchangeme isbn_app

# Parar
docker compose down

# Parar e apagar o banco (zera tudo)
docker compose down -v
```

---

## Arquitetura dos containers

```
┌────────────────────────────────────┐    ┌─────────────────────────┐
│  isbn_app                          │    │  isbn_db                │
│  php:8.2-apache + Composer         │────│  mariadb:11             │
│  Porta: 80  → host 8080            │    │  Porta: 3306 → host 3307│
│  Volume: ./ → /var/www/html        │    │  Volume: db_data        │
└────────────────────────────────────┘    └─────────────────────────┘
            ↑
    DocumentRoot=/public, Alias /api → /api
```

- **isbn_app**: build customizado de `.docker/Dockerfile`, monta o projeto como
  volume para que edições no código tenham efeito imediato (sem rebuild).
- **isbn_db**: schema carregado automaticamente via
  `sql/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql:ro` na 1ª subida.
- O Apache (`.docker/apache-vhost.conf`) aponta DocumentRoot para `public/` e
  cria um alias `/api → api/` para preservar a separação de pastas.

---

## Configuração — `.env`

Arquivo de configuração no diretório raiz. **Já vem pronto para Docker.**

```env
# Banco — para Docker NÃO altere DB_HOST (precisa ser "db")
DB_HOST=db
DB_PORT=3306
DB_NAME=isbn_app
DB_USER=isbn_user
DB_PASS=changeme

# Defaults pré-preenchidos na tela de Campos Complementares HYB.
# Operador pode editar ou apagar. Campos apagados vão em branco no XLSX.
HYB_UNIDADE_DEFAULT=UN
HYB_CATEGORIA_DEFAULT=Livros
HYB_NCM_DEFAULT=4901.99.00
HYB_PATRIMONIO_DEFAULT=N
HYB_TIPO_DEFAULT=Móvel
HYB_ESTOQUE_MINIMO_DEFAULT=0
HYB_ESTOQUE_INICIAL_QTD_DEFAULT=1

# APIs
GOOGLE_BOOKS_API_KEY=                 # opcional; aumenta cota de ~1k/dia para ~100k
APP_TIMEOUT_API_SEGUNDOS=5
APP_CACHE_DIAS=30                     # reconsulta do mesmo ISBN dentro disso vem do banco

# App
APP_TIMEZONE=America/Sao_Paulo
APP_DEBUG=false
EXPORT_DIR=storage/exports
```

Após editar `.env`, reinicie só o container do app (preserva o banco):

```powershell
docker compose restart app
```

---

## Estrutura do projeto

```
projeto-isbn/
├── public/                 # DocumentRoot servido pelo Apache
│   ├── index.php           # Tela de bipagem
│   ├── lista.php           # Livros cadastrados
│   └── assets/             # CSS + JS
├── api/                    # Endpoints HTTP (consultar, livros, exportar)
├── src/
│   ├── Domain/             # Núcleo isolado (entidades, VOs, services, ports)
│   ├── Application/        # Casos de uso (orquestração)
│   └── Infrastructure/     # Adaptadores: HTTP, persistência, export
├── bootstrap/container.php # Injeção de dependências (único wiring)
├── sql/schema.sql          # Inicialização do MariaDB
├── storage/exports/        # XLSX gerados (persistido no host)
├── logs/                   # Logs do app (app-YYYY-MM-DD.log)
├── .docker/                # Dockerfile + apache vhost + entrypoint
├── docker-compose.yml
├── composer.json
└── .env
```

---

## Implantação em outro servidor

Para subir em outra máquina (Windows, Linux ou macOS) com Docker:

1. **Copiar a pasta inteira** do projeto.
2. (Opcional) Editar `.env` se quiser alterar credenciais/portas.
3. Executar `docker compose up -d --build`.

A primeira execução é demorada (build + composer). Execuções seguintes (`docker compose up -d`, sem `--build`) sobem em segundos.

### Persistência

- Dados do banco ficam no volume nomeado `db_data` (sobrevive a `docker compose down`, é apagado por `docker compose down -v`).
- Arquivos XLSX gerados ficam em `storage/exports/` no host (não some).
- Logs em `logs/` no host.

### Portas em conflito

Se as portas `8080` ou `3307` já estão em uso, edite o `docker-compose.yml`:

```yaml
ports:
  - "8081:80"        # app em http://localhost:8081
  - "3308:3306"      # banco em localhost:3308
```

E refaça `docker compose up -d`.

---

## Sem Docker (alternativa)

Se preferir rodar nativo, é preciso PHP 8.1+, Composer e MySQL/MariaDB:

```powershell
composer install
# Edite .env: DB_HOST=localhost
mysql -u root -p < sql/schema.sql
php -S localhost:8080 -t public
```

Em produção, aponte o DocumentRoot do Apache/Nginx para `public/` e crie um alias `/api → api/`. Use `.docker/apache-vhost.conf` como referência.

---

## Critérios de aceitação validados

- ✅ Bipar ISBN-13 válido exibe todos os dados em ≤2 s (típico ~300 ms)
- ✅ Capa renderizada quando disponível
- ✅ Cache responde em <50 ms
- ✅ ISBN inválido é rejeitado sem chamar APIs (dígito verificador)
- ✅ Fallback BrasilAPI → Google Books → Open Library
- ✅ Merge automático quando primeira API retorna dados pobres
- ✅ Campos HYB pré-preenchidos com defaults do `.env`
- ✅ Nenhum campo HYB obrigatório
- ✅ XLSX com cabeçalhos exatos do template HYB (incluindo espaços nas col. M/N)
- ✅ Comentários nas células A, D, L do XLSX
- ✅ EAN e NCM exportados como texto
- ✅ Listagem com paginação, busca e exportação parcial
- ✅ Trocar provider ou banco exige só novo adaptador (arquitetura hexagonal)

---

## Stack técnica

- **PHP 8.2** + Apache 2.4
- **MariaDB 11**
- **PhpSpreadsheet** ^2.0 (geração de XLSX)
- **vlucas/phpdotenv** ^5.6 (configuração)
- Frontend HTML5 + JS puro (sem framework)
- Arquitetura **Hexagonal (Ports & Adapters)**

---

## Troubleshooting

| Problema | Causa provável | Solução |
|---|---|---|
| `docker compose up` falha em "Cannot connect to Docker daemon" | Docker Desktop não está aberto | Abrir Docker Desktop e aguardar o ícone ficar verde |
| Composer travado em "Extracting archive" | I/O lento no bind mount Windows→Linux (~3 min) | Aguardar — só acontece na primeira subida |
| HTTP 500 ao consultar ISBN | Banco ainda inicializando | `docker compose ps` deve mostrar `db` como `healthy` |
| Bipa código e nada acontece | Foco fora do campo de ISBN | Clicar no campo grande da tela; ele tem autofocus |
| ISBN válido retorna "não encontrado" | Livro não está no CBL nem Google Books | Veja [docs/APIS.md](docs/APIS.md) sobre cobertura |
| Erro de TLS com a BrasilAPI no container | JA3 fingerprint do libcurl bloqueado pelo Cloudflare | Já mitigado — o `HttpClient` usa `stream_context` em vez de libcurl |

Para mais detalhes operacionais (uso das telas, atalhos, fluxos), consulte [docs/USO.md](docs/USO.md).
