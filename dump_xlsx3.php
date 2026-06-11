<?php
// Extract raw XML files from xlsx to see actual cell fills
$file = '/var/www/html/Docs_para_Consulta/HYBIntegrador_bens.xlsx';
$zip = new ZipArchive();
$zip->open($file);
$out = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $out[] = $zip->getNameIndex($i);
}
file_put_contents('/var/www/html/dump_xlsx3_files.txt', implode("\n", $out));

// Read styles.xml and sheet1
$styles = $zip->getFromName('xl/styles.xml');
file_put_contents('/var/www/html/dump_xlsx3_styles.xml', $styles);
$sheet1 = $zip->getFromName('xl/worksheets/sheet1.xml');
file_put_contents('/var/www/html/dump_xlsx3_sheet1.xml', $sheet1);
$sheet2 = $zip->getFromName('xl/worksheets/sheet2.xml');
file_put_contents('/var/www/html/dump_xlsx3_sheet2.xml', $sheet2);
echo "OK\n";
