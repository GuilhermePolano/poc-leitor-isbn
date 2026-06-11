<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapter\Out\Persistence;

use App\Domain\Entity\CamposHyb;
use App\Domain\Entity\DadosBibliograficos;
use App\Domain\Entity\Livro;
use App\Domain\Port\Out\LivroRepository;
use App\Domain\ValueObject\Dimensoes;
use App\Domain\ValueObject\ISBN;
use App\Domain\ValueObject\Preco;
use PDO;

final class MySqlLivroRepository implements LivroRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function salvar(Livro $livro): Livro
    {
        $api = $livro->dadosApi;
        $hyb = $livro->hyb;

        $params = [
            ':isbn_13'           => $api->isbn13,
            ':isbn_10'           => $api->isbn10,
            ':titulo'            => $api->titulo,
            ':subtitulo'         => $api->subtitulo,
            ':autores'           => $this->jsonOuNull($api->autores),
            ':editora'           => $api->editora,
            ':ano_publicacao'    => $api->anoPublicacao,
            ':data_publicacao'   => $this->dataValida($api->dataPublicacao),
            ':idioma'            => $api->idioma,
            ':paginas'           => $api->paginas,
            ':sinopse'           => $api->sinopse,
            ':assuntos'          => $this->jsonOuNull($api->assuntos),
            ':categorias'        => $this->jsonOuNull($api->categorias),
            ':formato'           => $api->formato,
            ':altura_cm'         => $api->dimensoes->alturaCm,
            ':largura_cm'        => $api->dimensoes->larguraCm,
            ':espessura_cm'      => $api->dimensoes->espessuraCm,
            ':peso'              => $api->peso,
            ':preco_moeda'       => $api->preco->moeda,
            ':preco_valor'       => $api->preco->valor,
            ':local_publicacao'  => $api->localPublicacao,
            ':capa_url'          => $api->capaUrl,
            ':capa_thumbnail'    => $api->capaThumbnail,
            ':link_preview'      => $api->linkPreview,
            ':avaliacao_media'   => $api->avaliacaoMedia,
            ':qtd_avaliacoes'    => $api->qtdAvaliacoes,
            ':payload_bruto'     => $this->jsonOuNull($api->payloadBruto),
            ':fonte_api'         => $api->fonteApi,
            ':provider_origem'   => $api->providerOrigem,
            ':consultado_em'     => $this->normalizarDateTime($livro->consultadoEm) ?? date('Y-m-d H:i:s'),
            ':atualizado_em'     => date('Y-m-d H:i:s'),
            ':hyb_bem_produto'   => $hyb->bemProduto,
            ':hyb_unidade'       => $hyb->unidade,
            ':hyb_categoria'     => $hyb->categoria,
            ':categoria_id'      => $hyb->categoriaId,
            ':hyb_ncm'           => $hyb->ncm,
            ':hyb_preco_venda'   => $hyb->precoVenda,
            ':hyb_estoque_minimo'=> $hyb->estoqueMinimo,
            ':hyb_referencia'    => $hyb->referencia,
            ':hyb_patrimonio'    => $hyb->patrimonio,
            ':hyb_depreciacao_pct' => $hyb->depreciacaoPct,
            ':hyb_tipo'          => $hyb->tipo,
            ':hyb_estoque_ini_qtd'   => $hyb->estoqueInicialQtd,
            ':hyb_estoque_ini_custo' => $hyb->estoqueInicialCusto,
            ':hyb_descricao'     => $hyb->descricao,
        ];

        $sql = "INSERT INTO livros (
            isbn_13, isbn_10, titulo, subtitulo, autores, editora,
            ano_publicacao, data_publicacao, idioma, paginas, sinopse,
            assuntos, categorias, formato,
            altura_cm, largura_cm, espessura_cm, peso,
            preco_moeda, preco_valor, local_publicacao,
            capa_url, capa_thumbnail, link_preview,
            avaliacao_media, qtd_avaliacoes,
            payload_bruto, fonte_api, provider_origem,
            consultado_em, atualizado_em,
            hyb_bem_produto, hyb_unidade, hyb_categoria, categoria_id, hyb_ncm,
            hyb_preco_venda, hyb_estoque_minimo, hyb_referencia,
            hyb_patrimonio, hyb_depreciacao_pct, hyb_tipo,
            hyb_estoque_ini_qtd, hyb_estoque_ini_custo, hyb_descricao
        ) VALUES (
            :isbn_13, :isbn_10, :titulo, :subtitulo, :autores, :editora,
            :ano_publicacao, :data_publicacao, :idioma, :paginas, :sinopse,
            :assuntos, :categorias, :formato,
            :altura_cm, :largura_cm, :espessura_cm, :peso,
            :preco_moeda, :preco_valor, :local_publicacao,
            :capa_url, :capa_thumbnail, :link_preview,
            :avaliacao_media, :qtd_avaliacoes,
            :payload_bruto, :fonte_api, :provider_origem,
            :consultado_em, :atualizado_em,
            :hyb_bem_produto, :hyb_unidade, :hyb_categoria, :categoria_id, :hyb_ncm,
            :hyb_preco_venda, :hyb_estoque_minimo, :hyb_referencia,
            :hyb_patrimonio, :hyb_depreciacao_pct, :hyb_tipo,
            :hyb_estoque_ini_qtd, :hyb_estoque_ini_custo, :hyb_descricao
        )
        ON DUPLICATE KEY UPDATE
            isbn_10            = VALUES(isbn_10),
            titulo             = VALUES(titulo),
            subtitulo          = VALUES(subtitulo),
            autores            = VALUES(autores),
            editora            = VALUES(editora),
            ano_publicacao     = VALUES(ano_publicacao),
            data_publicacao    = VALUES(data_publicacao),
            idioma             = VALUES(idioma),
            paginas            = VALUES(paginas),
            sinopse            = VALUES(sinopse),
            assuntos           = VALUES(assuntos),
            categorias         = VALUES(categorias),
            formato            = VALUES(formato),
            altura_cm          = VALUES(altura_cm),
            largura_cm         = VALUES(largura_cm),
            espessura_cm       = VALUES(espessura_cm),
            peso               = VALUES(peso),
            preco_moeda        = VALUES(preco_moeda),
            preco_valor        = VALUES(preco_valor),
            local_publicacao   = VALUES(local_publicacao),
            capa_url           = VALUES(capa_url),
            capa_thumbnail     = VALUES(capa_thumbnail),
            link_preview       = VALUES(link_preview),
            avaliacao_media    = VALUES(avaliacao_media),
            qtd_avaliacoes     = VALUES(qtd_avaliacoes),
            payload_bruto      = VALUES(payload_bruto),
            fonte_api          = VALUES(fonte_api),
            provider_origem    = VALUES(provider_origem),
            consultado_em      = VALUES(consultado_em),
            atualizado_em      = VALUES(atualizado_em),
            hyb_bem_produto    = VALUES(hyb_bem_produto),
            hyb_unidade        = VALUES(hyb_unidade),
            hyb_categoria      = VALUES(hyb_categoria),
            categoria_id       = VALUES(categoria_id),
            hyb_ncm            = VALUES(hyb_ncm),
            hyb_preco_venda    = VALUES(hyb_preco_venda),
            hyb_estoque_minimo = VALUES(hyb_estoque_minimo),
            hyb_referencia     = VALUES(hyb_referencia),
            hyb_patrimonio     = VALUES(hyb_patrimonio),
            hyb_depreciacao_pct  = VALUES(hyb_depreciacao_pct),
            hyb_tipo           = VALUES(hyb_tipo),
            hyb_estoque_ini_qtd  = VALUES(hyb_estoque_ini_qtd),
            hyb_estoque_ini_custo= VALUES(hyb_estoque_ini_custo),
            hyb_descricao      = VALUES(hyb_descricao)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $id = (int) $this->pdo->lastInsertId();
        if ($id === 0) {
            $row = $this->buscarLinhaPorIsbn13($api->isbn13);
            $id = $row ? (int) $row['id'] : 0;
        }

        $livro->id = $id;
        $livro->atualizadoEm = $params[':atualizado_em'];
        return $livro;
    }

    public function buscarPorIsbn(ISBN $isbn): ?Livro
    {
        $row = $this->buscarLinhaPorIsbn13($isbn->isbn13());
        return $row ? $this->mapearLinhaParaLivro($row) : null;
    }

    public function buscarPorId(int $id): ?Livro
    {
        $stmt = $this->pdo->prepare("SELECT * FROM livros WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->mapearLinhaParaLivro($row) : null;
    }

    public function listar(int $limite, int $offset, array $filtros = []): array
    {
        [$where, $params] = $this->montarWhere($filtros, 'l');

        // LEFT JOIN com subquery agregada para evitar N+1 e manter LIMIT correto
        // (decisão #1: cada linha em exportacoes_hyb_itens = uma baixa efetiva).
        $sql = "SELECT l.*, COALESCE(b.qtd_baixas, 0) AS qtd_baixas
                FROM livros l
                LEFT JOIN (
                    SELECT livro_id, COUNT(*) AS qtd_baixas
                    FROM exportacoes_hyb_itens
                    GROUP BY livro_id
                ) b ON b.livro_id = l.id
                {$where}
                ORDER BY l.atualizado_em DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $resultados = [];
        foreach ($stmt->fetchAll() as $row) {
            $resultados[] = $this->mapearLinhaParaLivro($row);
        }
        return $resultados;
    }

    public function contar(array $filtros = []): int
    {
        [$where, $params] = $this->montarWhere($filtros);
        $sql = "SELECT COUNT(*) AS qtd FROM livros {$where}";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function buscarPorIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (count($ids) === 0) {
            return [];
        }
        $placeholders = implode(',', array_map(fn ($i) => ':id' . $i, array_keys($ids)));
        $sql = "SELECT * FROM livros WHERE id IN ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        foreach ($ids as $i => $id) {
            $stmt->bindValue(':id' . $i, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $resultados = [];
        foreach ($stmt->fetchAll() as $row) {
            $resultados[] = $this->mapearLinhaParaLivro($row);
        }
        return $resultados;
    }

    public function listarNaoExportados(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM livros WHERE exportado_em IS NULL ORDER BY id ASC");
        $resultados = [];
        foreach ($stmt->fetchAll() as $row) {
            $resultados[] = $this->mapearLinhaParaLivro($row);
        }
        return $resultados;
    }

    public function marcarComoExportados(array $ids, string $quando): void
    {
        if (count($ids) === 0) {
            return;
        }
        $placeholders = implode(',', array_map(fn ($i) => ':id' . $i, array_keys($ids)));
        $sql = "UPDATE livros SET exportado_em = :quando WHERE id IN ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':quando', $quando);
        foreach ($ids as $i => $id) {
            $stmt->bindValue(':id' . $i, (int) $id, PDO::PARAM_INT);
        }
        $stmt->execute();
    }

    // ---------------------------------------------------------------
    // Helpers privados
    // ---------------------------------------------------------------

    private function montarWhere(array $filtros, ?string $alias = null): array
    {
        $where = [];
        $params = [];
        $prefix = $alias !== null ? $alias . '.' : '';

        if (!empty($filtros['busca'])) {
            $where[] = "({$prefix}titulo LIKE :busca OR {$prefix}isbn_13 LIKE :busca OR {$prefix}isbn_10 LIKE :busca OR {$prefix}autores LIKE :busca)";
            $params[':busca'] = '%' . $filtros['busca'] . '%';
        }
        if (!empty($filtros['editora'])) {
            $where[] = "{$prefix}editora = :editora";
            $params[':editora'] = $filtros['editora'];
        }
        if (!empty($filtros['ano'])) {
            $where[] = "{$prefix}ano_publicacao = :ano";
            $params[':ano'] = (int) $filtros['ano'];
        }
        if (!empty($filtros['idioma'])) {
            $where[] = "{$prefix}idioma = :idioma";
            $params[':idioma'] = $filtros['idioma'];
        }

        $sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        return [$sql, $params];
    }

    private function buscarLinhaPorIsbn13(string $isbn13): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM livros WHERE isbn_13 = :isbn LIMIT 1");
        $stmt->execute([':isbn' => $isbn13]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function mapearLinhaParaLivro(array $row): Livro
    {
        $dim = new Dimensoes(
            alturaCm:    $row['altura_cm']    !== null ? (float) $row['altura_cm']    : null,
            larguraCm:   $row['largura_cm']   !== null ? (float) $row['largura_cm']   : null,
            espessuraCm: $row['espessura_cm'] !== null ? (float) $row['espessura_cm'] : null,
        );
        $preco = new Preco(
            moeda: $row['preco_moeda'],
            valor: $row['preco_valor'] !== null ? (float) $row['preco_valor'] : null,
        );

        $dados = new DadosBibliograficos(
            isbn13: (string) $row['isbn_13'],
            isbn10: $row['isbn_10'],
            titulo: (string) $row['titulo'],
            subtitulo: $row['subtitulo'],
            autores: $this->jsonDecode($row['autores']),
            editora: $row['editora'],
            anoPublicacao: $row['ano_publicacao'] !== null ? (int) $row['ano_publicacao'] : null,
            dataPublicacao: $row['data_publicacao'],
            idioma: $row['idioma'],
            paginas: $row['paginas'] !== null ? (int) $row['paginas'] : null,
            sinopse: $row['sinopse'],
            assuntos: $this->jsonDecode($row['assuntos']),
            categorias: $this->jsonDecode($row['categorias']),
            formato: $row['formato'],
            dimensoes: $dim,
            peso: $row['peso'],
            preco: $preco,
            localPublicacao: $row['local_publicacao'],
            capaUrl: $row['capa_url'],
            capaThumbnail: $row['capa_thumbnail'],
            linkPreview: $row['link_preview'],
            avaliacaoMedia: $row['avaliacao_media'] !== null ? (float) $row['avaliacao_media'] : null,
            qtdAvaliacoes: $row['qtd_avaliacoes'] !== null ? (int) $row['qtd_avaliacoes'] : null,
            fonteApi: (string) $row['fonte_api'],
            providerOrigem: $row['provider_origem'],
            consultadoEm: $row['consultado_em'],
            payloadBruto: $this->jsonDecode($row['payload_bruto']) ?: null,
        );

        $hyb = new CamposHyb(
            bemProduto:           $row['hyb_bem_produto'],
            unidade:              $row['hyb_unidade'],
            categoria:            $row['hyb_categoria'],
            ncm:                  $row['hyb_ncm'],
            precoVenda:           $row['hyb_preco_venda'] !== null ? (float) $row['hyb_preco_venda'] : null,
            estoqueMinimo:        $row['hyb_estoque_minimo'] !== null ? (int) $row['hyb_estoque_minimo'] : null,
            referencia:           $row['hyb_referencia'],
            patrimonio:           $row['hyb_patrimonio'],
            depreciacaoPct:       $row['hyb_depreciacao_pct'] !== null ? (float) $row['hyb_depreciacao_pct'] : null,
            tipo:                 $row['hyb_tipo'],
            estoqueInicialQtd:    $row['hyb_estoque_ini_qtd'] !== null ? (int) $row['hyb_estoque_ini_qtd'] : null,
            estoqueInicialCusto:  $row['hyb_estoque_ini_custo'] !== null ? (float) $row['hyb_estoque_ini_custo'] : null,
            descricao:            $row['hyb_descricao'],
            categoriaId:          isset($row['categoria_id']) && $row['categoria_id'] !== null ? (int) $row['categoria_id'] : null,
        );

        return new Livro(
            id: (int) $row['id'],
            dadosApi: $dados,
            hyb: $hyb,
            consultadoEm: $row['consultado_em'],
            atualizadoEm: $row['atualizado_em'],
            exportadoEm: $row['exportado_em'],
            // qtd_baixas só vem na query da listagem (LEFT JOIN com itens).
            // Outros pontos (buscarPorId, etc.) deixam null → toArray() coalesce p/ 0.
            qtdBaixas: isset($row['qtd_baixas']) ? (int) $row['qtd_baixas'] : null,
        );
    }

    private function jsonOuNull($valor): ?string
    {
        if ($valor === null || $valor === '' || (is_array($valor) && count($valor) === 0)) {
            return null;
        }
        return json_encode($valor, JSON_UNESCAPED_UNICODE);
    }

    private function jsonDecode(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function normalizarDateTime(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        // Já em formato MySQL (YYYY-MM-DD HH:MM:SS)
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $valor)) {
            return $valor;
        }
        $ts = strtotime($valor);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $ts);
    }

    private function dataValida(?string $data): ?string
    {
        if ($data === null || $data === '') {
            return null;
        }
        // Aceita YYYY-MM-DD ou YYYY (Google Books às vezes manda só ano)
        if (preg_match('/^\d{4}$/', $data)) {
            return $data . '-01-01';
        }
        if (preg_match('/^\d{4}-\d{2}$/', $data)) {
            return $data . '-01';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $data)) {
            return substr($data, 0, 10);
        }
        return null;
    }
}
