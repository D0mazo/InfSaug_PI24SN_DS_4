<?php
// ═══════════════════════════════════════════════════════════════
//  PROGRAMA 3 – Parašo tikrinimas
//  Veikia DVIEM režimais:
//
//  A) CLI:  php program3.php
//     Paleidžia TCP serverį (port 9002), laukia duomenų iš Prog.2,
//     tikrina RSA parašą ir išsaugo rezultatą JSON faile.
//
//  B) Naršyklė: program3.php
//     Rodo tikrinimo rezultatą (galiojantis / negaliojantis).
// ═══════════════════════════════════════════════════════════════

define('LISTEN_PORT', 9002);
define('DATA_FILE',   __DIR__ . '/prog3_result.json');

// ════════════════════════════════════════════════════════════════
//  Tikrinimo funkcija
// ════════════════════════════════════════════════════════════════

/**
 * Tikriname RSA-SHA256 parašą.
 * Grąžina ['valid' => bool, 'computed_hash' => string, 'error' => string|null]
 */
function verifySignature(string $message, string $signatureBase64, string $publicKeyPem): array {
    $publicKey = openssl_pkey_get_public($publicKeyPem);
    if (!$publicKey) {
        return ['valid' => false, 'computed_hash' => hash('sha256', $message),
            'error' => 'Nepavyko įkelti viešojo rakto: ' . openssl_error_string()];
    }

    $signature = base64_decode($signatureBase64, true);
    if ($signature === false) {
        return ['valid' => false, 'computed_hash' => hash('sha256', $message),
            'error' => 'Parašas – netinkamas Base64 formatas'];
    }

    $result = openssl_verify($message, $signature, $publicKey, OPENSSL_ALGO_SHA256);

    return [
        'valid'         => ($result === 1),
        'computed_hash' => hash('sha256', $message),
        'error'         => $result === -1 ? ('openssl_verify klaida: ' . openssl_error_string()) : null,
    ];
}

// ════════════════════════════════════════════════════════════════
//  CLI REŽIMAS – TCP serveris
// ════════════════════════════════════════════════════════════════
if (PHP_SAPI === 'cli') {
    echo "═══════════════════════════════════════════\n";
    echo "  PROGRAMA 3 – Tikrinimo serveris\n";
    echo "  Klausosi: 0.0.0.0:" . LISTEN_PORT . "\n";
    echo "  Rezultatas → " . DATA_FILE . "\n";
    echo "═══════════════════════════════════════════\n\n";

    $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
    socket_bind($server, '0.0.0.0', LISTEN_PORT);
    socket_listen($server, 5);
    echo "[✓] Serveris paleistas. Laukiama ryšio iš Programos 2...\n";

    while (true) {
        $client = socket_accept($server);
        if (!$client) continue;

        $raw = '';
        while ($chunk = socket_read($client, 65536)) {
            $raw .= $chunk;
            if (str_contains($raw, "\n")) break;
        }
        $raw  = trim($raw);
        $data = json_decode($raw, true);

        if (!$data || !isset($data['message'], $data['signature'], $data['public_key_pem'])) {
            echo "[✗] Netinkamas JSON.\n";
            socket_write($client, "KLAIDA: Netinkamas JSON formatas\n");
            socket_close($client);
            continue;
        }

        // Tikriname
        $check = verifySignature($data['message'], $data['signature'], $data['public_key_pem']);

        $resultData = [
            'timestamp'      => date('Y-m-d H:i:s'),
            'message'        => $data['message'],
            'signature'      => $data['signature'],
            'public_key_pem' => $data['public_key_pem'],
            'valid'          => $check['valid'],
            'computed_hash'  => $check['computed_hash'],
            'verify_error'   => $check['error'],
        ];

        file_put_contents(DATA_FILE, json_encode($resultData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $status = $check['valid'] ? 'GALIOJANTIS' : 'NEGALIOJANTIS';
        echo "[" . ($check['valid'] ? '✓' : '✗') . "] Parašas: $status\n";
        echo "    Pranešimas : " . substr($data['message'], 0, 60) . "\n";
        echo "    SHA-256    : " . $check['computed_hash'] . "\n";
        if ($check['error']) echo "    Klaida     : " . $check['error'] . "\n";
        echo "[→] Atidarykite naršyklėje: http://localhost/program3.php\n\n";

        socket_write($client, "OK: Tikrinimas atliktas – $status. Žr. program3.php\n");
        socket_close($client);
    }
    socket_close($server);
    exit;
}

// ════════════════════════════════════════════════════════════════
//  WEB REŽIMAS – Naršyklė
// ════════════════════════════════════════════════════════════════
$result    = null;
$dataError = null;

if (file_exists(DATA_FILE)) {
    $result = json_decode(file_get_contents(DATA_FILE), true);
    if (!$result) { $dataError = 'Nepavyko perskaityti rezultato failo.'; }
} else {
    $dataError = 'Rezultato failas nerastas. Pasekite proceso žingsnius pradedant nuo Programos 1.';
}

// Taip pat leidžiame tiesiogiai tikrinti per formą (bypass socket)
$manualResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg    = trim($_POST['message']        ?? '');
    $sig    = trim($_POST['signature']      ?? '');
    $pubKey = trim($_POST['public_key_pem'] ?? '');
    if ($msg && $sig && $pubKey) {
        $check = verifySignature($msg, $sig, $pubKey);
        $manualResult = array_merge($check, [
            'message'   => $msg,
            'signature' => $sig,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RSA Parašas – Programa 3 (Tikrinimas)</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .big-status {
            text-align: center;
            padding: 36px 24px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .big-status .icon { font-size: 3rem; line-height: 1; margin-bottom: 10px; }
        .big-status .label { font-family: var(--mono); font-size: 1.3rem; letter-spacing: .12em; text-transform: uppercase; }
        .big-status .sub   { font-size: .8rem; color: var(--muted); margin-top: 6px; }
        .big-valid   { background: rgba(184,255,87,.08); border: 2px solid var(--accent3); }
        .big-valid .label { color: var(--accent3); }
        .big-invalid { background: rgba(255,45,120,.08); border: 2px solid var(--accent2); }
        .big-invalid .label { color: var(--accent2); }
        .hash-row { display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap; }
        .hash-row .badge { font-family: var(--mono); font-size:.65rem; padding: 2px 8px; border-radius: 2px; white-space:nowrap; margin-top:3px; }
        .eq { color: var(--accent3); border: 1px solid var(--accent3); }
        .neq { color: var(--accent2); border: 1px solid var(--accent2); }
    </style>
</head>
<body>

<header class="site-header">
    <span class="prog-badge badge-3">Programa 3</span>
    <h1>RSA <span style="color:var(--accent3)">Tikrinimas</span></h1>
</header>

<nav class="nav-pills">
    <a href="program1.php" class="nav-pill">① Pasirašymas</a>
    <a href="program2.php" class="nav-pill p2">② Tarpininkė</a>
    <a href="program3.php" class="nav-pill p3 active">③ Tikrinimas</a>
</nav>

<!-- ── Tikrinimo proceso paaiškinimas ── -->
<div class="card">
    <div class="card-header"><span class="dot dot-3"></span>Tikrinimo procesas</div>
    <div class="card-body">
        <div class="row">
            <div class="col">
                <div class="info-block ok">
                    <strong>Žingsnis 1: Parašo iššifravimas</strong><br>
                    Gautą parašą iššifruojame siuntėjo <em>viešuoju raktu</em>.
                    Tai grąžina pradinę maišos reikšmę, kurią siuntėjas pasirašė.
                </div>
            </div>
            <div class="col">
                <div class="info-block ok">
                    <strong>Žingsnis 2: Maišos apskaičiavimas</strong><br>
                    Nepriklausomai apskaičiuojame gauto <em>pranešimo SHA-256 maišą</em>.
                </div>
            </div>
            <div class="col">
                <div class="info-block ok">
                    <strong>Žingsnis 3: Palyginimas</strong><br>
                    Jei abi maišos <strong>sutampa</strong> → parašas galiojantis.<br>
                    Jei <strong>nesutampa</strong> → pranešimas ar parašas buvo pakeisti.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Socket rezultatas ── -->
<?php if ($dataError): ?>
    <div class="card">
        <div class="card-header"><span class="dot dot-3"></span>Socket tikrinimo rezultatas</div>
        <div class="card-body">
            <div class="info-block warn"><?= $dataError ?></div>
            <div class="info-block" style="margin-top:8px;">
                <strong>Proceso žingsniai:</strong><br>
                1. <code>php program3.php</code> – paleidžia šį serverį<br>
                2. <code>php program2.php</code> – paleidžia tarpininkę<br>
                3. Naršyklėje → <a href="program1.php" style="color:var(--accent1)">program1.php</a> – įveskite pranešimą ir siųskite<br>
                4. Naršyklėje → <a href="program2.php" style="color:var(--accent2)">program2.php</a> – (nebūtina pakeisti) siųskite į čia<br>
                5. Atnaujinkite šį puslapį.
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="card">
        <div class="card-header">
            <span class="dot <?= $result['valid'] ? 'dot-3' : 'dot-2' ?>"></span>
            Socket tikrinimo rezultatas
            <span style="margin-left:auto;font-size:.68rem;color:var(--muted);"><?= htmlspecialchars($result['timestamp']) ?></span>
        </div>
        <div class="card-body">

            <!-- Didelis statusas -->
            <div class="big-status <?= $result['valid'] ? 'big-valid' : 'big-invalid' ?>">
                <div class="icon"><?= $result['valid'] ? '✓' : '✗' ?></div>
                <div class="label"><?= $result['valid'] ? 'Parašas galiojantis' : 'Parašas NEGALIOJANTIS' ?></div>
                <div class="sub">
                    <?= $result['valid']
                        ? 'Pranešimas nepakeistas. Parašas atitinka viešąjį raktą.'
                        : 'Maišos reikšmės nesutampa arba parašas sugadintas. Galimas pakeitimas!' ?>
                </div>
            </div>

            <?php if ($result['verify_error']): ?>
                <div class="info-block warn">
                    <strong>Tikrinimo klaida:</strong> <?= htmlspecialchars($result['verify_error']) ?>
                </div>
                <div style="margin-top:12px;"></div>
            <?php endif; ?>

            <!-- Pranešimas -->
            <div class="field">
                <label>Gautas pranešimas</label>
                <div class="output-box"><?= htmlspecialchars($result['message']) ?></div>
            </div>

            <!-- Maišos palyginimas -->
            <div class="field">
                <label>SHA-256 maišos reikšmė (apskaičiuota iš gauto pranešimo)</label>
                <div class="hash-row">
                    <div class="output-box highlight-3" style="flex:1;"><?= htmlspecialchars($result['computed_hash']) ?></div>
                    <span class="badge <?= $result['valid'] ? 'eq' : 'neq' ?>"><?= $result['valid'] ? '= sutampa' : '≠ nesutampa' ?></span>
                </div>
            </div>

            <!-- Parašas -->
            <div class="field">
                <label>Gautas parašas (Base64)</label>
                <div class="output-box <?= $result['valid'] ? 'highlight-3' : 'highlight-2' ?>"
                     style="max-height:80px;overflow-y:auto;">
                    <?= htmlspecialchars($result['signature']) ?>
                </div>
            </div>

            <?php if (!$result['valid']): ?>
                <div class="info-block warn">
                    <strong>Tikėtina priežastis:</strong> Programa 2 pakeitė parašą arba pranešimą prieš persiųsdama.
                    Net vienas simbolis parašo tekste pilnai sugadina tikrinimo rezultatą.
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- ── Rankinis tikrinimas ── -->
<div class="card">
    <div class="card-header"><span class="dot dot-3"></span>Rankinis tikrinimas (nepriklausomas nuo socket)</div>
    <div class="card-body">
        <div class="info-block" style="margin-bottom:16px;">
            Galite rankiniu būdu įvesti pranešimą, parašą ir viešąjį raktą tikrinimui be socket proceso.
        </div>
        <form method="POST">
            <div class="field">
                <label for="m_msg">Pranešimas</label>
                <textarea id="m_msg" name="message" rows="3"
                          placeholder="Įveskite pranešimą..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </div>
            <div class="field">
                <label for="m_sig">Parašas (Base64)</label>
                <textarea id="m_sig" name="signature" rows="4"
                          placeholder="Įveskite parašą (Base64)..."><?= htmlspecialchars($_POST['signature'] ?? '') ?></textarea>
            </div>
            <div class="field">
                <label for="m_pub">Viešasis raktas (PEM)</label>
                <textarea id="m_pub" name="public_key_pem" rows="6"
                          placeholder="-----BEGIN PUBLIC KEY-----&#10;...&#10;-----END PUBLIC KEY-----"><?= htmlspecialchars($_POST['public_key_pem'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-success">&#9654; Tikrinti</button>
        </form>

        <?php if ($manualResult): ?>
            <hr class="divider">
            <div class="big-status <?= $manualResult['valid'] ? 'big-valid' : 'big-invalid' ?>">
                <div class="icon"><?= $manualResult['valid'] ? '✓' : '✗' ?></div>
                <div class="label"><?= $manualResult['valid'] ? 'Parašas galiojantis' : 'Parašas NEGALIOJANTIS' ?></div>
                <div class="sub"><?= $manualResult['valid'] ? 'Maišos reikšmės sutampa.' : 'Maišos reikšmės nesutampa arba klaida.' ?></div>
            </div>
            <?php if ($manualResult['error']): ?>
                <div class="info-block warn"><strong>Klaida:</strong> <?= htmlspecialchars($manualResult['error']) ?></div>
            <?php endif; ?>
            <div class="field" style="margin-top:12px;">
                <label>Apskaičiuota SHA-256 maišos reikšmė</label>
                <div class="output-box <?= $manualResult['valid'] ? 'highlight-3' : 'highlight-2' ?>">
                    <?= htmlspecialchars($manualResult['computed_hash']) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
