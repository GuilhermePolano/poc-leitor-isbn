<?php
require '/var/www/html/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$file = '/var/www/html/Docs_para_Consulta/Categorias (3).xlsx';
$reader = IOFactory::createReaderForFile($file);
$spreadsheet = $reader->load($file);

$sheet = $spreadsheet->getSheet(0);
$highestRow = $sheet->getHighestRow();
$rows = [];
for ($r = 1; $r <= $highestRow; $r++) {
    $a = $sheet->getCell('A' . $r);
    $b = $sheet->getCell('B' . $r);
    $c = $sheet->getCell('C' . $r);
    $rows[] = [
        'row' => $r,
        'A_raw' => $a->getValue(),
        'A_fmt' => $a->getFormattedValue(),
        'A_type' => $a->getDataType(),
        'B_raw' => $b->getValue(),
        'B_type' => $b->getDataType(),
        'C_raw' => $c->getValue(),
        'C_fmt' => $c->getFormattedValue(),
        'C_type' => $c->getDataType(),
    ];
}
echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
