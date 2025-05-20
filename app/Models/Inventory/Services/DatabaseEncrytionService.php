<?php

namespace Models\Inventory\Services;

class DatabaseEncrytionService
{
    private EncryptionService $encryptionService;

    public function __construct(EncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    public function encryptDatabaseRow(array $data, array $fieldsToEncrypt): array
    {
        foreach ($fieldsToEncrypt as $field) {
            if (!empty($data[$field])) {
                $encrypted = $this->encryptionService->encrypt($data[$field]);
                $data[$field] = $encrypted['data'];
                $data[$field . '_iv'] = $encrypted['iv'];
            }
        }
        return $data;
    }

    public function decryptDatabaseRow(array $data, array $fieldsToDecrypt): array
    {
        foreach ($fieldsToDecrypt as $field) {
            if (isset($data[$field]) && isset($data[$field . '_iv']) && !empty($data[$field])) {
                $data[$field] = $this->encryptionService->decrypt(
                    $data[$field],
                    $data[$field . '_iv']
                );
            }
        }

        return $data;
    }
}