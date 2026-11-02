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

$timestamp = date('Y-m-d_H-i-s');
$downloadName = 'inventuur_' . $timestamp . '.csv';

header('Content-Description: File Transfer');
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Excel loeb UTF-8 paremini, kui fail algab BOM-iga
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Päiserida
fputcsv($out, array('Kood', 'Asukoht', 'Konteiner', 'Kogus', 'Vigane'), ';');

while ($row = mysqli_fetch_assoc($result)) {
    $viganeText = ((int) $row['viga'] === 1) ? 'Jah' : 'Ei';
    $konteiner = isset($row['konteiner']) ? $row['konteiner'] : '';

    fputcsv(
        $out,
        array(
            $row['kood'],
            $row['asukoht'],
            $konteiner,
            number_format((float) $row['kogus'], 2, '.', ''),
            $viganeText
        ),
        ';'
    );
}

fclose($out);
mysqli_free_result($result);
mysqli_close($mysqli);
exit;
