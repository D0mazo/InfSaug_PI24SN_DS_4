<?php
// ═══════════════════════════════════════════════════════════════
//  PROGRAMA 1 – Pranešimo pasirašymas ir siuntimas (3 lygis)
//  Generuojame RSA raktų porą, pasirašome pranešimą SHA-256 maiša,
//  siunčiame JSON duomenis į Programą 2 per socket (TCP).
// ═══════════════════════════════════════════════════════════════

// ── Konfigūracija ────────────────────────────────────────────
define('PROG2_HOST', '127.0.0.1');
define('PROG2_PORT', 9001);

// ── Pagalbinės funkcijos ─────────────────────────────────────

/**
 * Generuojame RSA raktų porą.
 * Grąžina ['private_key' => ..., 'public_key' => ..., 'public_key_pem' => ...]
 */
function generateRSAKeys(): array {
    $config = [
        'digest_alg'       => 'sha256',
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];
    $res = openssl_pkey_new($config);
    if (!$res) {
        throw new RuntimeException('Nepavyko sugeneruoti RSA rakto: ' . openssl_error_string());
    }
    openssl_pkey_export($res, $privateKeyPem);
    $details = openssl_pkey_get_details($res);
    return [
        'private_key'     => $res,
        'private_key_pem' => $privateKeyPem,
        'public_key_pem'  => $details['key'],
    ];
}

/**
 * Pasirašome pranešimą RSA-SHA256.
 * Grąžina Base64 užkoduotą parašą.
 */
function signMessage(string $message, $privateKey): string {
    $ok = openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    if (!$ok) {
        throw new RuntimeException('Pasirašymas nepavyko: ' . openssl_error_string());
    }
    return base64_encode($signature);
}

/**
 * Siunčiame JSON duomenis į Programą 2 per TCP socket.
 * Grąžina atsakymo eilutę arba klaidos pranešimą.
 */
function sendToProgram2(array $payload): string {
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!$sock) {
        return 'KLAIDA: Nepavyko sukurti socket – ' . socket_strerror(socket_last_error());
    }

    $connected = @socket_connect($sock, PROG2_HOST, PROG2_PORT);
    if (!$connected) {
        socket_close($sock);
        return 'KLAIDA: Nepavyko prisijungti prie Programos 2 ('. PROG2_HOST .':'. PROG2_PORT .'). '
            . 'Įsitikinkite, kad program2.php veikia. – ' . socket_strerror(socket_last_error());
    }

    socket_write($sock, $json . "\n", strlen($json) + 1);

    // Laukiame atsakymo (max ~3 s)
    $response = '';
    socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 3, 'usec' => 0]);
    while ($chunk = @socket_read($sock, 4096)) {
        $response .= $chunk;
        if (str_contains($response, "\n")) break;
    }

    socket_close($sock);
    return trim($response) ?: '(Programa 2 negrąžino atsakymo)';
}

// ── Sesijai saugome sugeneruotus raktus ─────────────────────
session_start();

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    if ($message === '') {
        $result = ['error' => 'Pranešimas negali būti tuščias.'];
    } else {
        try {
            // 1. Generuojame raktus
            $keys = generateRSAKeys();

            // 2. Pasirašome
            $signature    = signMessage($message, $keys['private_key']);
            $sha256hash   = hash('sha256', $message);

            // 3. Formuojame JSON paketą
            $payload = [
                'message'         => $message,
                'signature'       => $signature,
                'public_key_pem'  => $keys['public_key_pem'],
            ];

            // 4. Siunčiame į Programą 2
            $sendResponse = sendToProgram2($payload);

            // 5. Išsaugome duomenis rezultatų atvaizdavimui
            $_SESSION['last_private_key'] = $keys['private_key_pem'];
            $_SESSION['last_public_key']  = $keys['public_key_pem'];

            $result = [
                'success'        => true,
                'message'        => $message,
                'sha256'         => $sha256hash,
                'signature'      => $signature,
                'public_key_pem' => $keys['public_key_pem'],
                'payload_json'   => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'send_response'  => $sendResponse,
            ];
        } catch (RuntimeException $e) {
            $result = ['error' => $e->getMessage()];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RSA Parašas – Programa 1</title>
    <link rel="stylesheet" href="Style.css">
</head>
<body>

<header class="site-header">
    <span class="prog-badge badge-1">Programa 1</span>
    <h1>RSA <span>Skaitmeninis Parašas</span></h1>
</header>

<nav class="nav-pills">
    <a href="program1.php" class="nav-pill active">① Pasirašymas</a>
    <a href="program2.php" class="nav-pill p2">② Tarpininkė</a>
    <a href="program3.php" class="nav-pill p3">③ Tikrinimas</a>
</nav>

<!-- ── Paaiškinimas ── -->
<div class="card">
    <div class="card-header"><span class="dot dot-1"></span>Kaip veikia skaitmeninis parašas?</div>
    <div class="card-body">
        <div class="row">
            <div class="col">
                <div class="info-block">
                    <strong>1. Maišos funkcija (SHA-256)</strong><br>
                    Iš pranešimo teksto apskaičiuojama fiksuoto ilgio „pirštų atspaudas" – maišos reikšmė.
                    Net mažiausias pranešimo pakeitimas visiškai keičia maišą.
                </div>
            </div>
            <div class="col">
                <div class="info-block">
                    <strong>2. Pasirašymas (privatus raktas)</strong><br>
                    Maišos reikšmė šifruojama siuntėjo <em>privačiuoju RSA raktu</em>.
                    Gautas šifrtekstas – tai skaitmeninis parašas.
                </div>
            </div>
            <div class="col">
                <div class="info-block">
                    <strong>3. Tikrinimas (viešas raktas)</strong><br>
                    Gavėjas iššifruoja parašą <em>viešuoju raktu</em>, gauna pradinę maišą ir lygina su
                    apskaičiuota pranešimo maiša. Sutampa → parašas galiojantis.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Forma ── -->
<div class="card">
    <div class="card-header"><span class="dot dot-1"></span>Pranešimo įvedimas ir pasirašymas</div>
    <div class="card-body">
        <form method="POST">
            <div class="field">
                <label for="message">Pranešimo tekstas</label>
                <textarea id="message" name="message" rows="4"
                          placeholder="Įveskite pranešimą kurį norite pasirašyti..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                &#9654; Pasirašyti ir siųsti į Programą 2
            </button>
        </form>

        <?php if ($result): ?>

            <?php if (isset($result['error'])): ?>
                <div class="status status-invalid" style="margin-top:16px;">
                    <span class="status-icon">✗</span> <?= htmlspecialchars($result['error']) ?>
                </div>

            <?php else: ?>
                <hr class="divider">

                <!-- SHA-256 maišos reikšmė -->
                <div class="field">
                    <label>SHA-256 maišos reikšmė (pranešimo „pirštų atspaudas")</label>
                    <div class="output-box highlight-1"><?= htmlspecialchars($result['sha256']) ?></div>
                </div>

                <!-- Parašas -->
                <div class="field">
                    <label>RSA skaitmeninis parašas (Base64)</label>
                    <div class="output-box highlight-1"><?= htmlspecialchars($result['signature']) ?></div>
                </div>

                <!-- Viešasis raktas -->
                <div class="field">
                    <label>Viešasis RSA raktas (PEM)</label>
                    <div class="output-box" style="max-height:120px;overflow-y:auto;"><?= htmlspecialchars($result['public_key_pem']) ?></div>
                </div>

                <hr class="divider">
                <div class="step-arrow">↓ Siunčiama į Programą 2 per TCP socket (port <?= PROG2_PORT ?>) ↓</div>

                <!-- JSON paketas -->
                <div class="field">
                    <label>Išsiųstas JSON paketas</label>
                    <div class="json-preview"><?= htmlspecialchars($result['payload_json']) ?></div>
                </div>

                <!-- Atsakymas iš Prog. 2 -->
                <div class="field">
                    <label>Atsakymas iš Programos 2</label>
                    <div class="output-box <?= str_starts_with($result['send_response'], 'KLAIDA') ? 'highlight-2' : 'highlight-3' ?>">
                        <?= htmlspecialchars($result['send_response']) ?>
                    </div>
                </div>

                <?php if (str_starts_with($result['send_response'], 'KLAIDA')): ?>
                    <div class="info-block warn">
                        <strong>Kaip paleisti Programą 2?</strong><br>
                        Atidarykite terminalą ir vykdykite:<br>
                        <code>php program2.php</code><br>
                        Programa 2 turi veikti kaip serveris prieš siunčiant duomenis.
                    </div>
                <?php else: ?>
                    <div class="status status-valid">
                        <span class="status-icon">✓</span>
                        Duomenys sėkmingai perduoti į Programą 2
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ── Proceso schema ── -->
<div class="card">
    <div class="card-header"><span class="dot dot-1"></span>3 programų proceso schema</div>
    <div class="card-body">
        <div class="info-block">
            <strong>Programa 1</strong> → [pranešimas + parašas + viešas raktas] →
            <strong>Programa 2</strong> (tarpininkė, gali keisti parašą) →
            [duomenys] → <strong>Programa 3</strong> (tikrinimas).<br><br>
            Bet koks parašo ar pranešimo pakeitimas sukels tikrinimo nesėkmę Programoje 3,
            nes apskaičiuota maišos reikšmė nesutaps su iššifruota iš parašo.
        </div>
    </div>
</div>

</body>
</html>