<?php
declare(strict_types=1);

namespace App\Application;

use App\Domain\Entity\CamposHyb;
use App\Domain\Entity\DadosBibliograficos;
use App\Domain\Entity\Livro;
use App\Domain\Port\In\SalvarLivroUseCase;
use App\Domain\Port\Out\LivroRepository;
use App\Domain\Port\Out\Logger;
use App\Domain\ValueObject\Dimensoes;
use App\Domain\ValueObject\ISBN;
use App\Domain\ValueObject\Preco;

final class SalvarLivroService implements SalvarLivroUseCase
{
    public function __construct(
        private readonly LivroRepository $repo,
        private readonly Logger $logger,
    ) {}

    public function executar(array $livroApi, array $hyb): array
    {
        $isbn13 = (string) ($livroApi['isbn_13'] ?? '');
        if ($isbn13 === '') {
            throw new \InvalidArgumentException('isbn_13 ausente no payload.');
        }
        $vo = ISBN::criar($isbn13);

        $dim = new Dimensoes(
            alturaCm:    isset($livroApi['dimensoes']['altura_cm'])    ? (float) $livroApi['dimensoes']['altura_cm']    : null,
            larguraCm:   isset($livroApi['dimensoes']['largura_cm'])   ? (float) $livroApi['dimensoes']['largura_cm']   : null,
            espessuraCm: isset($livroApi['dimensoes']['espessura_cm']) ? (float) $livroApi['dimensoes']['espessura_cm'] : null,
        );
        $preco = new Preco(
            moeda: $livroApi['preco']['moeda'] ?? null,
            valor: isset($livroApi['preco']['valor']) ? (float) $livroApi['preco']['valor'] : null,
        );

        $dados = new DadosBibliograficos(
            isbn13: $vo->isbn13(),
            isbn10: $livroApi['isbn_10'] ?? $vo->isbn10(),
            titulo: (string) ($livroApi['titulo'] ?? ''),
            subtitulo: $livroApi['subtitulo'] ?? null,
            autores: (array) ($livroApi['autores'] ?? []),
            editora: $livroApi['editora'] ?? null,
            anoPublicacao: isset($livroApi['ano_publicacao']) ? (int) $livroApi['ano_publicacao'] : null,
            dataPublicacao: $livroApi['data_publicacao'] ?? null,
            idioma: $livroApi['idioma'] ?? null,
            paginas: isset($livroApi['paginas']) ? (int) $livroApi['paginas'] : null,
            sinopse: $livroApi['sinopse'] ?? null,
            assuntos: (array) ($livroApi['assuntos'] ?? []),
            categorias: (array) ($livroApi['categorias'] ?? []),
            formato: $livroApi['formato'] ?? null,
            dimensoes: $dim,
            peso: $livroApi['peso'] ?? null,
            preco: $preco,
            localPublicacao: $livroApi['local_publicacao'] ?? null,
            capaUrl: $livroApi['capa_url'] ?? null,
            capaThumbnail: $livroApi['capa_thumbnail'] ?? null,
            linkPreview: $livroApi['link_preview'] ?? null,
            avaliacaoMedia: isset($livroApi['avaliacao_media']) ? (float) $livroApi['avaliacao_media'] : null,
            qtdAvaliacoes: isset($livroApi['qtd_avaliacoes']) ? (int) $livroApi['qtd_avaliacoes'] : null,
            fonteApi: (string) ($livroApi['fonte_api'] ?? 'desconhecida'),
            providerOrigem: $livroApi['provider_origem'] ?? null,
            consultadoEm: $livroApi['consultado_em'] ?? date('c'),
            payloadBruto: $livroApi['payload_bruto'] ?? null,
        );

        $existente = $this->repo->buscarPorIsbn($vo);
        $criado = $existente === null;

        $livro = new Livro(
            id: $existente?->id,
            dadosApi: $dados,
            hyb: CamposHyb::fromArray($hyb),
            consultadoEm: $existente?->consultadoEm ?? $dados->consultadoEm,
            atualizadoEm: date('Y-m-d H:i:s'),
            exportadoEm: $existente?->exportadoEm,
        );

        $salvo = $this->repo->salvar($livro);

        $this->logger->info($criado ? 'Livro criado' : 'Livro atualizado', [
            'isbn_13' => $vo->isbn13(),
            'id'      => $salvo->id,
        ]);

        return [
            'sucesso' => true,
            'id'      => (int) $salvo->id,
            'isbn_13' => $vo->isbn13(),
            'criado'  => $criado,
        ];
    }
}
