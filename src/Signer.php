<?php

namespace Rsa;


class Signer
{

    public static function sign(string $message, \OpenSSLAsymmetricKey $privateKey): array
    {
        $ok = openssl_sign($message, $rawSignature, $privateKey, 'sha256WithRSAEncryption');

        if (!$ok) {
            throw new \RuntimeException('Pasirašymas nepavyko: ' . openssl_error_string());
        }

        return [
            'signature' => base64_encode($rawSignature),
            'hash'      => hash(HASH_ALGO, $message),
        ];
    }
}