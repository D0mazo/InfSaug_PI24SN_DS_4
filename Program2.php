<?php
// ─────────────────────────────────────────────────────────────
//  PROGRAMA 2 – Tarpininkė (Man-in-the-Middle)
//
//  CLI:  php program2.php  → TCP serveris (port 9001)
//  Web:  program2.php      → UI parašo keitimui ir siuntimui
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';

use Rsa\SocketClient;

// ── CLI: socket serveris ─────────────────────────────────────
if (PHP_SAPI === 'cli') {
    echo "═══════════════════════════════════════════\n";
    echo "  PROGRAMA 2 – Tarpininkės serveris\n";
    echo "  Klausosi : 0.0.0.0:" . LISTEN_PORT2 . "\n";
    echo "  Duomenys : " . PROG2_DATA_FILE . "\n";
    echo "═══════════════════════════════════════════\n\n";

    $server = stream_socket_server(
            'tcp://0.0.0.0:' . LISTEN_PORT2,
            $errno, $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
    );

    if (!$server) {
        echo "[x] Nepavyko paleisti serverio: $errstr ($errno)\n";
        exit(1);
    }

    echo "[OK] Laukiama duomenų iš Programos 1...\n";

    while (true) {
        $client = @stream_socket_accept($server, 30);
        if (!$client) continue;

        $raw = '';
        stream_set_timeout($client, 5);
        while (!feof($client)) {
            $chunk = fgets($client, 65536);
            if ($chunk === false) break;
            $raw .= $chunk;
            if (str_contains($raw, "\n")) break;
        }

        $data = json_decode(trim($raw), true);

        if (!$data || !isset($data['message'], $data['signature'], $data['public_key_pem'])) {
            fwrite($client, "KLAIDA: Netinkamas JSON\n");
            fclose($client);
            continue;
        }

        file_put_contents(PROG2_DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo "[OK] Gauta iš Prog.1:\n";
        echo "     Pranešimas : " . substr($data['message'], 0, 60) . "\n";
        echo "     Parašas    : " . substr($data['signature'], 0, 40) . "...\n";
        echo "[->] Atidarykite: http://localhost/program2.php\n\n";

        fwrite($client, "OK: Duomenys gauti. Atidarykite program2.php\n");
        fclose($client);
    }

    fclose($server);
    exit;
}

// ── Web: UI ──────────────────────────────────────────────────
$data      = null;
$dataError = null;

if (file_exists(PROG2_DATA_FILE)) {
    $data = json_decode(file_get_contents(PROG2_DATA_FILE), true);
    if (!$data) $dataError = 'Nepavyko perskaityti duomenų failo.';
} else {
    $dataError = 'Duomenų failas nerastas. Paleiskite <code>php program2.php</code> terminale ir nusiųskite pranešimą iš Programos 1.';
}

$sendResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $data) {
    $modifiedSig = trim($_POST['signature'] ?? $data['signature']);
    $payload = [
            'message'        => $data['message'],
            'signature'      => $modifiedSig,
            'public_key_pem' => $data['public_key_pem'],
    ];

    $client   = new SocketClient(PROG3_HOST, PROG3_PORT);
    $response = $client->send($payload);
    $changed  = ($modifiedSig !== $data['signature']);

    $sendResult = [
            'response'     => $response,
            'changed'      => $changed,
            'payload_json' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    ];
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Programa 2 – Tarpininkė</title>
    <link rel="stylesheet" href="style.css">
    <style>
        textarea.modified { border-color: var(--red) !important; }
        .tamper-hint { font-size: .75rem; color: var(--red); margin-top: 4px; }
    </style>
</head>
<body>

<header class="site-header">
    <span class="prog-badge badge-2">Programa 2</span>
    <h1>RSA <span style="color:var(--red)">Tarpininkė</span></h1>
</header>

<nav class="nav-pills">
    <a href="program1.php" class="nav-pill">① Pasirašymas</a>
    <a href="program2.php" class="nav-pill active">② Tarpininkė</a>
    <a href="program3.php" class="nav-pill">③ Tikrinimas</a>
</nav>

<div class="card">
    <div class="card-header"><span class="dot"></span>Man-in-the-Middle tarpininkės vaidmuo</div>
    <div class="card-body">
        <div class="row">
            <div class="col">
                <div class="info-block warn">
                    <strong>Kas vyksta čia?</strong><br>
                    Tarpininkė gauna pranešimą, parašą ir viešąjį raktą iš Programos 1,
                    gali pakeisti parašą ir persiunčia duomenis į Programą 3.
                </div>
            </div>
            <div class="col">
                <div class="info-block warn">
                    <strong>Kodėl keitimas aptinkamas?</strong><br>
                    Parašas yra SHA-256 maišos RSA šifras. Bet koks pakeitimas
                    sukels maišų neatitikimą Programoje 3 → parašas negaliojantis.
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($dataError): ?>
    <div class="card">
        <div class="card-header"><span class="dot"></span>Statusas</div>
        <div class="card-body">
            <div class="info-block warn"><?= $dataError ?></div>
            <div class="info-block" style="margin-top:8px;">
                <strong>Žingsniai:</strong><br>
                1. <code>php program3.php</code> – paleiskite tikrinimo serverį<br>
                2. <code>php program2.php</code> – paleiskite šį serverį<br>
                3. <a href="program1.php">program1.php</a> – nusiųskite pranešimą<br>
                4. Atnaujinkite šį puslapį
            </div>
        </div>
    </div>

<?php else: ?>

    <div class="card">
        <div class="card-header"><span class="dot"></span>Gauti duomenys iš Programos 1</div>
        <div class="card-body">
            <div class="field">
                <label>Pranešimas</label>
                <div class="output-box"><?= htmlspecialchars($data['message']) ?></div>
            </div>
            <div class="field">
                <label>Originalus parašas (Base64)</label>
                <div class="output-box red" style="max-height:80px;overflow-y:auto;"><?= htmlspecialchars($data['signature']) ?></div>
            </div>
            <div class="field">
                <label>Viešasis raktas (PEM)</label>
                <div class="output-box" style="max-height:100px;overflow-y:auto;"><?= htmlspecialchars($data['public_key_pem']) ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="dot"></span>Parašo keitimas ir siuntimas į Programą 3</div>
        <div class="card-body">
            <form method="POST">
                <div class="field">
                    <label for="sigField">Parašas (galite keisti)</label>
                    <textarea id="sigField" name="signature" rows="5"
                              oninput="checkChange()"><?= htmlspecialchars($data['signature']) ?></textarea>
                    <div class="tamper-hint" id="tampHint" style="display:none;">
                        ⚠ Parašas pakeistas – tikrinimas greičiausiai nepavyks
                    </div>
                </div>

                <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
                    <button type="button" class="btn btn-danger" onclick="tamperSignature()">✎ Sugadinti automatiškai</button>
                    <button type="button" class="btn btn-ghost" onclick="resetSignature()">↺ Atstatyti originalą</button>
                </div>

                <div class="step-arrow">↓ Persiunčiama į Programą 3 per TCP socket (port <?= PROG3_PORT ?>) ↓</div>
                <button type="submit" class="btn btn-danger">&#9654; Siųsti į Programą 3</button>
            </form>

            <?php if ($sendResult): ?>
                <hr class="divider">

                <?php if ($sendResult['changed']): ?>
                    <div class="status status-invalid">⚠ Parašas buvo pakeistas prieš siunčiant</div>
                <?php else: ?>
                    <div class="status status-valid">✓ Parašas nebuvo keičiamas</div>
                <?php endif; ?>

                <div class="field" style="margin-top:14px;">
                    <label>Išsiųstas JSON į Programą 3</label>
                    <div class="json-preview"><?= htmlspecialchars($sendResult['payload_json']) ?></div>
                </div>

                <div class="field">
                    <label>Atsakymas iš Programos 3</label>
                    <div class="output-box <?= str_starts_with($sendResult['response'], 'KLAIDA') ? 'red' : 'green' ?>">
                        <?= htmlspecialchars($sendResult['response']) ?>
                    </div>
                </div>

                <?php if (str_starts_with($sendResult['response'], 'KLAIDA')): ?>
                    <div class="info-block warn" style="margin-top:8px;">
                        <strong>Programa 3 neveikia.</strong> Terminale: <code>php program3.php</code>
                    </div>
                <?php else: ?>
                    <div class="info-block ok" style="margin-top:8px;">
                        Duomenys persiųsti. Eikite į <a href="program3.php">program3.php</a> matyti rezultatą.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<script>
    const origSig = <?= json_encode($data['signature'] ?? '') ?>;

    function checkChange() {
        const val   = document.getElementById('sigField').value;
        const hint  = document.getElementById('tampHint');
        const field = document.getElementById('sigField');
        const changed = val !== origSig;
        hint.style.display = changed ? 'block' : 'none';
        field.classList.toggle('modified', changed);
    }

    function tamperSignature() {
        const f = document.getElementById('sigField');
        let s = f.value;
        f.value = (s.charAt(0) === 'A' ? 'B' : 'A') + s.slice(1, -4) + 'XXXX';
        checkChange();
    }

    function resetSignature() {
        document.getElementById('sigField').value = origSig;
        checkChange();
    }
</script>

</body>
</html>