<?php

namespace Models\Inventory\Services;

class EncryptionService
{
    private string $cipher = 'aes-256-cbc';
    private string $encryptionKey;

    public function __construct(?string $key = null)
    {
        $rawKey = $key ?? getenv('ENCRYPTION_KEY') ?? '';
        $this->encryptionKey = $this->deriveKey($rawKey);
    }

    private function deriveKey(string $key): string
    {
        return substr(hash('sha256', $key, true), 0, 32);
    }

    public function generateRSAKeyPair(): array
    {
        $config = [
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);

        if (!$res) {
            throw new \Exception("Erreur lors de la génération de la clé RSA");
        }

        openssl_pkey_export($res, $privateKey);
        $publicKeyDetails = openssl_pkey_get_details($res);

        return [
            'private' => $privateKey,
            'public' => $publicKeyDetails['key']
        ];
    }


    public function encrypt(string $data): array
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));
        $encrypted = openssl_encrypt($data, $this->cipher, $this->encryptionKey, 0, $iv);

        if ($encrypted === false) {
            throw new \Exception("Échec du chiffrement : " . openssl_error_string());
        }

        return [
            'data' => base64_encode($encrypted),
            'iv' => base64_encode($iv)
        ];
    }

    public function decrypt(string $encryptedData, string $iv): string
    {
        try {
            $decodedData = base64_decode($encryptedData);
            $decodedIv = base64_decode($iv);

            $decrypted = openssl_decrypt($decodedData, $this->cipher, $this->encryptionKey, 0, $decodedIv);

            if ($decrypted === false) {
                error_log("Déchiffrement échoué — données retournées brutes");
                return $encryptedData;
            }

            return $decrypted;
        } catch (\Throwable $e) {
            error_log("Exception au déchiffrement : " . $e->getMessage());
            return $encryptedData;
        }
    }


    public function encryptDatabaseRow(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (!empty($data[$field])) {
                $encrypted = $this->encrypt($data[$field]);
                $data[$field] = $encrypted['data'];
                $data[$field . '_iv'] = $encrypted['iv'];
            }
        }
        return $data;
    }

    public function decryptDatabaseRow(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (!empty($data[$field]) && !empty($data[$field . '_iv'])) {
                try {
                    $data[$field] = $this->decrypt($data[$field], $data[$field . '_iv']);
                } catch (\Exception $e) {
                    error_log("Erreur déchiffrement [$field] : " . $e->getMessage());
                }
            }

        }
        return $data;
    }
}
