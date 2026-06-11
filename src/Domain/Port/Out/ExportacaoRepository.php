<?php
declare(strict_types=1);

namespace App\Domain\Port\Out;

use App\Domain\Entity\Exportacao;

/**
 * Porta de saída para persistir/consultar baixas (histórico de exportações HYB).
 *
 * O método registrar() recebe o cabeçalho da baixa (com id=0, será atribuído
 * pelo adapter) e a lista de itens. A implementação DEVE rodar tudo em uma
 * única transação:
 *   1) INSERT em exportacoes_hyb -> obtém o id;
 *   2) INSERT em massa em exportacoes_hyb_itens;
 *   3) UPDATE livros SET exportado_em = NOW() para todos os livros do batch;
 *   4) COMMIT (rollback em qualquer erro).
 *
 * @phpstan-type ItemBaixa array{livro_id:int, quantidade:int|null}
 */
interface ExportacaoRepository
{
    /**
     * Registra uma baixa completa (cabeçalho + itens) atomicamente.
     *
     * @param Exportacao $cabecalho  Cabeçalho com id=0 (será ignorado e
     *                               substituído pelo lastInsertId).
     * @param array<int, array{livro_id:int, quantidade:int|null}> $itens
     *                               Lista de itens; quantidade=null = sem override.
     * @return int                   ID do batch criado (exportacoes_hyb.id).
     */
    public function registrar(Exportacao $cabecalho, array $itens): int;

    /**
     * Última baixa em que o livro apareceu (NULL se nunca foi baixado).
     */
    public function buscarUltimaBaixaPorLivro(int $livroId): ?Exportacao;

    /**
     * Quantas vezes o livro já foi incluído em baixas (decisão #8 — sem purga).
     */
    public function contarBaixasPorLivro(int $livroId): int;
}
