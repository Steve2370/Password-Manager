<?php

namespace Models\Inventory\Entities;

class User
{
    public int $id;
    public string $username;
    public string $email;
    public string $passwordHash;
    public \DateTime $createdAt;

    public string $password;
    public string $publicKey;
    public string $encryptedPrivateKey;
    public string $privateKeyIv;
    public string $encryptionSalt;

    public function getId(): int { return $this->id; }
    public function setId(int $id): self { $this->id = $id; return $this; }

    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): self { $this->username = $username; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getPasswordHash(): string { return $this->passwordHash; }
    public function setPasswordHash(string $passwordHash): self { $this->passwordHash = $passwordHash; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'password_hash' => $this->passwordHash,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s')
        ];
    }

    public static function fromArray(array $data): self
    {
        $user = new self();
        $user->id = $data['id'] ?? 0;
        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->passwordHash = $data['password_hash'];
        $user->createdAt = new \DateTime($data['created_at'] ?? 'now');
        return $user;
    }
}
