<?php

require_once __DIR__ . '/config.php';

$dbCharset = 'utf8';
$dbPort = isset($dbPort) ? (int) $dbPort : 3306;

$mysqli = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if (!$mysqli) {
    http_response_code(500);
    echo 'Andmebaasi uhendus ebaonnestus: ' . htmlspecialchars(mysqli_connect_error(), ENT_QUOTES, 'UTF-8');
    exit;
}
mysqli_set_charset($mysqli, $dbCharset);

$sql = 'SELECT kood, asukoht, konteiner, kogus, viga FROM inventuur ORDER BY id ASC';
$result = mysqli_query($mysqli, $sql);
if (!$result) {
    http_response_code(500);
    echo 'Andmete lugemine ebaonnestus: ' . htmlspecialchars(mysqli_error($mysqli), ENT_QUOTES, 'UTF-8');
    exit;
}

function xlsxEscape($value)
{
    $value = (string) $value;
    $value = str_replace('&', '&amp;', $value);
    $value = str_replace('<', '&lt;', $value);
    $value = str_replace('>', '&gt;', $value);
    return $value;
}

$rowsXml = '';
$rowIndex = 1;

// Päiserida.
$headers = array('Kood', 'Asukoht', 'Konteiner', 'Kogus', 'Vigane');
$cells = '';
foreach ($headers as $header) {
    $cells .= '<c t="inlineStr"><is><t>' . xlsxEscape($header) . '</t></is></c>';
}
$rowsXml .= '<row r="' . $rowIndex . '">' . $cells . '</row>';
$rowIndex++;

while ($row = mysqli_fetch_assoc($result)) {
    $viganeText = ((int) $row['viga'] === 1) ? 'Jah' : 'Ei';
    $konteiner = isset($row['konteiner']) ? $row['konteiner'] : '';
    $cells = '';
    $cells .= '<c t="inlineStr"><is><t>' . xlsxEscape($row['kood']) . '</t></is></c>';
    $cells .= '<c t="inlineStr"><is><t>' . xlsxEscape($row['asukoht']) . '</t></is></c>';
    $cells .= '<c t="inlineStr"><is><t>' . xlsxEscape($konteiner) . '</t></is></c>';
    $cells .= '<c t="n"><v>' . number_format((float) $row['kogus'], 2, '.', '') . '</v></c>';
    $cells .= '<c t="inlineStr"><is><t>' . $viganeText . '</t></is></c>';
    $rowsXml .= '<row r="' . $rowIndex . '">' . $cells . '</row>';
    $rowIndex++;
}

mysqli_free_result($result);
mysqli_close($mysqli);

$sheetXml =
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
    '<sheetViews><sheetView workbookViewId="0"/></sheetViews>' .
    '<sheetFormatPr defaultRowHeight="15"/>' .
    '<cols>' .
    '<col min="1" max="1" width="15" customWidth="1"/>' .
    '<col min="2" max="2" width="14" customWidth="1"/>' .
    '<col min="3" max="3" width="22" customWidth="1"/>' .
    '<col min="4" max="4" width="12" customWidth="1"/>' .
    '<col min="5" max="5" width="10" customWidth="1"/>' .
    '</cols>' .
    '<sheetData>' . $rowsXml . '</sheetData>' .
    '</worksheet>';

$contentTypesXml =
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
    '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
    '<Default Extension="xml" ContentType="application/xml"/>' .
    '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
    '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
    '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' .
    '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>' .
    '</Types>';

$relsXml =
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
    '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
    '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>' .
    '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>' .
    '</Relationships>';

$workbookXml =
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ' .
    'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
    '<sheets><sheet name="Inventuur" sheetId="1" r:id="rId1"/></sheets>' .
    '</workbook>';

$workbookRelsXml =
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
    '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
    '</Relationships>';

$timestamp = date('Y-m-d_H-i-s');
$coreXml =
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" ' .
    'xmlns:dc="http://purl.org/dc/elements/1.1/" ' .
    'xmlns:dcterms="http://purl.org/dc/terms/" ' .
    'xmlns:dcmitype="http://purl.org/dc/dcmitype/" ' .
    'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">' .
    '<dc:title>Inventuur eksport</dc:title>' .
    '<dc:creator>Inventuur</dc:creator>' .
    '<cp:lastModifiedBy>Inventuur</cp:lastModifiedBy>' .
    '<dcterms:created xsi:type="dcterms:W3CDTF">' . date('c') . '</dcterms:created>' .
    '<dcterms:modified xsi:type="dcterms:W3CDTF">' . date('c') . '</dcterms:modified>' .
    '</cp:coreProperties>';

$appXml =
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" ' .
    'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">' .
    '<Application>PHP</Application>' .
    '</Properties>';

$tmpFile = tempnam(sys_get_temp_dir(), 'inv_xlsx_');
$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo 'XLSX faili loomine ebaonnestus.';
    exit;
}

$zip->addFromString('[Content_Types].xml', $contentTypesXml);
$zip->addFromString('_rels/.rels', $relsXml);
$zip->addFromString('xl/workbook.xml', $workbookXml);
$zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
$zip->addFromString('docProps/core.xml', $coreXml);
$zip->addFromString('docProps/app.xml', $appXml);
$zip->close();

$downloadName = 'inventuur_' . $timestamp . '.xlsx';
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($tmpFile));

readfile($tmpFile);
unlink($tmpFile);
exit;
