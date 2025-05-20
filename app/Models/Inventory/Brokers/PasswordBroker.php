<?php

namespace Models\Inventory\Brokers;

use Models\Inventory\Entities\PasswordEntry;
use Models\Inventory\Services\DatabaseEncrytionService;
use Zephyrus\Database\DatabaseBroker;

class PasswordBroker extends DatabaseBroker
{
    private DatabaseEncrytionService $encryptionService;

    public function __construct(DatabaseEncrytionService $encryptionService)
    {
        parent::__construct();
        $this->encryptionService = $encryptionService;
    }

    public function findById(int $id, int $userId): ?PasswordEntry
    {
        $row = $this->selectSingle(
            "SELECT * FROM password_entries WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );

        if (!$row) {
            return null;
        }

        $fieldsToDecrypt = ['service_password', 'service_username', 'notes'];
        $decrypted = $this->encryptionService->decryptDatabaseRow((array)$row, $fieldsToDecrypt);

        return PasswordEntry::fromArray($decrypted);
    }


    public function findAllForUser(int $userId): array
    {
        $rows = $this->select(
            "SELECT * FROM password_entries WHERE user_id = ? ORDER BY service_name ASC",
            [$userId]
        );

        $fieldsToDecrypt = ['service_password', 'service_username', 'notes'];

        return array_map(function ($row) use ($fieldsToDecrypt) {
            $decrypted = $this->encryptionService->decryptDatabaseRow((array)$row, $fieldsToDecrypt);
            return PasswordEntry::fromArray($decrypted);
        }, $rows);
    }


    public function insert(PasswordEntry $entry): int
    {
        $data = $entry->toArray();
        unset($data['id']);

        $fieldsToEncrypt = ['service_password', 'service_username', 'notes'];
        $data = $this->encryptionService->encryptDatabaseRow($data, $fieldsToEncrypt);

        $requiredKeys = [
            'user_id', 'service_name', 'service_username', 'service_username_iv',
            'service_password', 'service_password_iv', 'shared_by', 'url', 'url_iv',
            'notes', 'notes_iv', 'category'
        ];

        $orderedData = [];
        foreach ($requiredKeys as $key) {
            $orderedData[$key] = $data[$key] ?? null;
        }

        $columns = implode(', ', array_keys($orderedData));
        $placeholders = implode(', ', array_fill(0, count($orderedData), '?'));

        $result = $this->query("INSERT INTO password_entries ($columns) VALUES ($placeholders) RETURNING id", array_values($orderedData));

        return $result ? $result->id : $this->getLastAffectedCount();
    }


    public function update(PasswordEntry $passwordEntry): void
    {
        $data = $passwordEntry->toArray();
        $id = $data['id'];
        unset($data['id']);

        $fieldsToEncrypt = ['service_password', 'service_username', 'notes'];
        $encryptedData = $this->encryptionService->encryptDatabaseRow($data, $fieldsToEncrypt);

        $setClause = '';
        $params = [];
        $i = 1;

        foreach ($encryptedData as $key => $value) {
            $setClause .= ($setClause ? ', ' : '') . "$key = $$i";
            $params[] = $value;
            $i++;
        }

        $params[] = $id;

        $query = "UPDATE password_entries SET $setClause WHERE id = $$i";

        $this->query($query, $params);
    }

    public function delete(int $id, int $userId): void
    {
        $this->query(
            "DELETE FROM password_entries WHERE id = $1 AND user_id = $2",
            [$id, $userId]
        );
    }

    public function searchPasswords(int $userId, string $keyword): array
    {
        $result = $this->select(
            "SELECT * FROM password_entries 
            WHERE user_id = $1 
            AND (service_name ILIKE $2 OR url ILIKE $2 OR category ILIKE $2)
            ORDER BY service_name",
            [$userId, "%$keyword%"]
        );

        $passwords = [];
        $fieldsToDecrypt = ['service_password', 'service_username', 'notes'];

        foreach ($result as $row) {
            $decryptedData = $this->encryptionService->decryptDatabaseRow((array) $row, $fieldsToDecrypt);
            $passwords[] = PasswordEntry::fromArray($decryptedData);
        }

        return $passwords;
    }

    public function getPasswordsByCategory(int $userId, string $category): array
    {
        $result = $this->select(
            "SELECT * FROM password_entries 
            WHERE user_id = $1 AND category = $2
            ORDER BY service_name",
            [$userId, $category]
        );

        $passwords = [];
        $fieldsToDecrypt = ['service_password', 'service_username', 'notes'];

        foreach ($result as $row) {
            $decryptedData = $this->encryptionService->decryptDatabaseRow((array) $row, $fieldsToDecrypt);
            $passwords[] = PasswordEntry::fromArray($decryptedData);
        }

        return $passwords;
    }

    public function getAllCategories(int $userId): array
    {
        $result = $this->select(
            "SELECT DISTINCT category FROM password_entries 
            WHERE user_id = $1 AND category IS NOT NULL AND category <> ''
            ORDER BY category",
            [$userId]
        );

        return array_column(array_map(fn($row) => (array) $row, $result), 'category');
    }

    public function sharePassword(int $passwordId, int $fromUserId, int $toUserId): int
    {
        $result = $this->selectSingle(
            "SELECT * FROM password_entries WHERE id = $1 AND user_id = $2",
            [$passwordId, $fromUserId]
        );

        if (empty($result)) {
            throw new \Exception("Le mot de passe n'appartient pas à cet utilisateur.");
        }

        $result = (array) $result;

        try {
            $shareResult = $this->query(
                "INSERT INTO password_shares (password_id, from_user_id, to_user_id) 
                VALUES ($1, $2, $3) RETURNING id",
                [$passwordId, $fromUserId, $toUserId]
            );

            $shareId = $shareResult->id;

            $originalPassword['user_id'] = $toUserId;
            $originalPassword['shared_by'] = $fromUserId;
            unset($originalPassword['id']);

            $columns = implode(', ', array_keys($originalPassword));
            $placeholders = implode(', ', array_fill(0, count($originalPassword), '?'));

            $this->rawQuery(
                "INSERT INTO password_entries ($columns) VALUES ($placeholders)",
                array_values($originalPassword)
            );

            $this->getDatabase()->commit();
            return $shareId;
        } catch (\Exception $e) {
            $this->getDatabase()->rollback();
            throw $e;
        }
    }

    public function getSharedPasswords(int $userId): array
    {
        $result = $this->select(
            "SELECT pe.*, u.username as shared_by_username
            FROM password_entries pe
            JOIN users u ON pe.shared_by = u.id
            WHERE pe.user_id = $1 AND pe.shared_by IS NOT NULL
            ORDER BY pe.service_name",
            [$userId]
        );

        $passwords = [];
        $fieldsToDecrypt = ['service_password', 'service_username', 'notes'];

        foreach ($result as $row) {
            $decryptedData = $this->encryptionService->decryptDatabaseRow((array)$row, $fieldsToDecrypt);
            $password = PasswordEntry::fromArray($decryptedData);
            $passwords[] = [
                'password' => $password,
                'shared_by_username' => $row['shared_by_username']
            ];
        }

        return $passwords;
    }

    public function savePasswordHistory(int $passwordId, string $oldPassword, string $oldPasswordIv, int $userId): int
    {
        $result = $this->query(
            "INSERT INTO password_history (password_id, old_service_password, old_service_password_iv, changed_by_user_id)
            VALUES ($1, $2, $3, $4) RETURNING id",
            [$passwordId, $oldPassword, $oldPasswordIv, $userId]
        );

        return $result['id'];
    }

    public function getPasswordHistory(int $passwordId, int $userId): array
    {
        $passwordResult = $this->selectSingle(
            "SELECT id FROM password_entries WHERE id = $1 AND user_id = $2",
            [$passwordId, $userId]
        );

        if (!$passwordResult) {
            return [];
        }

        $result = $this->select(
            "SELECT ph.*, u.username as changed_by_username
            FROM password_history ph
            JOIN users u ON ph.changed_by_user_id = u.id
            WHERE ph.password_id = $1
            ORDER BY ph.changed_at DESC",
            [$passwordId]
        );

        $history = [];
        foreach ($result as $row) {

            $rowArray = (array) $row;

            $decryptedPassword = $this->encryptionService->decryptDatabaseRow(
                $rowArray['old_service_password'],
                $rowArray['old_service_password_iv']
            );

            $history[] = [
                'id' => $row['id'],
                'password_id' => $row['password_id'],
                'old_password' => $decryptedPassword,
                'changed_by' => $row['changed_by_username'],
                'changed_at' => $row['changed_at']
            ];
        }

        return $history;
    }
}