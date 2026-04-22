<?php

namespace Rsa;

/**
 * RsaKeys – RSA raktų poros generavimas.
 */
class RsaKeys
{
    private \OpenSSLAsymmetricKey $privateKey;
    private string $privateKeyPem;
    private string $publicKeyPem;

    public function __construct()
    {
        $config = [
            'digest_alg'       => 'sha256',
            'private_key_bits' => RSA_KEY_BITS,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);
        if (!$res) {
            throw new \RuntimeException('Raktų generavimas nepavyko: ' . openssl_error_string());
        }

        openssl_pkey_export($res, $pem);
        $details = openssl_pkey_get_details($res);

        $this->privateKey    = $res;
        $this->privateKeyPem = $pem;
        $this->publicKeyPem  = $details['key'];
    }

    public function getPrivateKey(): \OpenSSLAsymmetricKey
    {
        return $this->privateKey;
    }

    public function getPrivateKeyPem(): string
    {
        return $this->privateKeyPem;
    }

    public function getPublicKeyPem(): string
    {
        return $this->publicKeyPem;
    }
}