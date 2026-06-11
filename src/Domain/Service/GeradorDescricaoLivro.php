<?php
declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\DadosBibliograficos;
use App\Domain\Entity\Livro;

/**
 * Gera a descrição enriquecida (coluna O do HYB) a partir de um Livro.
 *
 * Decisão M16 (aprovada): concatena partes não-vazias separadas por '. ',
 * mantendo a ordem fixa abaixo. Cada parte é "rotulada" (Autores:, Editora:,
 * etc.) para o operador final do HYB conseguir ler rápido. A sinopse vai
 * por último por ser tipicamente o trecho mais longo.
 *
 * Ordem das seções:
 *   1) Autores       — "Autores: A, B, C"
 *   2) Tradução      — "Tradução: X, Y"        (contributors role=translator)
 *   3) Ilustrações   — "Ilustrações: Z"        (contributors role=illustrator)
 *   4) Edição        — "Edição: <edition_name>"
 *   5) Série         — "Série: <series>"
 *   6) Editora       — "Editora: <editora> (<ano>)"
 *   7) Páginas       — "<n> páginas"
 *   8) Idioma        — "Idioma: <idioma>"      (só se diferente de pt-BR)
 *   9) Classificação — "Classificação: MATURE" (só se maturityRating='MATURE')
 *  10) Sinopse       — "Sinopse: <descricao_original>" (último, longa)
 *
 * Truncamento defensivo em 30.000 caracteres (decisão M16).
 *
 * Decisão #9 mantida: este serviço NÃO aplica defaults do .env. Trabalha
 * apenas com dados bibliográficos vindos da API/normalizador.
 */
final class GeradorDescricaoLivro
{
    private const LIMITE_CHARS = 30000;
    private const IDIOMA_PADRAO = 'pt-BR';

    public function gerar(Livro $livro): string
    {
        return $this->gerarDeDados($livro->dadosApi);
    }

    /**
     * Variante que aceita o array já normalizado (formato DadosBibliograficos::toArray()).
     * Usado por api/consultar.php, onde o resultado do caso de uso já vem serializado.
     */
    public function gerarDeArray(array $livro): string
    {
        $dados = new DadosBibliograficos(
            isbn13: (string) ($livro['isbn_13'] ?? ''),
            autores: (array) ($livro['autores'] ?? []),
            editora: $this->str($livro['editora'] ?? null),
            anoPublicacao: isset($livro['ano_publicacao']) && $livro['ano_publicacao'] !== ''
                ? (int) $livro['ano_publicacao']
                : null,
            idioma: $this->str($livro['idioma'] ?? null),
            paginas: isset($livro['paginas']) && $livro['paginas'] !== ''
                ? (int) $livro['paginas']
                : null,
            sinopse: $this->str($livro['sinopse'] ?? null),
            contributors: (array) ($livro['contributors'] ?? []),
            maturityRating: $this->str($livro['maturity_rating'] ?? null),
            editionName: $this->str($livro['edition_name'] ?? null),
            series: $this->str($livro['series'] ?? null),
        );
        return $this->gerarDeDados($dados);
    }

    private function str(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }

    /**
     * Variante que aceita diretamente o VO de dados bibliográficos.
     * Útil para api/consultar.php quando o livro ainda não foi salvo
     * (não existe entidade Livro completa).
     */
    public function gerarDeDados(DadosBibliograficos $api): string
    {
        $partes = [];

        // 1) Autores
        $autores = $this->limparLista($api->autores);
        if ($autores !== []) {
            $partes[] = 'Autores: ' . implode(', ', $autores);
        }

        // 2) Tradução / 3) Ilustrações — derivados de contributors
        $traducoes    = $this->nomesPorRole($api->contributors, 'translator');
        $ilustracoes  = $this->nomesPorRole($api->contributors, 'illustrator');
        if ($traducoes !== []) {
            $partes[] = 'Tradução: ' . implode(', ', $traducoes);
        }
        if ($ilustracoes !== []) {
            $partes[] = 'Ilustrações: ' . implode(', ', $ilustracoes);
        }

        // 4) Edição
        if ($this->preenchido($api->editionName)) {
            $partes[] = 'Edição: ' . trim((string) $api->editionName);
        }

        // 5) Série
        if ($this->preenchido($api->series)) {
            $partes[] = 'Série: ' . trim((string) $api->series);
        }

        // 6) Editora (+ ano entre parênteses, se houver)
        if ($this->preenchido($api->editora)) {
            $editora = trim((string) $api->editora);
            if ($api->anoPublicacao !== null && $api->anoPublicacao > 0) {
                $editora .= ' (' . $api->anoPublicacao . ')';
            }
            $partes[] = 'Editora: ' . $editora;
        }

        // 7) Páginas
        if ($api->paginas !== null && $api->paginas > 0) {
            $partes[] = $api->paginas . ' páginas';
        }

        // 8) Idioma (só se diferente de pt-BR e setado)
        if ($this->preenchido($api->idioma)) {
            $idioma = trim((string) $api->idioma);
            if (strcasecmp($idioma, self::IDIOMA_PADRAO) !== 0) {
                $partes[] = 'Idioma: ' . $idioma;
            }
        }

        // 9) Classificação — só quando MATURE
        if ($api->maturityRating !== null
            && strcasecmp(trim((string) $api->maturityRating), 'MATURE') === 0
        ) {
            $partes[] = 'Classificação: MATURE';
        }

        // 10) Sinopse (último, longa)
        if ($this->preenchido($api->sinopse)) {
            $partes[] = 'Sinopse: ' . trim((string) $api->sinopse);
        }

        $descricao = implode('. ', $partes);

        // Truncamento defensivo
        if (function_exists('mb_strlen') && mb_strlen($descricao) > self::LIMITE_CHARS) {
            return mb_substr($descricao, 0, self::LIMITE_CHARS);
        }
        if (strlen($descricao) > self::LIMITE_CHARS) {
            return substr($descricao, 0, self::LIMITE_CHARS);
        }

        return $descricao;
    }

    /**
     * Filtra contributors pelo role e devolve só os nomes não-vazios,
     * preservando a ordem e removendo duplicatas (case-insensitive).
     */
    private function nomesPorRole(array $contributors, string $role): array
    {
        $alvo = strtolower($role);
        $nomes = [];
        $vistos = [];
        foreach ($contributors as $c) {
            if (!is_array($c)) {
                continue;
            }
            $r = isset($c['role']) ? strtolower(trim((string) $c['role'])) : '';
            $n = isset($c['name']) ? trim((string) $c['name']) : '';
            if ($r !== $alvo || $n === '') {
                continue;
            }
            $chave = mb_strtolower($n);
            if (isset($vistos[$chave])) {
                continue;
            }
            $vistos[$chave] = true;
            $nomes[] = $n;
        }
        return $nomes;
    }

    /**
     * Limpa uma lista de strings: trim, descarta vazios, remove duplicatas
     * mantendo a primeira ocorrência.
     */
    private function limparLista(array $itens): array
    {
        $saida = [];
        $vistos = [];
        foreach ($itens as $i) {
            $v = trim((string) $i);
            if ($v === '') {
                continue;
            }
            $chave = mb_strtolower($v);
            if (isset($vistos[$chave])) {
                continue;
            }
            $vistos[$chave] = true;
            $saida[] = $v;
        }
        return $saida;
    }

    private function preenchido(?string $v): bool
    {
        return $v !== null && trim($v) !== '';
    }
}
