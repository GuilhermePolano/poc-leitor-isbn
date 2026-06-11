<?php
declare(strict_types=1);

namespace App\Application;

use App\Domain\Entity\CamposHyb;
use App\Domain\Entity\Exportacao;
use App\Domain\Entity\Livro;
use App\Domain\Port\In\ExportarLivrosParaHybUseCase;
use App\Domain\Port\Out\ExportacaoRepository;
use App\Domain\Port\Out\ExportadorDeLivros;
use App\Domain\Port\Out\LivroRepository;
use App\Domain\Port\Out\Logger;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Caso de uso de exportação HYB.
 *
 * Decisões aplicadas:
 *  - #1 : registra o histórico (cabeçalho + itens) somente após Writer::save()
 *         OK. Se a geração falhar, o arquivo (se existir) é removido e nada
 *         é gravado em exportacoes_hyb / exportacoes_hyb_itens.
 *  - #12: a quantidade do modal de baixa é um override só para esta baixa.
 *         Aplicamos via clone do Livro com hyb.estoqueInicialQtd substituído
 *         (sem persistir) e gravamos o snapshot em exportacoes_hyb_itens.
 */
final class ExportarLivrosParaHybService implements ExportarLivrosParaHybUseCase
{
    public function __construct(
        private readonly LivroRepository $repo,
        private readonly ExportadorDeLivros $exportador,
        private readonly ExportacaoRepository $exportacoes,
        private readonly Logger $logger,
        private readonly string $diretorioExports,
    ) {}

    /**
     * Aceita os seguintes formatos de entrada (retro-compat):
     *
     *  Forma legada (ainda suportada pelo endpoint GET):
     *    $filtros = [
     *      'ids' => [1, 2, ...],
     *      'apenas_nao_exportados' => bool,
     *    ]
     *
     *  Forma nova (POST JSON):
     *    $filtros = [
     *      'ids' => [1, 2, ...],
     *      'quantidades' => [1 => 3, 2 => 5, ...],   // override por livro
     *      'origem' => 'lista' | 'atalho_bipagem',
     *    ]
     */
    public function executar(array $filtros = []): string
    {
        $ids                 = $filtros['ids'] ?? null;
        $apenasNaoExportados = (bool) ($filtros['apenas_nao_exportados'] ?? false);
        $quantidadesOverride = $this->normalizarOverrides($filtros['quantidades'] ?? []);
        $origem              = $this->normalizarOrigem($filtros['origem'] ?? null);

        // 1) Carrega livros ---------------------------------------------------
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

        // 2) Aplica overrides em memória (decisão #12) -----------------------
        // Não persistimos nada; clonamos o livro substituindo apenas a qtd.
        $livrosParaExportar = [];
        $itensSnapshot      = []; // material p/ exportacoes_hyb_itens
        foreach ($livros as $livro) {
            $livroId = $livro->id;
            $override = null;
            if ($livroId !== null && array_key_exists($livroId, $quantidadesOverride)) {
                $override = $quantidadesOverride[$livroId];
            }

            $livrosParaExportar[] = $override !== null
                ? $this->aplicarOverrideQuantidade($livro, $override)
                : $livro;

            if ($livroId !== null) {
                $itensSnapshot[] = [
                    'livro_id'   => $livroId,
                    'quantidade' => $override, // null = sem override
                ];
            }
        }

        // 3) Garante diretório e gera o arquivo ------------------------------
        if (!is_dir($this->diretorioExports)) {
            mkdir($this->diretorioExports, 0775, true);
        }

        $nomeArquivo = sprintf('HYBIntegrador_bens_%s.xlsx', date('Ymd_His'));
        $caminho     = rtrim($this->diretorioExports, '/\\') . DIRECTORY_SEPARATOR . $nomeArquivo;

        try {
            $this->exportador->exportar($livrosParaExportar, $caminho);
        } catch (Throwable $e) {
            // Se o save criou um arquivo parcial, removemos. NÃO registramos histórico (decisão #1).
            if (is_file($caminho)) {
                @unlink($caminho);
            }
            $this->logger->error('Falha ao gerar XLSX HYB — histórico NÃO registrado', [
                'arquivo' => $caminho,
                'erro'    => $e->getMessage(),
            ]);
            throw new RuntimeException('Falha ao gerar XLSX HYB: ' . $e->getMessage(), 0, $e);
        }

        // 4) Save OK → registra histórico em transação (decisão #1) ----------
        if (count($itensSnapshot) > 0) {
            try {
                $cabecalho = new Exportacao(
                    id:           0,
                    arquivo:      $nomeArquivo,
                    qtdRegistros: count($itensSnapshot),
                    geradoEm:     new DateTimeImmutable('now'),
                    usuario:      null,
                    origem:       $origem,
                );
                $this->exportacoes->registrar($cabecalho, $itensSnapshot);
            } catch (Throwable $e) {
                // Histórico falhou após o arquivo já ter sido gerado.
                // Mantemos o arquivo (já entregue ao operador no fluxo HTTP)
                // mas logamos a inconsistência para investigação manual.
                $this->logger->error('XLSX gerado, mas registrar() do histórico falhou', [
                    'arquivo' => $caminho,
                    'erro'    => $e->getMessage(),
                ]);
                throw new RuntimeException(
                    'XLSX gerado, mas o histórico não pôde ser registrado: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        $this->logger->info('Exportação HYB gerada', [
            'arquivo' => $caminho,
            'qtd'     => count($livrosParaExportar),
            'origem'  => $origem,
        ]);

        return $caminho;
    }

    /**
     * @param array<int|string, mixed> $raw
     * @return array<int, int>  mapa livroId => quantidade (>0)
     */
    private function normalizarOverrides(array $raw): array
    {
        $out = [];
        foreach ($raw as $k => $v) {
            $livroId = (int) $k;
            if ($livroId <= 0 || $v === null || $v === '') {
                continue;
            }
            $qtd = (int) $v;
            if ($qtd <= 0) {
                continue;
            }
            $out[$livroId] = $qtd;
        }
        return $out;
    }

    private function normalizarOrigem(?string $valor): string
    {
        $valor = $valor !== null ? trim($valor) : '';
        if ($valor === Exportacao::ORIGEM_ATALHO) {
            return Exportacao::ORIGEM_ATALHO;
        }
        return Exportacao::ORIGEM_LISTA;
    }

    /**
     * Clona o livro com a nova quantidade de estoque inicial (override).
     * Não toca no banco — é um snapshot só para esta baixa (decisão #12).
     */
    private function aplicarOverrideQuantidade(Livro $livro, int $quantidade): Livro
    {
        $hybAtual = $livro->hyb;
        $novoHyb = new CamposHyb(
            bemProduto:          $hybAtual->bemProduto,
            unidade:             $hybAtual->unidade,
            categoria:           $hybAtual->categoria,
            ncm:                 $hybAtual->ncm,
            precoVenda:          $hybAtual->precoVenda,
            estoqueMinimo:       $hybAtual->estoqueMinimo,
            referencia:          $hybAtual->referencia,
            patrimonio:          $hybAtual->patrimonio,
            depreciacaoPct:      $hybAtual->depreciacaoPct,
            tipo:                $hybAtual->tipo,
            estoqueInicialQtd:   $quantidade,
            estoqueInicialCusto: $hybAtual->estoqueInicialCusto,
            descricao:           $hybAtual->descricao,
            categoriaId:         $hybAtual->categoriaId,
        );

        return new Livro(
            id:           $livro->id,
            dadosApi:     $livro->dadosApi,
            hyb:          $novoHyb,
            consultadoEm: $livro->consultadoEm,
            atualizadoEm: $livro->atualizadoEm,
            exportadoEm:  $livro->exportadoEm,
        );
    }
}
