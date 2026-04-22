<?php

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


$pageTitle  = 'Programa 2 – Tarpininkė';
$badgeClass = 'badge-2';
$badgeLabel = 'Programa 2';
$titleSub   = 'Tarpininkė';
$titleColor = 'var(--red)';
$activePage = '2';
$pageStyles = '<style>
    textarea.modified { border-color: var(--red) !important; }
    .tamper-hint { font-size: .75rem; color: var(--red); margin-top: 4px; }
</style>';
$pageScripts = '<script>
    const origSig = ' . json_encode($data['signature'] ?? '') . ';

    function checkChange() {
        const val   = document.getElementById("sigField").value;
        const hint  = document.getElementById("tampHint");
        const field = document.getElementById("sigField");
        const changed = val !== origSig;
        hint.style.display = changed ? "block" : "none";
        field.classList.toggle("modified", changed);
    }

    function tamperSignature() {
        const f = document.getElementById("sigField");
        let s = f.value;
        f.value = (s.charAt(0) === "A" ? "B" : "A") + s.slice(1, -4) + "XXXX";
        checkChange();
    }

    function resetSignature() {
        document.getElementById("sigField").value = origSig;
        checkChange();
    }
</script>';

require __DIR__ . '/views/layout.php';
require __DIR__ . '/views/program2.php';
require __DIR__ . '/views/footer.php';