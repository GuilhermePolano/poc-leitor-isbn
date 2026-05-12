<?php
declare(strict_types=1);

namespace App\Application;

use App\Domain\Entity\CamposHyb;
use App\Domain\Entity\Livro;
use App\Domain\Port\In\ConsultarLivroPorIsbnUseCase;
use App\Domain\Port\Out\HistoricoBipagemRepository;
use App\Domain\Port\Out\IsbnProvider;
use App\Domain\Port\Out\LivroRepository;
use App\Domain\Port\Out\Logger;
use App\Domain\ValueObject\ISBN;
use InvalidArgumentException;
use Throwable;

final class ConsultarLivroPorIsbnService implements ConsultarLivroPorIsbnUseCase
{
    public function __construct(
        private readonly IsbnProvider $provider,
        private readonly LivroRepository $repo,
        private readonly HistoricoBipagemRepository $historicoRepo,
        private readonly Logger $logger,
        private readonly int $cacheDias = 30,
    ) {}

    public function executar(string $isbn, bool $forcarReconsulta = false): array
    {
        $inicio = microtime(true);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            $vo = ISBN::criar($isbn);
        } catch (InvalidArgumentException $e) {
            $this->historicoRepo->registrar($isbn, null, false, null, $e->getMessage(), $ip);
            return [
                'sucesso' => false,
                'erro'    => $e->getMessage(),
                'isbn'    => $isbn,
                'tempo_ms' => $this->ms($inicio),
            ];
        }

        // Tenta cache (livro já consultado dentro da janela)
        if (!$forcarReconsulta) {
            $existente = $this->repo->buscarPorIsbn($vo);
            if ($existente !== null && $this->dentroDoCache($existente)) {
                $this->historicoRepo->registrar((string) $vo, $existente->id, true, 'cache', null, $ip);
                return [
                    'sucesso' => true,
                    'origem'  => 'cache',
                    'tempo_ms' => $this->ms($inicio),
                    'livro'   => $existente->dadosApi->toArray(),
                    'hyb'     => $existente->hyb->toArray(),
                    'cadastrado' => true,
                    'livro_id' => $existente->id,
                ];
            }
        }

        $dados = null;
        try {
            $dados = $this->provider->buscarPorIsbn($vo);
        } catch (Throwable $e) {
            $this->logger->error('Falha no provider de ISBN', [
                'isbn' => (string) $vo,
                'erro' => $e->getMessage(),
            ]);
        }

        if ($dados === null) {
            $this->historicoRepo->registrar((string) $vo, null, false, $this->provider->nome(), 'Nenhuma API retornou dados', $ip);
            return [
                'sucesso'  => false,
                'erro'     => 'ISBN não encontrado em nenhuma fonte',
                'isbn'     => (string) $vo,
                'tempo_ms' => $this->ms($inicio),
            ];
        }

        $this->logger->info('ISBN consultado', [
            'isbn' => (string) $vo,
            'origem' => $dados->fonteApi,
            'tempo_ms' => $this->ms($inicio),
        ]);

        $this->historicoRepo->registrar((string) $vo, null, true, $dados->fonteApi, null, $ip);

        // Verifica se já existe no banco para retornar hyb cadastrado
        $existente = $this->repo->buscarPorIsbn($vo);
        $hyb = $existente?->hyb ?? CamposHyb::vazio();

        return [
            'sucesso' => true,
            'origem'  => $dados->fonteApi,
            'provider_origem' => $dados->providerOrigem,
            'tempo_ms' => $this->ms($inicio),
            'livro'   => $dados->toArray(),
            'hyb'     => $hyb->toArray(),
            'cadastrado' => $existente !== null,
            'livro_id'   => $existente?->id,
        ];
    }

    private function dentroDoCache(Livro $livro): bool
    {
        if ($livro->consultadoEm === null) {
            return false;
        }
        $consultado = strtotime($livro->consultadoEm);
        if ($consultado === false) {
            return false;
        }
        $limite = time() - ($this->cacheDias * 86400);
        return $consultado >= $limite;
    }

    private function ms(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }
}
