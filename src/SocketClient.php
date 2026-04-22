<?php

namespace Rsa;

/**
 * SocketClient – JSON duomenų siuntimas per TCP socket.
 * Naudoja stream_socket_client (veikia be php_sockets plėtinio).
 */
class SocketClient
{
    private string $host;
    private int    $port;
    private int    $timeout;

    public function __construct(string $host, int $port, int $timeout = 5)
    {
        $this->host    = $host;
        $this->port    = $port;
        $this->timeout = $timeout;
    }

    /**
     * Siunčia masyvą kaip JSON eilutę ir grąžina serverio atsakymą.
     */
    public function send(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $sock = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout
        );

        if (!$sock) {
            return "KLAIDA: Nepavyko prisijungti prie {$this->host}:{$this->port} – $errstr ($errno)";
        }

        stream_set_timeout($sock, $this->timeout);
        fwrite($sock, $json . "\n");

        $response = '';
        while (!feof($sock)) {
            $line = fgets($sock, 4096);
            if ($line === false) break;
            $response .= $line;
            if (str_contains($response, "\n")) break;
        }

        fclose($sock);
        return trim($response) ?: '(Serveris negrąžino atsakymo)';
    }
}