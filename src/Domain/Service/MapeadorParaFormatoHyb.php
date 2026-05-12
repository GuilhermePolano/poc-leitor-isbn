<?php
declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Livro;

/**
 * Converte um Livro em um array com as colunas do formato HYB Integrador.
 *
 * Regra:
 *   1º valor digitado pelo operador na tela (mesmo vazio) →
 *   2º valor derivado da API (preço, ISBN-10) →
 *   3º vazio.
 *
 * Defaults do .env NÃO entram aqui — eles são aplicados na tela de consulta
 * no momento do pré-preenchimento. Quando o operador apaga um campo, ele
 * vem como null e desce vazio para o XLSX.
 */
final class MapeadorParaFormatoHyb
{
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
            // Coluna D — Categoria
            'categoria' => $hyb->categoria ?? '',
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
            // Coluna O — Descrição
            'descricao' => $hyb->descricao ?? '',
        ];
    }
}
