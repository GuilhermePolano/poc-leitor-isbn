<?php
declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\DadosBibliograficos;
use App\Domain\ValueObject\Dimensoes;
use App\Domain\ValueObject\ISBN;
use App\Domain\ValueObject\Preco;

/**
 * Utilitários de normalização compartilhados pelos adaptadores
 * (limpeza de strings, parsing de dimensões textuais, mescla de respostas).
 */
final class NormalizadorDeLivro
{
    /**
     * Mescla múltiplos DadosBibliograficos preferindo o primeiro não-nulo.
     * Útil quando duas APIs retornaram dados parciais.
     */
    public function mesclar(DadosBibliograficos ...$registros): DadosBibliograficos
    {
        if (count($registros) === 0) {
            throw new \InvalidArgumentException('Necessário pelo menos um registro.');
        }
        $primeiro = $registros[0];
        if (count($registros) === 1) {
            return $primeiro;
        }

        $pick = static function ($valor, $fallback) {
            if ($valor === null || $valor === '' || $valor === []) {
                return $fallback;
            }
            return $valor;
        };

        $dim = $primeiro->dimensoes;
        $preco = $primeiro->preco;
        $isbn10 = $primeiro->isbn10;

        foreach (array_slice($registros, 1) as $r) {
            if (!$dim->temAlgumValor() && $r->dimensoes->temAlgumValor()) {
                $dim = $r->dimensoes;
            }
            if (!$preco->temValor() && $r->preco->temValor()) {
                $preco = $r->preco;
            }
            $isbn10 = $pick($isbn10, $r->isbn10);
        }

        $valoresCampos = [
            'titulo'           => $primeiro->titulo,
            'subtitulo'        => $primeiro->subtitulo,
            'autores'          => $primeiro->autores,
            'editora'          => $primeiro->editora,
            'anoPublicacao'    => $primeiro->anoPublicacao,
            'dataPublicacao'   => $primeiro->dataPublicacao,
            'idioma'           => $primeiro->idioma,
            'paginas'          => $primeiro->paginas,
            'sinopse'          => $primeiro->sinopse,
            'assuntos'         => $primeiro->assuntos,
            'categorias'       => $primeiro->categorias,
            'formato'          => $primeiro->formato,
            'peso'             => $primeiro->peso,
            'localPublicacao'  => $primeiro->localPublicacao,
            'capaUrl'          => $primeiro->capaUrl,
            'capaThumbnail'    => $primeiro->capaThumbnail,
            'linkPreview'      => $primeiro->linkPreview,
            'avaliacaoMedia'   => $primeiro->avaliacaoMedia,
            'qtdAvaliacoes'    => $primeiro->qtdAvaliacoes,
        ];

        foreach (array_slice($registros, 1) as $r) {
            foreach ($valoresCampos as $campo => $valor) {
                $valoresCampos[$campo] = $pick($valor, $r->$campo);
            }
        }

        return new DadosBibliograficos(
            isbn13: $primeiro->isbn13,
            isbn10: $isbn10,
            titulo: (string) ($valoresCampos['titulo'] ?? ''),
            subtitulo: $valoresCampos['subtitulo'],
            autores: $valoresCampos['autores'] ?? [],
            editora: $valoresCampos['editora'],
            anoPublicacao: $valoresCampos['anoPublicacao'],
            dataPublicacao: $valoresCampos['dataPublicacao'],
            idioma: $valoresCampos['idioma'],
            paginas: $valoresCampos['paginas'],
            sinopse: $valoresCampos['sinopse'],
            assuntos: $valoresCampos['assuntos'] ?? [],
            categorias: $valoresCampos['categorias'] ?? [],
            formato: $valoresCampos['formato'],
            dimensoes: $dim,
            peso: $valoresCampos['peso'],
            preco: $preco,
            localPublicacao: $valoresCampos['localPublicacao'],
            capaUrl: $valoresCampos['capaUrl'],
            capaThumbnail: $valoresCampos['capaThumbnail'],
            linkPreview: $valoresCampos['linkPreview'],
            avaliacaoMedia: $valoresCampos['avaliacaoMedia'],
            qtdAvaliacoes: $valoresCampos['qtdAvaliacoes'],
            fonteApi: $primeiro->fonteApi,
            providerOrigem: $primeiro->providerOrigem,
            consultadoEm: $primeiro->consultadoEm,
            payloadBruto: $primeiro->payloadBruto,
        );
    }

    /**
     * Tenta extrair dimensões a partir de uma string textual ex.: "20.3 x 13.5 x 2 centimeters".
     */
    public function dimensoesFromTexto(?string $texto): Dimensoes
    {
        if ($texto === null || trim($texto) === '') {
            return new Dimensoes();
        }
        $t = strtolower($texto);
        preg_match_all('/([\d.,]+)/', $t, $m);
        if (empty($m[1])) {
            return new Dimensoes();
        }
        $valores = array_map(fn ($v) => (float) str_replace(',', '.', $v), $m[1]);
        $unidadeCm = (str_contains($t, 'cm') || str_contains($t, 'centi'));
        $unidadeMm = str_contains($t, 'mm');
        $fator = $unidadeMm ? 0.1 : ($unidadeCm ? 1.0 : 1.0); // assume cm por padrão
        $altura    = isset($valores[0]) ? $valores[0] * $fator : null;
        $largura   = isset($valores[1]) ? $valores[1] * $fator : null;
        $espessura = isset($valores[2]) ? $valores[2] * $fator : null;
        return new Dimensoes($altura, $largura, $espessura);
    }

    public function gerarDescricaoAutomatica(DadosBibliograficos $d): string
    {
        $partes = [];
        if (!empty($d->autores)) {
            $partes[] = 'Autor(es): ' . implode(', ', $d->autores) . '.';
        }
        if ($d->editora !== null && $d->editora !== '') {
            $partes[] = 'Editora: ' . $d->editora . '.';
        }
        if ($d->anoPublicacao !== null) {
            $partes[] = 'Ano: ' . $d->anoPublicacao . '.';
        }
        if ($d->paginas !== null) {
            $partes[] = 'Páginas: ' . $d->paginas . '.';
        }
        if ($d->sinopse !== null && trim($d->sinopse) !== '') {
            $partes[] = 'Sinopse: ' . trim($d->sinopse);
        }
        return implode(' ', $partes);
    }
}
