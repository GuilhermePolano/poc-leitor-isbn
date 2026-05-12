<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapter\Out\Export;

use App\Domain\Port\Out\ExportadorDeLivros;
use App\Domain\Service\MapeadorParaFormatoHyb;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class XlsxHybExporter implements ExportadorDeLivros
{
    public function __construct(private readonly MapeadorParaFormatoHyb $mapeador) {}

    public function exportar(array $livros, string $caminhoDestino): string
    {
        $wb = new Spreadsheet();
        $wb->getProperties()
            ->setCreator('POC Código de Barras')
            ->setTitle('HYB Integrador — Bens')
            ->setDescription('Exportação automática gerada pela POC de bipagem ISBN.');

        $this->montarAbaDados($wb, $livros);
        $this->montarAbaLegenda($wb);
        $wb->setActiveSheetIndex(0);

        $diretorio = dirname($caminhoDestino);
        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0775, true);
        }

        (new Xlsx($wb))->save($caminhoDestino);
        return $caminhoDestino;
    }

    private function montarAbaDados(Spreadsheet $wb, array $livros): void
    {
        $sheet = $wb->getActiveSheet();
        $sheet->setTitle('Dados');

        // Cabeçalhos EXATOS do template HYB (espaços propositais em M e N)
        $headers = [
            'A1' => 'Bem/Produto',
            'B1' => 'Titulo',
            'C1' => 'Unidade',
            'D1' => 'Categoria',
            'E1' => 'Código de Barras (EAN)',
            'F1' => 'NCM',
            'G1' => 'Preço de Venda',
            'H1' => 'Estoque Mínimo',
            'I1' => 'Referência',
            'J1' => 'Patrimônio(S,N)',
            'K1' => 'Depreciação(%)',
            'L1' => 'Tipo',
            'M1' => '  Estoque Inicial  -Quantidade',
            'N1' => '  Estoque Inicial  - Custo Unitário ',
            'O1' => 'Descrição',
        ];
        foreach ($headers as $cel => $valor) {
            $sheet->setCellValue($cel, $valor);
        }

        // Comentários (notas) idênticos ao template
        $this->adicionarComentario(
            $sheet,
            'A1',
            "Bem/Produto\nInformar somente se desejar realizar a edição de um Bem/Produto já registrado no HYB.\n1) número - Identificará pelo Código do Bem/Produto no HYB"
        );
        $this->adicionarComentario(
            $sheet,
            'D1',
            "Categoria dos bens:\nO sistema buscará pelo nome registrado da categoria ou pelo ID."
        );
        $this->adicionarComentario(
            $sheet,
            'L1',
            "tipo do bem:\nopções possíveis:\nDesconhecido\nMóvel\nImóvel"
        );

        // Larguras de coluna idênticas ao template
        $larguras = [
            'A' => 15.85, 'B' => 24.71, 'C' => 8.57,  'D' => 24.14, 'E' => 21.71,
            'F' => 13.00, 'G' => 16.14, 'H' => 17.57, 'I' => 13.14, 'J' => 20.71,
            'K' => 20.14, 'L' => 18.42, 'M' => 34.57, 'N' => 36.71, 'O' => 34.85,
        ];
        foreach ($larguras as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // Linha 1 em negrito
        $sheet->getStyle('A1:O1')->getFont()->setBold(true);

        // Linhas de dados
        $linha = 2;
        foreach ($livros as $livro) {
            $row = $this->mapeador->mapear($livro);

            $sheet->setCellValue("A{$linha}", $row['bem_produto']);
            $sheet->setCellValue("B{$linha}", $row['titulo']);
            $sheet->setCellValue("C{$linha}", $row['unidade']);
            $sheet->setCellValue("D{$linha}", $row['categoria']);

            // E e F como TEXT para preservar zeros à esquerda e evitar notação científica
            $sheet->setCellValueExplicit("E{$linha}", (string) $row['ean'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$linha}", (string) $row['ncm'], DataType::TYPE_STRING);

            $sheet->setCellValue("G{$linha}", $row['preco_venda']);
            $sheet->setCellValue("H{$linha}", $row['estoque_minimo']);
            $sheet->setCellValue("I{$linha}", $row['referencia']);
            $sheet->setCellValue("J{$linha}", $row['patrimonio']);
            $sheet->setCellValue("K{$linha}", $row['depreciacao']);
            $sheet->setCellValue("L{$linha}", $row['tipo']);
            $sheet->setCellValue("M{$linha}", $row['estoque_inicial_qtd']);
            $sheet->setCellValue("N{$linha}", $row['estoque_inicial_custo']);
            $sheet->setCellValue("O{$linha}", $row['descricao']);

            $linha++;
        }
    }

    private function montarAbaLegenda(Spreadsheet $wb): void
    {
        $sheet = $wb->createSheet();
        $sheet->setTitle('Legenda de Cores');

        $sheet->setCellValue('A1', 'Legendas das cores');
        $sheet->setCellValue('A2', 'Obrigatório');
        $sheet->setCellValue('A3', 'Obrigatório em alguns casos(dependendo de outros dados)');
        $sheet->setCellValue('A4', 'Obrigatório somente na edição');
        $sheet->setCellValue('A5', 'Não obrigatório');
        $sheet->setCellValue('A7', '* Clique  no canto superior direito da célula para detalhes sobre o campo');

        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(70);
    }

    private function adicionarComentario(Worksheet $sheet, string $cel, string $texto): void
    {
        $sheet->getComment($cel)->getText()->createTextRun($texto);
        $sheet->getComment($cel)->setWidth('220pt');
        $sheet->getComment($cel)->setHeight('110pt');
    }
}
