<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapter\Out\IsbnProvider;

use App\Domain\Entity\DadosBibliograficos;
use App\Domain\Port\Out\IsbnProvider;
use App\Domain\Port\Out\Logger;
use App\Domain\Service\NormalizadorDeLivro;
use App\Domain\ValueObject\ISBN;
use Throwable;

/**
 * Orquestra a busca de ISBN entre vários providers.
 *
 * Estratégia: consulta os providers em ordem; para **assim que** o primeiro
 * resultado vier "rico o suficiente" (título + autores + sinopse OU capa). Se
 * o primeiro retornar pobre (ex.: CBL com só título e editora), tenta os
 * próximos e **mescla** os campos faltantes — preservando a precedência do
 * primeiro provider para campos preenchidos.
 */
final class CompositeIsbnProvider implements IsbnProvider
{
    /** @param IsbnProvider[] $providers */
    public function __construct(
        private readonly array $providers,
        private readonly Logger $logger,
        private readonly NormalizadorDeLivro $normalizador = new NormalizadorDeLivro(),
    ) {}

    public function nome(): string
    {
        return 'composite';
    }

    public function buscarPorIsbn(ISBN $isbn): ?DadosBibliograficos
    {
        $coletados = [];

        foreach ($this->providers as $provider) {
            try {
                $resultado = $provider->buscarPorIsbn($isbn);
                if ($resultado === null) {
                    $this->logger->info('Provider sem dados', [
                        'provider' => $provider->nome(),
                        'isbn'     => $isbn->isbn13(),
                    ]);
                    continue;
                }
                $coletados[] = $resultado;
                $this->logger->info('Provider acertou', [
                    'provider' => $provider->nome(),
                    'isbn'     => $isbn->isbn13(),
                    'qualidade' => $this->classificarQualidade($resultado),
                ]);
                // Se o primeiro já veio rico, não chama os demais
                if (count($coletados) === 1 && $this->resultadoRico($resultado)) {
                    break;
                }
            } catch (Throwable $e) {
                $this->logger->warn('Falha em provider, seguindo fallback', [
                    'provider' => $provider->nome(),
                    'erro'     => $e->getMessage(),
                ]);
            }
        }

        if (count($coletados) === 0) {
            return null;
        }
        if (count($coletados) === 1) {
            return $coletados[0];
        }

        $this->logger->info('Mesclando resultados de múltiplas APIs', [
            'isbn'  => $isbn->isbn13(),
            'qtd'   => count($coletados),
            'fontes' => array_map(fn ($r) => $r->fonteApi, $coletados),
        ]);
        return $this->normalizador->mesclar(...$coletados);
    }

    /**
     * Define "rico o suficiente" como tendo título + (autores OU sinopse OU capa).
     * Se vier só título e editora (cadastro raso do CBL), busca enriquecimento.
     */
    private function resultadoRico(DadosBibliograficos $r): bool
    {
        $temTitulo = trim($r->titulo) !== '';
        $temAutores = count($r->autores) > 0;
        $temSinopse = $r->sinopse !== null && trim($r->sinopse) !== '';
        $temCapa = $r->capaUrl !== null && $r->capaUrl !== '';
        return $temTitulo && ($temAutores || $temSinopse || $temCapa);
    }

    private function classificarQualidade(DadosBibliograficos $r): string
    {
        return $this->resultadoRico($r) ? 'rico' : 'pobre';
    }
}
