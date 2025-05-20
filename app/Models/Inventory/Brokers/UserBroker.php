<?php

namespace Models\Inventory\Brokers;

use Models\Inventory\Entities\User;
use Zephyrus\Database\DatabaseBroker;

class UserBroker extends DatabaseBroker
{

    public function insert(User $user): int
    {
        try {
            $db = $this->getDatabase();
            $db->beginTransaction();

            $hashedPassword = password_hash($user->password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 2
            ]);

            $result = $this->selectSingle(
                "INSERT INTO users (username, email, password_hash, created_at) 
             VALUES (?, ?, ?, NOW()) RETURNING id",
                [
                    $user->username,
                    $user->email,
                    $hashedPassword
                ]
            );

            $userId = $result->id ?? null;
            if (!$userId) {
                throw new \Exception("Insertion utilisateur échouée");
            }

            $db->query(
                "INSERT INTO user_keys (user_id, public_key, encrypted_private_key, private_key_iv, created_at)
             VALUES (?, ?, ?, ?, NOW())",
                [
                    $userId,
                    $user->publicKey,
                    $user->encryptedPrivateKey,
                    $user->privateKeyIv
                ]
            );

            $db->query(
                "INSERT INTO encryption_settings (user_id, encryption_salt, encryption_iterations, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW())",
                [
                    $userId,
                    $user->encryptionSalt,
                    10000
                ]
            );

            $db->commit();
            return $userId;
        } catch (\Throwable $e) {
            $this->getDatabase()->rollback();
            error_log("Erreur insertion utilisateur : " . $e->getMessage());
            return 0;
        }
    }
}
