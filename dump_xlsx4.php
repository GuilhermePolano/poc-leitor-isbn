<?php
$file = '/var/www/html/Docs_para_Consulta/HYBIntegrador_bens.xlsx';
$zip = new ZipArchive();
$zip->open($file);
$ss = $zip->getFromName('xl/sharedStrings.xml');
file_put_contents('/var/www/html/dump_sharedstrings.xml', $ss);
echo "OK\n";
