<?php
declare(strict_types=1);

namespace App\Domain\Entity;

/**
 * Campos complementares HYB preenchidos pelo operador.
 * Todos opcionais — null = célula vazia no XLSX e NULL no banco.
 */
final class CamposHyb
{
    public function __construct(
        public readonly ?string $bemProduto = null,
        public readonly ?string $unidade = null,
        public readonly ?string $categoria = null,
        public readonly ?string $ncm = null,
        public readonly ?float $precoVenda = null,
        public readonly ?int $estoqueMinimo = null,
        public readonly ?string $referencia = null,
        public readonly ?string $patrimonio = null,
        public readonly ?float $depreciacaoPct = null,
        public readonly ?string $tipo = null,
        public readonly ?float $estoqueInicialQtd = null,
        public readonly ?float $estoqueInicialCusto = null,
        public readonly ?string $descricao = null,
    ) {}

    public function toArray(): array
    {
        return [
            'bem_produto'       => $this->bemProduto,
            'unidade'           => $this->unidade,
            'categoria'         => $this->categoria,
            'ncm'               => $this->ncm,
            'preco_venda'       => $this->precoVenda,
            'estoque_minimo'    => $this->estoqueMinimo,
            'referencia'        => $this->referencia,
            'patrimonio'        => $this->patrimonio,
            'depreciacao_pct'   => $this->depreciacaoPct,
            'tipo'              => $this->tipo,
            'estoque_ini_qtd'   => $this->estoqueInicialQtd,
            'estoque_ini_custo' => $this->estoqueInicialCusto,
            'descricao'         => $this->descricao,
        ];
    }

    public static function fromArray(array $dados): self
    {
        $get = static function (array $a, string $chave) {
            if (!array_key_exists($chave, $a)) {
                return null;
            }
            $v = $a[$chave];
            if (is_string($v) && trim($v) === '') {
                return null;
            }
            return $v;
        };

        $toFloat = static fn($v) => $v === null ? null : (float) str_replace(',', '.', (string) $v);
        $toInt   = static fn($v) => $v === null ? null : (int) $v;

        return new self(
            bemProduto:           $get($dados, 'bem_produto')         !== null ? (string) $get($dados, 'bem_produto') : null,
            unidade:              $get($dados, 'unidade')             !== null ? (string) $get($dados, 'unidade') : null,
            categoria:            $get($dados, 'categoria')           !== null ? (string) $get($dados, 'categoria') : null,
            ncm:                  $get($dados, 'ncm')                 !== null ? (string) $get($dados, 'ncm') : null,
            precoVenda:           $toFloat($get($dados, 'preco_venda')),
            estoqueMinimo:        $toInt($get($dados, 'estoque_minimo')),
            referencia:           $get($dados, 'referencia')          !== null ? (string) $get($dados, 'referencia') : null,
            patrimonio:           $get($dados, 'patrimonio')          !== null ? strtoupper((string) $get($dados, 'patrimonio')) : null,
            depreciacaoPct:       $toFloat($get($dados, 'depreciacao_pct')),
            tipo:                 $get($dados, 'tipo')                !== null ? (string) $get($dados, 'tipo') : null,
            estoqueInicialQtd:    $toFloat($get($dados, 'estoque_ini_qtd')),
            estoqueInicialCusto:  $toFloat($get($dados, 'estoque_ini_custo')),
            descricao:            $get($dados, 'descricao')           !== null ? (string) $get($dados, 'descricao') : null,
        );
    }

    public static function vazio(): self
    {
        return new self();
    }
}
