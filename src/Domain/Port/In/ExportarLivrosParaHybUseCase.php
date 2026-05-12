<?php
declare(strict_types=1);

namespace App\Domain\Port\In;

interface ExportarLivrosParaHybUseCase
{
    /**
     * Gera o arquivo XLSX no formato HYB Integrador.
     *
     * @param array $filtros aceita as chaves:
     *   - ids: array<int>           — exporta apenas IDs específicos
     *   - apenas_nao_exportados: bool — exporta só os com exportado_em NULL
     * @return string caminho do arquivo gerado em storage/exports/
     */
    public function executar(array $filtros = []): string;
}
