<?php
require '/var/www/html/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$file = '/var/www/html/Docs_para_Consulta/HYBIntegrador_bens.xlsx';
$reader = IOFactory::createReaderForFile($file);
// Keep formatting/types
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

    // Comments
    foreach ($sheet->getComments() as $coord => $comment) {
        $sheetData['comments'][] = [
            'cell' => $coord,
            'text' => $comment->getText()->getPlainText(),
            'author' => $comment->getAuthor(),
        ];
    }

    // Determine header row: row 1 typically
    $highestColIdx = $sheetData['highestColumnIndex'];
    // Build header row (row 1) preserving raw value (no calculated) to keep spaces
    $headerRowNum = 1;
    for ($c = 1; $c <= $highestColIdx; $c++) {
        $letter = Coordinate::stringFromColumnIndex($c);
        $cell = $sheet->getCell($letter . $headerRowNum);
        $sheetData['header'][] = [
            'letter' => $letter,
            'rawValue' => $cell->getValue(),
            'formattedValue' => $cell->getFormattedValue(),
            'calculatedValue' => null, // skip
            'dataType' => $cell->getDataType(),
        ];
        // Column format from style of a data cell (row 2)
        $styleCell = $sheet->getCell($letter . '2');
        $sheetData['columnFormats'][$letter] = [
            'numberFormat' => $styleCell->getStyle()->getNumberFormat()->getFormatCode(),
            'dataType_row2' => $styleCell->getDataType(),
        ];
        // Column width
        $colDim = $sheet->getColumnDimension($letter);
        $sheetData['columnWidths'][$letter] = $colDim->getWidth();
    }

    // Data rows: from row 2 to highestRow but cap at 15 sample rows for analysis
    $highestRow = $sheetData['highestRow'];
    $rowsToRead = min($highestRow, 15);
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

    // Data validations: PhpSpreadsheet stores per-cell; iterate cells in row 2 range
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

file_put_contents('/var/www/html/dump_xlsx.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE));
echo "OK\n";
