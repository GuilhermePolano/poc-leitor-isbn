<?php
declare(strict_types=1);

/**
 * Composição da aplicação (injeção de dependências manual).
 * Aqui — e somente aqui — os adaptadores são "plugados" nas portas.
 *
 * Para trocar BrasilAPI por outra API, ou MySQL por outro banco, basta
 * trocar o adaptador instanciado abaixo. O domínio não muda.
 */

use App\Application\ConsultarLivroPorIsbnService;
use App\Application\ExportarLivrosParaHybService;
use App\Application\ListarCategoriasService;
use App\Application\ListarLivrosCadastradosService;
use App\Application\SalvarLivroService;
use App\Domain\Port\In\ConsultarLivroPorIsbnUseCase;
use App\Domain\Port\In\ExportarLivrosParaHybUseCase;
use App\Domain\Port\In\ListarLivrosCadastradosUseCase;
use App\Domain\Port\In\SalvarLivroUseCase;
use App\Domain\Port\Out\CategoriaRepository;
use App\Domain\Port\Out\ExportacaoRepository;
use App\Domain\Port\Out\ExportadorDeLivros;
use App\Domain\Port\Out\HistoricoBipagemRepository;
use App\Domain\Port\Out\IsbnProvider;
use App\Domain\Port\Out\LivroRepository;
use App\Domain\Port\Out\Logger;
use App\Domain\Service\GeradorDescricaoLivro;
use App\Domain\Service\IdiomaNormalizer;
use App\Domain\Service\MapeadorParaFormatoHyb;
use App\Domain\Service\NormalizadorDeLivro;
use App\Infrastructure\Adapter\Out\Export\XlsxHybExporter;
use App\Infrastructure\Adapter\Out\IsbnProvider\BrasilApiClient;
use App\Infrastructure\Adapter\Out\IsbnProvider\CompositeIsbnProvider;
use App\Infrastructure\Adapter\Out\IsbnProvider\GoogleBooksClient;
use App\Infrastructure\Adapter\Out\IsbnProvider\HttpClient;
use App\Infrastructure\Adapter\Out\IsbnProvider\OpenLibraryClient;
use App\Infrastructure\Adapter\Out\Persistence\MySqlCategoriaRepository;
use App\Infrastructure\Adapter\Out\Persistence\MySqlExportacaoRepository;
use App\Infrastructure\Adapter\Out\Persistence\MySqlHistoricoBipagemRepository;
use App\Infrastructure\Adapter\Out\Persistence\MySqlLivroRepository;
use App\Infrastructure\Logger\FileLogger;
use App\Infrastructure\Persistence\ConexaoPdo;

require_once __DIR__ . '/../vendor/autoload.php';

// Carrega .env (raiz do projeto)
$raiz = dirname(__DIR__);
if (class_exists(\Dotenv\Dotenv::class) && file_exists($raiz . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable($raiz);
    $dotenv->safeLoad();
}

// Timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo');

// Diretórios garantidos
foreach (['/storage/exports', '/logs'] as $sub) {
    if (!is_dir($raiz . $sub)) {
        @mkdir($raiz . $sub, 0775, true);
    }
}

return new class($raiz) {
    private array $servicos = [];

    public function __construct(private readonly string $raiz) {}

    public function pdo(): \PDO
    {
        return $this->singleton('pdo', fn () => ConexaoPdo::obter());
    }

    public function logger(): Logger
    {
        return $this->singleton(Logger::class, fn () => new FileLogger($this->raiz . '/logs'));
    }

    public function repositorioLivros(): LivroRepository
    {
        return $this->singleton(LivroRepository::class, fn () => new MySqlLivroRepository($this->pdo()));
    }

    public function repositorioHistorico(): HistoricoBipagemRepository
    {
        return $this->singleton(HistoricoBipagemRepository::class, fn () => new MySqlHistoricoBipagemRepository($this->pdo()));
    }

    public function repositorioCategorias(): CategoriaRepository
    {
        return $this->singleton(CategoriaRepository::class, fn () => new MySqlCategoriaRepository($this->pdo()));
    }

    public function repositorioExportacoes(): ExportacaoRepository
    {
        return $this->singleton(ExportacaoRepository::class, fn () => new MySqlExportacaoRepository($this->pdo()));
    }

    public function idiomaNormalizer(): IdiomaNormalizer
    {
        return $this->singleton(IdiomaNormalizer::class, fn () => new IdiomaNormalizer());
    }

    public function geradorDescricao(): GeradorDescricaoLivro
    {
        return $this->singleton(GeradorDescricaoLivro::class, fn () => new GeradorDescricaoLivro());
    }

    public function isbnProvider(): IsbnProvider
    {
        return $this->singleton(IsbnProvider::class, function () {
            $timeout = (int) ($_ENV['APP_TIMEOUT_API_SEGUNDOS'] ?? 5);
            $http = new HttpClient($timeout);
            $normalizador = new NormalizadorDeLivro();
            $idiomaNormalizer = $this->idiomaNormalizer();

            $providers = [
                new BrasilApiClient($http, $idiomaNormalizer),
                new GoogleBooksClient($http, $normalizador, $idiomaNormalizer, $_ENV['GOOGLE_BOOKS_API_KEY'] ?? null),
                new OpenLibraryClient($http, $normalizador, $idiomaNormalizer),
            ];

            return new CompositeIsbnProvider($providers, $this->logger());
        });
    }

    public function exportador(): ExportadorDeLivros
    {
        return $this->singleton(ExportadorDeLivros::class, fn () => new XlsxHybExporter(
            new MapeadorParaFormatoHyb(
                $this->repositorioCategorias(),
                $this->geradorDescricao(),
            )
        ));
    }

    public function casoUsoConsultar(): ConsultarLivroPorIsbnUseCase
    {
        return $this->singleton(ConsultarLivroPorIsbnUseCase::class, fn () => new ConsultarLivroPorIsbnService(
            provider: $this->isbnProvider(),
            repo: $this->repositorioLivros(),
            historicoRepo: $this->repositorioHistorico(),
            logger: $this->logger(),
            cacheDias: (int) ($_ENV['APP_CACHE_DIAS'] ?? 30),
        ));
    }

    public function casoUsoListar(): ListarLivrosCadastradosUseCase
    {
        return $this->singleton(ListarLivrosCadastradosUseCase::class, fn () => new ListarLivrosCadastradosService($this->repositorioLivros()));
    }

    public function casoUsoExportar(): ExportarLivrosParaHybUseCase
    {
        return $this->singleton(ExportarLivrosParaHybUseCase::class, fn () => new ExportarLivrosParaHybService(
            repo: $this->repositorioLivros(),
            exportador: $this->exportador(),
            exportacoes: $this->repositorioExportacoes(),
            logger: $this->logger(),
            diretorioExports: $this->raiz . '/' . ($_ENV['EXPORT_DIR'] ?? 'storage/exports'),
        ));
    }

    public function casoUsoSalvar(): SalvarLivroUseCase
    {
        return $this->singleton(SalvarLivroUseCase::class, fn () => new SalvarLivroService($this->repositorioLivros(), $this->logger()));
    }

    public function casoUsoListarCategorias(): ListarCategoriasService
    {
        return $this->singleton(ListarCategoriasService::class, fn () => new ListarCategoriasService($this->repositorioCategorias()));
    }

    public function defaultsHyb(): array
    {
        return [
            'unidade'         => $_ENV['HYB_UNIDADE_DEFAULT']             ?? 'UN',
            'categoria'       => $_ENV['HYB_CATEGORIA_DEFAULT']           ?? 'Livros',
            'ncm'             => $_ENV['HYB_NCM_DEFAULT']                 ?? '4901.99.00',
            'patrimonio'      => $_ENV['HYB_PATRIMONIO_DEFAULT']          ?? 'N',
            'tipo'            => $_ENV['HYB_TIPO_DEFAULT']                ?? 'Móvel',
            'estoque_minimo'  => $_ENV['HYB_ESTOQUE_MINIMO_DEFAULT']      ?? '0',
            'estoque_ini_qtd' => $_ENV['HYB_ESTOQUE_INICIAL_QTD_DEFAULT'] ?? '1',
        ];
    }

    public function raiz(): string
    {
        return $this->raiz;
    }

    private function singleton(string $chave, callable $factory)
    {
        if (!array_key_exists($chave, $this->servicos)) {
            $this->servicos[$chave] = $factory();
        }
        return $this->servicos[$chave];
    }
};
