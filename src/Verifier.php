<?php

namespace Rsa;

/**
 * Verifier – RSA parašo tikrinimas viešuoju raktu.
 *
 * Proceso žingsniai:
 *  1. Parašas iššifruojamas viešuoju raktu → pradinė maiša.
 *  2. Nepriklausomai apskaičiuojama gauto pranešimo SHA-256 maiša.
 *  3. Jei maišos sutampa → parašas galiojantis, priešingu atveju – negaliojantis.
 */
class Verifier
{
    /**
     * Tikrina parašą ir grąžina tikrinimo rezultatą.
     *
     * @return array{valid: bool, computed_hash: string, error: string|null}
     */
    public static function verify(string $message, string $signatureBase64, string $publicKeyPem): array
    {
        $computedHash = hash(HASH_ALGO, $message);

        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if (!$publicKey) {
            return [
                'valid'         => false,
                'computed_hash' => $computedHash,
                'error'         => 'Nepavyko įkelti viešojo rakto: ' . openssl_error_string(),
            ];
        }

        $rawSignature = base64_decode($signatureBase64, true);
        if ($rawSignature === false) {
            return [
                'valid'         => false,
                'computed_hash' => $computedHash,
                'error'         => 'Parašas – netinkamas Base64 formatas',
            ];
        }

        $result = openssl_verify($message, $rawSignature, $publicKey, 'sha256WithRSAEncryption');

        return [
            'valid'         => ($result === 1),
            'computed_hash' => $computedHash,
            'error'         => $result === -1 ? 'openssl_verify klaida: ' . openssl_error_string() : null,
        ];
    }
}