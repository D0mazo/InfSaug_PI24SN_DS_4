<?php

require_once __DIR__ . '/config.php';

use Rsa\Verifier;

// ── CLI: socket serveris ─────────────────────────────────────
if (PHP_SAPI === 'cli') {
    echo "═══════════════════════════════════════════\n";
    echo "  PROGRAMA 3 – Tikrinimo serveris\n";
    echo "  Klausosi : 0.0.0.0:" . LISTEN_PORT3 . "\n";
    echo "  Rezultatas: " . PROG3_RESULT_FILE . "\n";
    echo "═══════════════════════════════════════════\n\n";

    $server = stream_socket_server(
            'tcp://0.0.0.0:' . LISTEN_PORT3,
            $errno, $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
    );

    if (!$server) {
        echo "[x] Nepavyko paleisti serverio: $errstr ($errno)\n";
        exit(1);
    }

    echo "[OK] Laukiama duomenų iš Programos 2...\n";

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
            fwrite($client, "KLAIDA: Trūksta laukų\n");
            fclose($client);
            continue;
        }

        $check = Verifier::verify($data['message'], $data['signature'], $data['public_key_pem']);

        $result = [
                'timestamp'      => date('Y-m-d H:i:s'),
                'message'        => $data['message'],
                'signature'      => $data['signature'],
                'public_key_pem' => $data['public_key_pem'],
                'valid'          => $check['valid'],
                'computed_hash'  => $check['computed_hash'],
                'verify_error'   => $check['error'],
        ];

        file_put_contents(PROG3_RESULT_FILE, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $status = $check['valid'] ? 'GALIOJANTIS' : 'NEGALIOJANTIS';
        echo "[" . ($check['valid'] ? 'OK' : 'x') . "] Parašas: $status\n";
        echo "     Pranešimas : " . substr($data['message'], 0, 60) . "\n";
        echo "     SHA-256    : " . $check['computed_hash'] . "\n";
        if ($check['error']) echo "     Klaida     : " . $check['error'] . "\n";
        echo "[->] Atidarykite: http://localhost/program3.php\n\n";

        fwrite($client, "OK: $status\n");
        fclose($client);
    }

    fclose($server);
    exit;
}


$result    = null;
$dataError = null;

if (file_exists(PROG3_RESULT_FILE)) {
    $result = json_decode(file_get_contents(PROG3_RESULT_FILE), true);
    if (!$result) $dataError = 'Nepavyko perskaityti rezultato failo.';
} else {
    $dataError = 'Rezultato failas nerastas. Pasekite proceso žingsnius pradedant nuo Programos 1.';
}

$manualResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg    = trim($_POST['message']        ?? '');
    $sig    = trim($_POST['signature']      ?? '');
    $pubKey = trim($_POST['public_key_pem'] ?? '');
    if ($msg && $sig && $pubKey) {
        $check = Verifier::verify($msg, $sig, $pubKey);
        $manualResult = array_merge($check, ['message' => $msg, 'signature' => $sig]);
    }
}


$pageTitle  = 'Programa 3 – Tikrinimas';
$badgeClass = 'badge-3';
$badgeLabel = 'Programa 3';
$titleSub   = 'Tikrinimas';
$titleColor = 'var(--green)';
$activePage = '3';

require __DIR__ . '/views/layout.php';
require __DIR__ . '/views/program3.php';
require __DIR__ . '/views/footer.php';