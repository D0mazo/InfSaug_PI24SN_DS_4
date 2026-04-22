<?php

namespace Rsa;

/**
 * Signer – pranešimo pasirašymas RSA privačiuoju raktu.
 *
 * Proceso žingsniai:
 *  1. Iš pranešimo apskaičiuojama SHA-256 maiša.
 *  2. Maiša šifruojama privačiuoju RSA raktu → skaitmeninis parašas.
 *  3. Parašas grąžinamas Base64 formatu.
 */
class Signer
{
    /**
     * Pasirašo pranešimą ir grąžina Base64 parašą bei SHA-256 maišą.
     *
     * @return array{signature: string, hash: string}
     */
    public static function sign(string $message, \OpenSSLAsymmetricKey $privateKey): array
    {
        $ok = openssl_sign($message, $rawSignature, $privateKey, RSA_ALGO);

        if (!$ok) {
            throw new \RuntimeException('Pasirašymas nepavyko: ' . openssl_error_string());
        }

        return [
            'signature' => base64_encode($rawSignature),
            'hash'      => hash(HASH_ALGO, $message),
        ];
    }
}