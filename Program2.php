<?php
// ═══════════════════════════════════════════════════════════════
//  PROGRAMA 2 – Tarpininkė (Man-in-the-Middle)
//  Veikia DVIEM režimais:
//
//  A) CLI (komandinė eilutė):  php program2.php
//     Paleidžia TCP serverį (port 9001), laukia duomenų iš Prog.1,
//     išsaugo juos JSON faile ir parodo nuorodą naršyklei.
//
//  B) Naršyklė: program2.php
//     Rodo gautus duomenis, leidžia pakeisti parašą ir
//     persiunčia į Programą 3 per socket (port 9002).
// ═══════════════════════════════════════════════════════════════

define('LISTEN_PORT',  9001);   // Klausosi iš Prog. 1
define('PROG3_HOST',   '127.0.0.1');
define('PROG3_PORT',   9002);   // Siunčia į Prog. 3
define('DATA_FILE',    __DIR__ . '/prog2_data.json');

// ════════════════════════════════════════════════════════════════
//  CLI REŽIMAS – TCP serveris
// ════════════════════════════════════════════════════════════════
if (PHP_SAPI === 'cli') {
    echo "═══════════════════════════════════════════\n";
    echo "  PROGRAMA 2 – Socket serveris\n";
    echo "  Klausosi: 0.0.0.0:" . LISTEN_PORT . "\n";
    echo "  Duomenys → " . DATA_FILE . "\n";
    echo "  Siunčia į: " . PROG3_HOST . ":" . PROG3_PORT . "\n";
    echo "═══════════════════════════════════════════\n\n";

    $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
    socket_bind($server, '0.0.0.0', LISTEN_PORT);
    socket_listen($server, 5);
    echo "[✓] Serveris paleistas. Laukiama ryšio iš Programos 1...\n";

    while (true) {
        $client = socket_accept($server);
        if (!$client) { continue; }

        $raw = '';
        while ($chunk = socket_read($client, 65536)) {
            $raw .= $chunk;
            if (str_contains($raw, "\n")) break;
        }

        $raw = trim($raw);
        $data = json_decode($raw, true);

        if (!$data || !isset($data['message'], $data['signature'], $data['public_key_pem'])) {
            echo "[✗] Gautas netinkamas JSON.\n";
            socket_write($client, "KLAIDA: Netinkamas JSON formatas\n");
            socket_close($client);
            continue;
        }

        // Išsaugome į failą
        file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "[✓] Gauti duomenys iš Programos 1:\n";
        echo "    Pranešimas : " . substr($data['message'], 0, 60) . "\n";
        echo "    Parašas    : " . substr($data['signature'], 0, 40) . "...\n";
        echo "[→] Atidarykite naršyklėje: http://localhost/program2.php\n\n";

        socket_write($client, "OK: Duomenys gauti. Atidarykite program2.php naršyklėje.\n");
        socket_close($client);
    }
    socket_close($server);
    exit;
}

// ════════════════════════════════════════════════════════════════
//  WEB REŽIMAS – Naršyklė
// ════════════════════════════════════════════════════════════════

/**
 * Siunčiame duomenis į Programą 3 per TCP socket.
 */
function sendToProgram3(array $payload): string {
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!$sock) {
        return 'KLAIDA: socket_create – ' . socket_strerror(socket_last_error());
    }
    if (!@socket_connect($sock, PROG3_HOST, PROG3_PORT)) {
        socket_close($sock);
        return 'KLAIDA: Nepavyko prisijungti prie Programos 3 (' . PROG3_HOST . ':' . PROG3_PORT . '). '
            . 'Vykdykite: php program3.php – ' . socket_strerror(socket_last_error());
    }
    socket_write($sock, $json . "\n", strlen($json) + 1);
    $resp = '';
    socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 3, 'usec' => 0]);
    while ($c = @socket_read($sock, 4096)) {
        $resp .= $c;
        if (str_contains($resp, "\n")) break;
    }
    socket_close($sock);
    return trim($resp) ?: '(Programa 3 negrąžino atsakymo)';
}

// Perskaitome išsaugotus duomenis
$data     = null;
$dataError = null;
if (file_exists(DATA_FILE)) {
    $raw  = file_get_contents(DATA_FILE);
    $data = json_decode($raw, true);
    if (!$data) { $dataError = 'Nepavyko perskaityti duomenų failo.'; }
} else {
    $dataError = 'Duomenų failas nerastas. Paleiskite <code>php program2.php</code> terminale, '
        . 'tada nusiųskite duomenis iš Programos 1.';
}

$sendResult = null;

// POST – persiunčiame į Prog.3
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $data) {
    $modifiedSignature = trim($_POST['signature'] ?? $data['signature']);
    $payload = [
        'message'        => $data['message'],
        'signature'      => $modifiedSignature,
        'public_key_pem' => $data['public_key_pem'],
    ];
    $resp = sendToProgram3($payload);

    $signatureChanged = ($modifiedSignature !== $data['signature']);

    $sendResult = [
        'response'          => $resp,
        'signature_changed' => $signatureChanged,
        'sent_signature'    => $modifiedSignature,
        'payload_json'      => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    ];
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RSA Parašas – Programa 2 (Tarpininkė)</title>
    <link rel="stylesheet" href="style.css">
    <style>
        textarea.modified { border-color: var(--accent2) !important; box-shadow: var(--glow2) !important; }
        .tamper-hint { font-size: .72rem; color: var(--accent2); font-family: var(--mono); margin-top:4px; }
    </style>
</head>
<body>

<header class="site-header">
    <span class="prog-badge badge-2">Programa 2</span>
    <h1>RSA <span style="color:var(--accent2)">Tarpininkė</span></h1>
</header>

<nav class="nav-pills">
    <a href="program1.php" class="nav-pill">① Pasirašymas</a>
    <a href="program2.php" class="nav-pill p2 active">② Tarpininkė</a>
    <a href="program3.php" class="nav-pill p3">③ Tikrinimas</a>
</nav>

<!-- ── Paaiškinimas ── -->
<div class="card">
    <div class="card-header"><span class="dot dot-2"></span>Tarpininkės (Man-in-the-Middle) vaidmuo</div>
    <div class="card-body">
        <div class="row">
            <div class="col">
                <div class="info-block warn">
                    <strong>Kas yra MitM ataka?</strong><br>
                    Tarpininkė perauga Programos 1 siuntimo srautą, gauna pranešimą, parašą ir viešąjį raktą,
                    gali bet ką pakeisti prieš persiųsdama toliau.
                </div>
            </div>
            <div class="col">
                <div class="info-block warn">
                    <strong>Kodėl parašo keitimas aptinkamas?</strong><br>
                    Parašas yra pranešimo maišos RSA šifras. Pakeitus parašą, Programa 3 iššifruos
                    kitokią maišą – ji nesutaps su apskaičiuota → parašas negaliojantis.
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($dataError): ?>
    <div class="card">
        <div class="card-header"><span class="dot dot-2"></span>Statusas</div>
        <div class="card-body">
            <div class="status status-invalid">
                <span class="status-icon">!</span>
            </div>
            <div class="info-block warn" style="margin-top:12px;">
                <?= $dataError ?>
            </div>
            <div class="info-block" style="margin-top:8px;">
                <strong>Instrukcija:</strong><br>
                1. Terminale: <code>php program2.php</code><br>
                2. Naršyklėje eikite į <strong>program1.php</strong> ir nusiųskite pranešimą.<br>
                3. Grįžkite čia ir atnaujinkite puslapį.
            </div>
        </div>
    </div>

<?php else: ?>

    <!-- ── Gauti duomenys ── -->
    <div class="card">
        <div class="card-header"><span class="dot dot-2"></span>Gauti duomenys iš Programos 1</div>
        <div class="card-body">
            <div class="field">
                <label>Pranešimas</label>
                <div class="output-box"><?= htmlspecialchars($data['message']) ?></div>
            </div>
            <div class="field">
                <label>Originalus parašas (Base64) – gautas iš Prog. 1</label>
                <div class="output-box highlight-2" style="max-height:80px;overflow-y:auto;"><?= htmlspecialchars($data['signature']) ?></div>
            </div>
            <div class="field">
                <label>Viešasis raktas (PEM)</label>
                <div class="output-box" style="max-height:100px;overflow-y:auto;"><?= htmlspecialchars($data['public_key_pem']) ?></div>
            </div>
        </div>
    </div>

    <!-- ── Keitimo forma ── -->
    <div class="card">
        <div class="card-header"><span class="dot dot-2"></span>Parašo keitimas ir siuntimas į Programą 3</div>
        <div class="card-body">
            <form method="POST" id="tampForm">
                <div class="field">
                    <label for="signature">Parašas (galite pakeisti žemiau esančiame lauke)</label>
                    <textarea id="sigField" name="signature" rows="5"
                              oninput="checkChange()"><?= htmlspecialchars($data['signature']) ?></textarea>
                    <div class="tamper-hint" id="tampHint" style="display:none;">
                        ⚠ Parašas pakeistas – tikrinimas greičiausiai nepavyks
                    </div>
                </div>

                <div class="row" style="gap:10px;margin-bottom:16px;">
                    <button type="button" class="btn btn-danger" onclick="tamperSignature()">
                        ✎ Sugadinti parašą automatiškai
                    </button>
                    <button type="button" class="btn" style="border-color:var(--muted);color:var(--muted);"
                            onclick="resetSignature()">
                        ↺ Atstatyti originalą
                    </button>
                </div>

                <div class="step-arrow">↓ Persiunčiama į Programą 3 per TCP socket (port <?= PROG3_PORT ?>) ↓</div>

                <button type="submit" class="btn btn-danger">
                    &#9654; Siųsti į Programą 3
                </button>
            </form>

            <?php if ($sendResult): ?>
                <hr class="divider">

                <?php if ($sendResult['signature_changed']): ?>
                    <div class="status status-invalid">
                        <span class="status-icon">⚠</span> Parašas buvo pakeistas prieš siunčiant
                    </div>
                <?php else: ?>
                    <div class="status status-valid">
                        <span class="status-icon">✓</span> Parašas nebuvo keičiamas
                    </div>
                <?php endif; ?>

                <div class="field" style="margin-top:16px;">
                    <label>Išsiųstas JSON paketas į Programą 3</label>
                    <div class="json-preview"><?= htmlspecialchars($sendResult['payload_json']) ?></div>
                </div>

                <div class="field">
                    <label>Atsakymas iš Programos 3</label>
                    <div class="output-box <?= str_starts_with($sendResult['response'], 'KLAIDA') ? 'highlight-2' : 'highlight-3' ?>">
                        <?= htmlspecialchars($sendResult['response']) ?>
                    </div>
                </div>

                <?php if (str_starts_with($sendResult['response'], 'KLAIDA')): ?>
                    <div class="info-block warn">
                        <strong>Kaip paleisti Programą 3?</strong> Terminale: <code>php program3.php</code>
                    </div>
                <?php else: ?>
                    <div class="info-block ok">
                        Duomenys persiųsti. Eikite į <a href="program3.php" style="color:var(--accent3)">program3.php</a>
                        norėdami matyti tikrinimo rezultatą.
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<script>
    const origSig = <?= json_encode($data['signature'] ?? '') ?>;

    function checkChange() {
        const current = document.getElementById('sigField').value;
        const hint    = document.getElementById('tampHint');
        const field   = document.getElementById('sigField');
        if (current !== origSig) {
            hint.style.display = 'block';
            field.classList.add('modified');
        } else {
            hint.style.display = 'none';
            field.classList.remove('modified');
        }
    }

    function tamperSignature() {
        const field = document.getElementById('sigField');
        // Keičiame kelias pirmąsias Base64 raides – tai sugadina parašą
        let sig = field.value;
        const tampered = (sig.charAt(0) === 'A') ? 'B' + sig.slice(1) : 'A' + sig.slice(1);
        field.value = tampered.slice(0, -4) + 'XXXX';
        checkChange();
    }

    function resetSignature() {
        document.getElementById('sigField').value = origSig;
        checkChange();
    }
</script>

</body>
</html>