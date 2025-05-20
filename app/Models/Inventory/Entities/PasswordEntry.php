<?php

namespace Models\Inventory\Entities;

class PasswordEntry
{
    public int $id;
    private int $userId;
    public string $serviceName;
    public string $serviceUsername;
    private string $serviceUsernameIv;
    public string $servicePassword;
    private string $servicePasswordIv;
    private ?int $sharedBy = null;
    public string $url = '';
    private string $urlIv = '';
    public string $notes = '';
    private string $notesIv = '';
    public string $category = '';
    private \DateTime $createdAt;
    public \DateTime $updatedAt;

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }



    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'service_name' => $this->serviceName,
            'service_username' => $this->serviceUsername,
            'service_username_iv' => $this->serviceUsernameIv,
            'service_password' => $this->servicePassword,
            'service_password_iv' => $this->servicePasswordIv,
            'shared_by' => $this->sharedBy,
            'url' => $this->url,
            'url_iv' => $this->urlIv,
            'notes' => $this->notes,
            'notes_iv' => $this->notesIv,
            'category' => $this->category,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s')
        ];
    }

    public static function fromArray(array $data): self
    {
        $password = new self();
        $password->id = $data['id'] ?? 0;
        $password->userId = $data['user_id'];
        $password->serviceName = $data['service_name'];
        $password->serviceUsername = (string) $data['service_username'];
        $password->serviceUsernameIv = $data['service_username_iv'] ?? '';
        $password->servicePassword = (string) $data['service_password'];
        $password->servicePasswordIv = $data['service_password_iv'] ?? '';
        $password->sharedBy = $data['shared_by'] ?? null;
        $password->url = $data['url'] ?? '';
        $password->urlIv = $data['url_iv'] ?? '';
        $password->notes = (string) $data['notes'] ?? '';
        $password->notesIv = $data['notes_iv'] ?? '';
        $password->category = $data['category'] ?? '';
        $password->createdAt = new \DateTime($data['created_at'] ?? 'now');
        $password->updatedAt = new \DateTime($data['updated_at'] ?? 'now');

        return $password;
    }
}