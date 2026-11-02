<?php
require_once __DIR__ . '/config.php';

$dbCharset = 'utf8';

$mysqli = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if (!$mysqli) {
    http_response_code(500);
    echo 'Andmebaasi uhendus ebaonnestus.<br>';
    echo 'Kontrolli muutujaid: $dbHost, $dbPort, $dbName, $dbUser, $dbPass.<br>';
    echo 'Host: ' . htmlspecialchars((string) $dbHost, ENT_QUOTES, 'UTF-8') . '<br>';
    echo 'Viga: ' . htmlspecialchars(mysqli_connect_error(), ENT_QUOTES, 'UTF-8');
    exit;
}

mysqli_set_charset($mysqli, $dbCharset);

$formKood = '';
$formAsukoht = '';
$formKonteiner = '';
$formKogus = '';
$saveError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'toggle_viga') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $viga = isset($_POST['viga']) && $_POST['viga'] === '1' ? 1 : 0;
        $ok = false;

        if ($id > 0) {
            try {
                $stmt = mysqli_prepare($mysqli, 'UPDATE inventuur SET viga = ? WHERE id = ?');
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'ii', $viga, $id);
                    $ok = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            } catch (Exception $e) {
                $ok = false;
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => (bool) $ok));
        exit;
    }

    if ($action === 'save') {
        $formKood = trim((string) (isset($_POST['kood']) ? $_POST['kood'] : ''));
        $formAsukoht = trim((string) (isset($_POST['asukoht']) ? $_POST['asukoht'] : ''));
        $formKonteiner = trim((string) (isset($_POST['konteiner']) ? $_POST['konteiner'] : ''));
        $formKogus = trim((string) (isset($_POST['kogus']) ? $_POST['kogus'] : ''));
        $kogusRaw = str_replace(',', '.', $formKogus);

        if ($formKood !== '' && $formAsukoht !== '' && $formKonteiner !== '' && is_numeric($kogusRaw)) {
            $koodDb = substr($formKood, 0, 11);
            $asukohtDb = substr($formAsukoht, 0, 9);
            $konteinerDb = substr($formKonteiner, 0, 20);
            $kogusDb = (float) number_format((float) $kogusRaw, 2, '.', '');

            try {
                $stmt = mysqli_prepare(
                    $mysqli,
                    'INSERT INTO inventuur (kood, asukoht, konteiner, kogus, viga) VALUES (?, ?, ?, ?, 0)'
                );
                if (!$stmt) {
                    $saveError = 'Salvestamine ebaonnestus: ' . mysqli_error($mysqli);
                } else {
                    // neli välja: kood, asukoht, konteiner on tekst; kogus on murdarv
                    $bound = mysqli_stmt_bind_param($stmt, 'sssd', $koodDb, $asukohtDb, $konteinerDb, $kogusDb);
                    if (!$bound || !mysqli_stmt_execute($stmt)) {
                        $err = mysqli_stmt_error($stmt);
                        if ($err === '') {
                            $err = mysqli_error($mysqli);
                        }
                        if ($err === '') {
                            $err = 'paring ebaonnestus';
                        }
                        $saveError = 'Salvestamine ebaonnestus: ' . $err;
                    }
                    mysqli_stmt_close($stmt);
                }
            } catch (Exception $e) {
                $saveError = 'Salvestamine ebaonnestus: ' . $e->getMessage();
            }
        } else {
            $saveError = 'Taita kood, asukoht, konteiner ja kogus.';
        }

        if ($saveError === '') {
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
    }
}

$rows = array();
$listError = '';
$rowsResult = mysqli_query(
    $mysqli,
    'SELECT id, kood, asukoht, konteiner, kogus, viga FROM inventuur ORDER BY id DESC'
);
if ($rowsResult) {
    while ($row = mysqli_fetch_assoc($rowsResult)) {
        $rows[] = $row;
    }
    mysqli_free_result($rowsResult);
} else {
    $listError = 'Nimekirja laadimine ebaonnestus: ' . mysqli_error($mysqli);
}
?>
<!doctype html>
<html lang="et">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Inventuur</title>
    <style>
        :root {
            --bg: #e8e8e8;
            --panel: #f4f4f4;
            --text: #111;
            --border: #b8b8b8;
            --accent: #1f6feb;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            padding: 12px;
        }
        .app {
            width: 100%;
            max-width: 580px;
            background: var(--panel);
            border: 1px solid var(--border);
            padding: 12px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: max-content 1fr;
            gap: 10px 12px;
            align-items: center;
        }
        label {
            font-size: 34px;
            line-height: 1.1;
        }
        input[type="text"],
        input[type="number"] {
            width: 100%;
            font-size: 32px;
            padding: 8px 10px;
            border: 1px solid #949494;
            background: #fff;
        }
        .form-error,
        .list-error {
            margin: 0 0 12px;
            padding: 8px 10px;
            background: #ffd9d9;
            border: 1px solid #c47a7a;
            font-size: 18px;
            line-height: 1.3;
        }
        .actions {
            display: flex;
            justify-content: center;
            margin: 18px 0 12px;
        }
        button {
            font-size: 46px;
            padding: 8px 20px;
            border: 1px solid #7f7f7f;
            background: #d9d9d9;
            cursor: pointer;
        }
        button:active {
            transform: translateY(1px);
        }
        .table-wrap {
            border-top: 1px solid var(--border);
            margin-top: 8px;
            padding-top: 12px;
            max-height: 55vh;
            overflow-x: auto;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        /* Tabel: monospace — kood 11, asukoht 9, konteiner 20, kogus, checkbox */
        .inventory-table {
            /* Veeru laius + lahtri padding, et 20-märgiline konteiner ära mahuks */
            width: calc(11ch + 9ch + 20ch + 10ch + 7ch + 20px);
            border-collapse: collapse;
            table-layout: fixed;
            font-family: 'Courier New', Courier, monospace;
            font-size: 15px;
            line-height: 1.2;
        }
        .inventory-table col.col-kood {
            width: calc(11ch + 4px);
        }
        .inventory-table col.col-asukoht {
            width: calc(9ch + 4px);
        }
        .inventory-table col.col-konteiner {
            width: calc(20ch + 4px);
        }
        .inventory-table col.col-kogus {
            /* DECIMAL(9,2): max ~ "9999999.99" = 10 märki */
            width: calc(10ch + 4px);
        }
        .inventory-table col.col-chk {
            width: calc(7ch + 4px);
        }
        .inventory-table th,
        .inventory-table td {
            text-align: left;
            padding: 4px 2px;
            font-size: inherit;
            font-family: inherit;
            white-space: nowrap;
            overflow: visible;
        }
        .inventory-table th {
            font-weight: bold;
            border-bottom: 1px solid var(--border);
        }
        .inventory-table th.num,
        .inventory-table td.num {
            text-align: right;
        }
        .inventory-table td.chk,
        .inventory-table th.chk {
            text-align: center;
            vertical-align: middle;
        }
        .viga-checkbox {
            width: 22px;
            height: 22px;
            accent-color: var(--accent);
            cursor: pointer;
        }
        @media (max-width: 520px) {
            label,
            input[type="text"],
            input[type="number"] {
                font-size: 26px;
            }
            button {
                font-size: 34px;
            }
            .inventory-table {
                font-size: 13px;
            }
            .viga-checkbox {
                width: 20px;
                height: 20px;
            }
        }
        /* Kinnituse aken: checkboxi muudatus */
        .viga-confirm-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: rgba(0, 0, 0, 0.45);
        }
        .viga-confirm-backdrop.is-open {
            display: flex;
        }
        .viga-confirm-dialog {
            width: 100%;
            max-width: 360px;
            background: #f4f4f4;
            border: 1px solid #949494;
            padding: 18px 16px;
            font-family: Arial, sans-serif;
            font-size: 22px;
            line-height: 1.3;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
        }
        .viga-confirm-dialog p {
            margin: 0 0 16px;
            text-align: center;
        }
        .viga-confirm-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .viga-confirm-actions button {
            flex: 1 1 120px;
            font-size: 24px;
            padding: 10px 16px;
            border: 1px solid #7f7f7f;
            cursor: pointer;
        }
        .viga-confirm-no {
            background: #d9d9d9;
        }
        .viga-confirm-yes {
            background: #c8e0ff;
        }
    </style>
</head>
<body>
<main class="app">
    <form method="post" action="">
        <input type="hidden" name="action" value="save">

        <?php if ($saveError !== ''): ?>
            <p class="form-error" role="alert"><?php echo htmlspecialchars($saveError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <div class="form-grid">
            <label for="kood">Kood</label>
            <input id="kood" name="kood" type="text" maxlength="11" required autofocus value="<?php echo htmlspecialchars($formKood, ENT_QUOTES, 'UTF-8'); ?>">

            <label for="asukoht">Asukoht</label>
            <input id="asukoht" name="asukoht" type="text" maxlength="9" required value="<?php echo htmlspecialchars($formAsukoht, ENT_QUOTES, 'UTF-8'); ?>">

            <label for="konteiner">Konteiner</label>
            <input id="konteiner" name="konteiner" type="text" maxlength="20" required value="<?php echo htmlspecialchars($formKonteiner, ENT_QUOTES, 'UTF-8'); ?>">

            <label for="kogus">Kogus</label>
            <input id="kogus" name="kogus" type="number" inputmode="decimal" step="0.01" required value="<?php echo htmlspecialchars($formKogus, ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="actions">
            <button type="submit">Salvesta</button>
        </div>
    </form>

    <div class="table-wrap">
        <?php if ($listError !== ''): ?>
            <p class="list-error" role="alert"><?php echo htmlspecialchars($listError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <table class="inventory-table">
            <colgroup>
                <col class="col-kood">
                <col class="col-asukoht">
                <col class="col-konteiner">
                <col class="col-kogus">
                <col class="col-chk">
            </colgroup>
            <thead>
            <tr>
                <th>Kood</th>
                <th>Asukoht</th>
                <th>Konteiner</th>
                <th class="num">Kogus</th>
                <th class="chk">Vigane</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $konteinerCell = isset($row['konteiner']) ? $row['konteiner'] : '';
                ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) $row['kood'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) $row['asukoht'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) $konteinerCell, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="num"><?php echo number_format((float) $row['kogus'], 2, '.', ''); ?></td>
                    <td class="chk">
                        <input
                            class="viga-checkbox"
                            type="checkbox"
                            data-id="<?php echo (int) $row['id']; ?>"
                            <?php echo ((int) $row['viga'] === 1) ? 'checked' : ''; ?>
                        >
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<div id="viga-confirm-backdrop" class="viga-confirm-backdrop" aria-hidden="true">
    <div class="viga-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="viga-confirm-title">
        <p id="viga-confirm-title">Salvestan muudatuse?</p>
        <div class="viga-confirm-actions">
            <button type="button" class="viga-confirm-no" id="viga-confirm-no">Ei</button>
            <button type="button" class="viga-confirm-yes" id="viga-confirm-yes">Jah</button>
        </div>
    </div>
</div>

<script>
(function () {
    var backdrop = document.getElementById('viga-confirm-backdrop');
    var btnNo = document.getElementById('viga-confirm-no');
    var btnYes = document.getElementById('viga-confirm-yes');
    var pendingCheckbox = null;
    var pendingNewChecked = false;

    function openConfirmModal() {
        backdrop.classList.add('is-open');
        backdrop.setAttribute('aria-hidden', 'false');
        setTimeout(function () {
            btnNo.focus();
        }, 0);
    }

    function closeConfirmModal() {
        backdrop.classList.remove('is-open');
        backdrop.setAttribute('aria-hidden', 'true');
        pendingCheckbox = null;
    }

    function saveViga(checkbox, checked) {
        var formData = new FormData();
        formData.append('action', 'toggle_viga');
        formData.append('id', checkbox.dataset.id);
        formData.append('viga', checked ? '1' : '0');

        fetch('', {
            method: 'POST',
            body: formData
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('bad status');
            }
            return response.json();
        }).then(function (data) {
            if (!data || !data.ok) {
                throw new Error('not ok');
            }
        }).catch(function () {
            checkbox.checked = !checked;
            alert('Salvestamine ebaonnestus.');
        });
    }

    btnNo.addEventListener('click', function () {
        closeConfirmModal();
    });

    btnYes.addEventListener('click', function () {
        var cb = pendingCheckbox;
        var want = pendingNewChecked;
        closeConfirmModal();
        if (!cb) {
            return;
        }
        cb.checked = want;
        saveViga(cb, want);
    });

    backdrop.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            closeConfirmModal();
        }
    });

    document.querySelectorAll('.viga-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            var newChecked = checkbox.checked;
            var oldChecked = !newChecked;
            checkbox.checked = oldChecked;

            pendingCheckbox = checkbox;
            pendingNewChecked = newChecked;
            openConfirmModal();
        });
    });
})();
</script>
</body>
</html>
