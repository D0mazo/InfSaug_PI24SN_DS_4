<?php
// ─────────────────────────────────────────────
//  Bendra konfigūracija + autoloaderis
// ─────────────────────────────────────────────

define('PROG2_HOST',   '127.0.0.1');
define('PROG2_PORT',   9001);
define('LISTEN_PORT2', 9001);

define('PROG3_HOST',   '127.0.0.1');
define('PROG3_PORT',   9002);
define('LISTEN_PORT3', 9002);

define('PROG2_DATA_FILE',   __DIR__ . '/prog2_data.json');
define('PROG3_RESULT_FILE', __DIR__ . '/prog3_result.json');

define('RSA_KEY_BITS', 2048);
define('RSA_ALGO', 4); // OPENSSL_ALGO_SHA256 = 4
define('HASH_ALGO',    'sha256');

// Autoloaderis: Rsa\ClassName → src/ClassName.php
spl_autoload_register(function (string $class): void {
    $prefix  = 'Rsa\\';
    $baseDir = __DIR__ . '/src/';
    if (!str_starts_with($class, $prefix)) return;
    $file = $baseDir . substr($class, strlen($prefix)) . '.php';
    if (file_exists($file)) require $file;
});