<?php

namespace App\Services;

class VapidKeyGenerator
{
    public function generate(): array
    {
        if (!extension_loaded('openssl')) {
            throw new \RuntimeException('L’extension OpenSSL doit être activée dans le PHP en ligne de commande.');
        }

        $key = $this->createKey(null);
        if ($key === false) {
            // WAMP may point to a missing default openssl.cnf. Use an explicit file.
            while (openssl_error_string() !== false) {}
            $key = $this->createKey(resource_path('openssl.cnf'));
        }
        if ($key === false) {
            throw new \RuntimeException('OpenSSL ne peut pas créer une clé P-256, même avec la configuration du projet. Vérifier l’installation OpenSSL du PHP CLI de WAMP.');
        }

        $details = openssl_pkey_get_details($key);
        $ec = $details['ec'] ?? [];
        foreach (['x', 'y', 'd'] as $part) {
            if (empty($ec[$part]) || strlen($ec[$part]) > 32) {
                throw new \RuntimeException('OpenSSL a retourné une clé P-256 incomplète.');
            }
            $ec[$part] = str_pad($ec[$part], 32, "\0", STR_PAD_LEFT);
        }

        // Web Push uses the uncompressed 65-byte public point and 32-byte scalar.
        return [
            'publicKey' => $this->encode("\x04".$ec['x'].$ec['y']),
            'privateKey' => $this->encode($ec['d']),
        ];
    }

    protected function createKey(?string $config)
    {
        // PHP validates this generic setting even for EC keys on some builds.
        // The actual EC key size is determined by prime256v1 (256 bits).
        $options = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1', 'private_key_bits' => 2048];
        if ($config !== null) $options['config'] = $config;
        // Handle a native OpenSSL warning locally so Laravel cannot interrupt fallback.
        set_error_handler(static fn () => true, E_WARNING);
        try {
            return openssl_pkey_new($options);
        } finally {
            restore_error_handler();
        }
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
