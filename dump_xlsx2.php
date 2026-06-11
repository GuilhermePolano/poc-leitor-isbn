<?php
require '/var/www/html/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$file = '/var/www/html/Docs_para_Consulta/HYBIntegrador_bens.xlsx';
$spreadsheet = IOFactory::load($file);

$out = [];

// Get header fill colors from Dados sheet row 1
$sheet = $spreadsheet->getSheetByName('Dados');
$headerColors = [];
for ($c = 1; $c <= 15; $c++) {
    $letter = Coordinate::stringFromColumnIndex($c);
    $cell = $sheet->getCell($letter . '1');
    $fill = $cell->getStyle()->getFill();
    $headerColors[$letter] = [
        'fillType' => $fill->getFillType(),
        'startColor' => $fill->getStartColor()->getARGB(),
        'endColor' => $fill->getEndColor()->getARGB(),
    ];
}

// Get fill colors from Legenda de Cores sheet rows 2-5 (the 4 legend rows)
$legenda = $spreadsheet->getSheetByName('Legenda de Cores');
$legendColors = [];
for ($r = 2; $r <= 7; $r++) {
    $cell = $legenda->getCell('A' . $r);
    $fill = $cell->getStyle()->getFill();
    $legendColors[$r] = [
        'text' => $cell->getValue(),
        'fillType' => $fill->getFillType(),
        'startColor' => $fill->getStartColor()->getARGB(),
        'endColor' => $fill->getEndColor()->getARGB(),
    ];
}

$out['headerColors'] = $headerColors;
$out['legendColors'] = $legendColors;
$out['sheetNames'] = $spreadsheet->getSheetNames();
$out['activeSheet'] = $spreadsheet->getActiveSheetIndex();

file_put_contents('/var/www/html/dump_xlsx2.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "OK\n";
