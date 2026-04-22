<?php

require_once __DIR__ . '/config.php';

use Rsa\RsaKeys;
use Rsa\Signer;
use Rsa\SocketClient;

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');

    if ($message === '') {
        $result = ['error' => 'Pranešimas negali būti tuščias.'];
    } else {
        try {
            $keys    = new RsaKeys();
            $signed  = Signer::sign($message, $keys->getPrivateKey());

            $payload = [
                    'message'        => $message,
                    'signature'      => $signed['signature'],
                    'public_key_pem' => $keys->getPublicKeyPem(),
            ];

            $client   = new SocketClient(PROG2_HOST, PROG2_PORT);
            $response = $client->send($payload);

            $result = [
                    'success'      => true,
                    'message'      => $message,
                    'hash'         => $signed['hash'],
                    'signature'    => $signed['signature'],
                    'public_key'   => $keys->getPublicKeyPem(),
                    'payload_json' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    'response'     => $response,
            ];
        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage()];
        }
    }
}


$pageTitle  = 'Programa 1 – Pasirašymas';
$badgeClass = 'badge-1';
$badgeLabel = 'Programa 1';
$titleSub   = 'Skaitmeninis Parašas';
$titleColor = 'var(--blue)';
$activePage = '1';

require __DIR__ . '/views/layout.php';
require __DIR__ . '/views/program1.php';
require __DIR__ . '/views/footer.php';