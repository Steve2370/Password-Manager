<?php

namespace Models\Inventory\Services;;

use Models\Inventory\Entities\User;
use Zephyrus\Core\Session;

use PDO;
use PDOException;
use Zephyrus\Database\DatabaseBroker;

class UserService extends DatabaseBroker
{
    private EncryptionService $encryptionService;
    private $db;

    public function __construct(?EncryptionService $encryptionService = null)
    {
        parent::__construct();

        if ($encryptionService === null) {
            $masterKey = $_SESSION['master_key'] ?? '';
            $this->encryptionService = new EncryptionService($masterKey);
        } else {
            $this->encryptionService = $encryptionService;
        }

        $this->db = DatabaseService::getInstance();
    }

    public function login(string $username, string $password): ?array
    {
        try {
            $result = $this->db->query(
                "SELECT * FROM users WHERE username = ?",
                [$username]
            );

            $user = $this->db->fetch($result);

            if (!$user) {
                return null;
            }

            if (!password_verify($password, $user['password_hash'])) {
                return null;
            }

            Session::set('user_id', $user['id']);

            return $user;
        } catch (PDOException $e) {
            error_log("Erreur de login : " . $e->getMessage());
            return null;
        }
    }

    public function createUser(string $username, string $email, string $password, string $masterKey): int|bool
    {
        $existing = $this->selectSingle(
            "SELECT id FROM users WHERE username = ? OR email = ?",
            [$username, $email]
        );

        if ($existing) {
            error_log("Utilisateur déjà existant : $username / $email");
            return false;
        }

        $keys = $this->encryptionService->generateRSAKeyPair();
        $privateEncrypted = $this->encryptionService->encrypt($keys['private']);

        $user = new \Models\Inventory\Entities\User();
        $user->username = $username;
        $user->email = $email;
        $user->password = $password;
        $user->publicKey = $keys['public'];
        $user->encryptedPrivateKey = $privateEncrypted['data'];
        $user->privateKeyIv = $privateEncrypted['iv'];
        $user->encryptionSalt = bin2hex(random_bytes(16));

        try {
            $broker = new \Models\Inventory\Brokers\UserBroker();
            $userId = $broker->insert($user);

            if (!$userId) {
                error_log("Échec de l'insertion de l'utilisateur : $username");
                return false;
            }
            return $userId;
        } catch (\Throwable $e) {
            error_log("Exception lors de la création de l'utilisateur : " . $e->getMessage());
            return false;
        }
    }



    public function getUserById(int $userId): ?User
    {
        $result = $this->db->query(
            "SELECT * FROM users WHERE id = ?",
            [$userId]
        );

        $data = $this->db->fetch($result);
        if (empty($data)) {
            return null;
        }

        return $this->createUserFromData($data);
    }

    public function getUserByUsername(string $username): ?User
    {
        $result = $this->db->query(
            "SELECT * FROM users WHERE username = ?",
            [$username]
        );

        $data = $this->db->fetch($result);
        if (empty($data)) {
            return null;
        }

        return $this->createUserFromData($data);
    }

    public function getUserByEmail(string $email): ?User
    {
        $result = $this->db->query(
            "SELECT * FROM users WHERE email = ?",
            [$email]
        );

        $data = $this->db->fetch($result);
        if (empty($data)) {
            return null;
        }

        return $this->createUserFromData($data);
    }

    public function getUserPublicKey(int $userId): ?string
    {
        $result = $this->db->query(
            "SELECT public_key FROM user_keys WHERE user_id = ?",
            [$userId]
        );

        $data = $this->db->fetch($result);
        if (empty($data)) {
            return null;
        }

        return $data['public_key'];
    }

    public function getUserPrivateKey(int $userId, string $masterKey): ?string
    {
        $result = $this->db->query(
            "SELECT encrypted_private_key, private_key_iv 
         FROM user_keys 
         WHERE user_id = ?",
            [$userId]
        );

        $data = $this->db->fetch($result);
        if (empty($data)) {
            return null;
        }

        $encryptedKey = $data['encrypted_private_key'];
        $iv = $data['private_key_iv'];

        try {
            $encryptionService = new EncryptionService($masterKey);
            return $encryptionService->decrypt($encryptedKey, $iv);
        } catch (\Exception $e) {
            error_log("Erreur de déchiffrement de la clé privée pour user_id={$userId}: " . $e->getMessage());
            return null;
        }
    }


    public function authenticateUser(string $username, string $password): ?User
    {
        $user = $this->getUserByUsername($username);

        if ($user === null) {
            return null;
        }

        return null;
    }

    public function changePassword(
        int $userId,
        string $currentPassword,
        string $newPassword,
        string $masterKey
    ): bool {
        $user = $this->getUserById($userId);

        if ($user === null) {
            return false;
        }

        if (!password_verify($currentPassword, $user->passwordHash)) {
            return false;
        }

        $privateKey = $this->getUserPrivateKey($userId, $masterKey);

        if ($privateKey === null) {
            return false;
        }

        $newPasswordHash = password_hash($newPassword, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 2,
        ]);

        $stmt = $this->db->query(
            "UPDATE users SET password_hash = ? WHERE id = ?",
            [$newPasswordHash, $userId]
        );

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }

    public function changeMasterKey(
        int $userId,
        string $currentMasterKey,
        string $newMasterKey
    ): bool {
        try {
            $privateKey = $this->getUserPrivateKey($userId, $currentMasterKey);

            if ($privateKey === null) {
                return false;
            }

            $newIv = $this->encryptionService->generateIv();

            $newEncryptionService = new EncryptionService($newMasterKey);

            $newEncryptedPrivateKey = $newEncryptionService->encrypt($privateKey);

            $this->db->beginTransaction();

            $result = $this->db->query(
                "UPDATE user_keys SET encrypted_private_key = ?, private_key_iv = ? WHERE user_id = ?",
                [$newEncryptedPrivateKey, $newIv, $userId]
            );

            if ($result->rowCount() == 0) {
                $this->db->rollback();
                return false;
            }

            $encryptionSalt = bin2hex(random_bytes(16));
            $this->db->query(
                "UPDATE encryption_settings 
                 SET encryption_salt = ?, encryption_iterations = ?, updated_at = ? 
                 WHERE user_id = ?",
                [$encryptionSalt, 10000, date('Y-m-d H:i:s'), $userId]
            );

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            if ($this->db->getPdo()->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Exception lors du changement de clé maître: " . $e->getMessage());
            return false;
        }
    }

    private function createUserFromData(array $data): User
    {
        $user = new User();
        $user->id = $data['id'];
        $user->username = $data['username'];
        $user-> email = $data['email'];
        $user->passwordHash = $data['password_hash'];
        $user->createdAt = new \DateTime($data['created_at']);
        return $user;
    }
}