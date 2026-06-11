<?php
require '/var/www/html/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$file = '/var/www/html/Docs_para_Consulta/Categorias (3).xlsx';
$reader = IOFactory::createReaderForFile($file);
$spreadsheet = $reader->load($file);

$out = [];
foreach ($spreadsheet->getAllSheets() as $sheet) {
    $sheetData = [
        'name' => $sheet->getTitle(),
        'highestRow' => $sheet->getHighestRow(),
        'highestColumn' => $sheet->getHighestColumn(),
        'highestColumnIndex' => Coordinate::columnIndexFromString($sheet->getHighestColumn()),
        'merges' => array_keys($sheet->getMergeCells()),
        'comments' => [],
        'header' => [],
        'rows' => [],
        'columnFormats' => [],
        'dataValidations' => [],
        'freezePane' => $sheet->getFreezePane(),
        'columnWidths' => [],
    ];

    foreach ($sheet->getComments() as $coord => $comment) {
        $sheetData['comments'][] = [
            'cell' => $coord,
            'text' => $comment->getText()->getPlainText(),
            'author' => $comment->getAuthor(),
        ];
    }

    $highestColIdx = $sheetData['highestColumnIndex'];
    $headerRowNum = 1;
    for ($c = 1; $c <= $highestColIdx; $c++) {
        $letter = Coordinate::stringFromColumnIndex($c);
        $cell = $sheet->getCell($letter . $headerRowNum);
        $sheetData['header'][] = [
            'letter' => $letter,
            'rawValue' => $cell->getValue(),
            'formattedValue' => $cell->getFormattedValue(),
            'dataType' => $cell->getDataType(),
        ];
        $styleCell = $sheet->getCell($letter . '2');
        $sheetData['columnFormats'][$letter] = [
            'numberFormat' => $styleCell->getStyle()->getNumberFormat()->getFormatCode(),
            'dataType_row2' => $styleCell->getDataType(),
        ];
        $colDim = $sheet->getColumnDimension($letter);
        $sheetData['columnWidths'][$letter] = $colDim->getWidth();
    }

    $highestRow = $sheetData['highestRow'];
    // Read more rows for hierarchy understanding
    $rowsToRead = min($highestRow, 40);
    for ($r = 2; $r <= $rowsToRead; $r++) {
        $rowData = ['__row' => $r];
        $isEmpty = true;
        for ($c = 1; $c <= $highestColIdx; $c++) {
            $letter = Coordinate::stringFromColumnIndex($c);
            $cell = $sheet->getCell($letter . $r);
            $raw = $cell->getValue();
            $fmt = $cell->getFormattedValue();
            if ($raw !== null && $raw !== '') $isEmpty = false;
            $rowData[$letter] = [
                'raw' => is_scalar($raw) || $raw === null ? $raw : (string)$raw,
                'formatted' => $fmt,
                'type' => $cell->getDataType(),
            ];
        }
        $rowData['__isEmpty'] = $isEmpty;
        $sheetData['rows'][] = $rowData;
    }

    // Also: collect ALL unique values for each column for hierarchy analysis
    $colUnique = [];
    for ($c = 1; $c <= $highestColIdx; $c++) {
        $letter = Coordinate::stringFromColumnIndex($c);
        $vals = [];
        for ($r = 2; $r <= $highestRow; $r++) {
            $cell = $sheet->getCell($letter . $r);
            $v = $cell->getValue();
            if ($v !== null && $v !== '') {
                $vals[] = is_scalar($v) ? $v : (string)$v;
            }
        }
        $colUnique[$letter] = [
            'count_non_empty' => count($vals),
            'unique_count' => count(array_unique($vals)),
            'min_len' => $vals ? min(array_map(fn($x) => strlen((string)$x), $vals)) : null,
            'max_len' => $vals ? max(array_map(fn($x) => strlen((string)$x), $vals)) : null,
        ];
    }
    $sheetData['colStats'] = $colUnique;

    $vals = [];
    for ($c = 1; $c <= $highestColIdx; $c++) {
        $letter = Coordinate::stringFromColumnIndex($c);
        for ($r = 2; $r <= min(5, $highestRow); $r++) {
            $cell = $sheet->getCell($letter . $r);
            if ($cell->hasDataValidation()) {
                $dv = $cell->getDataValidation();
                $vals[$letter . $r] = [
                    'type' => $dv->getType(),
                    'formula1' => $dv->getFormula1(),
                    'formula2' => $dv->getFormula2(),
                    'operator' => $dv->getOperator(),
                    'showDropDown' => $dv->getShowDropDown(),
                    'prompt' => $dv->getPrompt(),
                    'error' => $dv->getError(),
                ];
            }
        }
    }
    $sheetData['dataValidations'] = $vals;

    $out[] = $sheetData;
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
