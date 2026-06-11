<?php
declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Livro;
use App\Domain\Port\Out\CategoriaRepository;

/**
 * Converte um Livro em um array com as colunas do formato HYB Integrador.
 *
 * Regra geral de precedência por campo:
 *   1º valor digitado pelo operador na tela (mesmo vazio) →
 *   2º valor derivado da API (preço, ISBN-10) →
 *   3º vazio.
 *
 * Defaults do .env NÃO entram aqui (decisão #9) — eles são aplicados na tela
 * de consulta no momento do pré-preenchimento. Quando o operador apaga um
 * campo, ele vem como null e desce vazio para o XLSX.
 *
 * Coluna D — Categoria (decisão #11):
 *   - Se o livro tem `categoria_id` setado → resolvemos o nome via
 *     CategoriaRepository (categoria.descricao) e escrevemos isso no XLSX.
 *   - Senão, caímos no snapshot legado `hyb.categoria` (string) ou vazio.
 *   - Não há fallback para defaults .env aqui (decisão #9).
 *
 * Coluna O — Descrição (decisão M16):
 *   - Se o operador deixou algo em `hyb.descricao` (mesmo que editado a partir
 *     do template gerado na tela de consulta) → respeita o que ele digitou.
 *   - Se está vazio (livro antigo, importação direta, etc.) → gera no momento
 *     da exportação via GeradorDescricaoLivro a partir de dadosApi.
 */
final class MapeadorParaFormatoHyb
{
    /** Cache local para evitar buscas repetidas no mesmo batch. */
    private array $cacheCategorias = [];

    public function __construct(
        private readonly ?CategoriaRepository $categoriaRepository = null,
        private readonly ?GeradorDescricaoLivro $geradorDescricao = null,
    ) {}

    public function mapear(Livro $livro): array
    {
        $api = $livro->dadosApi;
        $hyb = $livro->hyb;

        $titulo = trim(($api->titulo ?? '') . ($api->subtitulo ? ' — ' . $api->subtitulo : ''));

        return [
            // Coluna A — Bem/Produto (apenas edição no HYB)
            'bem_produto' => $hyb->bemProduto ?? '',
            // Coluna B — Título (sempre vem da API)
            'titulo' => $titulo,
            // Coluna C — Unidade
            'unidade' => $hyb->unidade ?? '',
            // Coluna D — Categoria (nome, decisão #11)
            'categoria' => $this->resolverCategoria($hyb->categoriaId, $hyb->categoria),
            // Coluna E — Código de Barras (EAN) — ISBN-13 do livro
            'ean' => $api->isbn13,
            // Coluna F — NCM
            'ncm' => $hyb->ncm ?? '',
            // Coluna G — Preço de Venda
            'preco_venda' => $hyb->precoVenda !== null ? (float) $hyb->precoVenda : '',
            // Coluna H — Estoque Mínimo
            'estoque_minimo' => $hyb->estoqueMinimo !== null ? (int) $hyb->estoqueMinimo : '',
            // Coluna I — Referência
            'referencia' => $hyb->referencia ?? '',
            // Coluna J — Patrimônio(S,N)
            'patrimonio' => $hyb->patrimonio ?? '',
            // Coluna K — Depreciação(%)
            'depreciacao' => $hyb->depreciacaoPct !== null ? (float) $hyb->depreciacaoPct : '',
            // Coluna L — Tipo
            'tipo' => $hyb->tipo ?? '',
            // Coluna M — Estoque Inicial Quantidade
            'estoque_inicial_qtd' => $hyb->estoqueInicialQtd !== null ? (float) $hyb->estoqueInicialQtd : '',
            // Coluna N — Estoque Inicial Custo Unitário
            'estoque_inicial_custo' => $hyb->estoqueInicialCusto !== null ? (float) $hyb->estoqueInicialCusto : '',
            // Coluna O — Descrição (decisão M16, ver doc da classe)
            'descricao' => $this->resolverDescricao($livro),
        ];
    }

    /**
     * Precedência da descrição (coluna O):
     *   1) o que o operador deixou em hyb.descricao (mesmo após editar o
     *      template pré-gerado na tela de consulta);
     *   2) descrição enriquecida gerada agora a partir de dadosApi via
     *      GeradorDescricaoLivro (fallback para livros antigos / batch);
     *   3) vazio (se nada do acima).
     */
    private function resolverDescricao(Livro $livro): string
    {
        $manual = $livro->hyb->descricao;
        if ($manual !== null && trim($manual) !== '') {
            return $manual;
        }
        if ($this->geradorDescricao !== null) {
            return $this->geradorDescricao->gerar($livro);
        }
        return '';
    }

    /**
     * Resolve o nome da categoria para a coluna D do XLSX.
     *
     * Precedência:
     *   1) categoria_id → CategoriaRepository::buscarPorId()->descricao
     *   2) string legada hyb.categoria
     *   3) vazio
     *
     * NÃO aplica default .env (decisão #9): se o operador não vinculou
     * categoria_id e não tem snapshot legado, vai vazio.
     */
    private function resolverCategoria(?int $categoriaId, ?string $categoriaLegada): string
    {
        if ($categoriaId !== null && $this->categoriaRepository !== null) {
            if (!array_key_exists($categoriaId, $this->cacheCategorias)) {
                $cat = $this->categoriaRepository->buscarPorId($categoriaId);
                $this->cacheCategorias[$categoriaId] = $cat?->descricao;
            }
            $descricao = $this->cacheCategorias[$categoriaId];
            if ($descricao !== null && $descricao !== '') {
                return $descricao;
            }
        }

        return $categoriaLegada ?? '';
    }
}
