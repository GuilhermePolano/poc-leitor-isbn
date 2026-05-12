<?php
declare(strict_types=1);

namespace App\Application;

use App\Domain\Port\In\ExportarLivrosParaHybUseCase;
use App\Domain\Port\Out\ExportadorDeLivros;
use App\Domain\Port\Out\LivroRepository;
use App\Domain\Port\Out\Logger;
use RuntimeException;

final class ExportarLivrosParaHybService implements ExportarLivrosParaHybUseCase
{
    public function __construct(
        private readonly LivroRepository $repo,
        private readonly ExportadorDeLivros $exportador,
        private readonly Logger $logger,
        private readonly string $diretorioExports,
    ) {}

    public function executar(array $filtros = []): string
    {
        $ids = $filtros['ids'] ?? null;
        $apenasNaoExportados = (bool) ($filtros['apenas_nao_exportados'] ?? false);

        if ($ids !== null && count($ids) > 0) {
            $livros = $this->repo->buscarPorIds($ids);
        } elseif ($apenasNaoExportados) {
            $livros = $this->repo->listarNaoExportados();
        } else {
            $livros = $this->repo->listar(1000000, 0);
        }

        if (count($livros) === 0) {
            throw new RuntimeException('Nenhum livro disponível para exportar.');
        }

        if (!is_dir($this->diretorioExports)) {
            mkdir($this->diretorioExports, 0775, true);
        }

        $nomeArquivo = sprintf('HYBIntegrador_bens_%s.xlsx', date('Ymd_His'));
        $caminho     = rtrim($this->diretorioExports, '/\\') . DIRECTORY_SEPARATOR . $nomeArquivo;

        $this->exportador->exportar($livros, $caminho);

        $idsExportados = array_filter(array_map(fn ($l) => $l->id, $livros));
        if (count($idsExportados) > 0) {
            $this->repo->marcarComoExportados(array_values($idsExportados), date('Y-m-d H:i:s'));
        }

        $this->logger->info('Exportação HYB gerada', [
            'arquivo' => $caminho,
            'qtd'     => count($livros),
        ]);

        return $caminho;
    }
}
