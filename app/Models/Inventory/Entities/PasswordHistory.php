<?php

namespace Models\Inventory\Entities;

class PasswordHistory
{
    private int $id;
    private int $passwordId;
    private string $oldServicePassword;
    private string $oldServicePasswordIv;
    private int $changedByUserId;
    private \DateTime $changedAt;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getPasswordId(): int
    {
        return $this->passwordId;
    }

    public function setPasswordId(int $passwordId): self
    {
        $this->passwordId = $passwordId;
        return $this;
    }

    public function getOldServicePassword(): string
    {
        return $this->oldServicePassword;
    }

    public function setOldServicePassword(string $oldServicePassword): self
    {
        $this->oldServicePassword = $oldServicePassword;
        return $this;
    }

    public function getOldServicePasswordIv(): string
    {
        return $this->oldServicePasswordIv;
    }

    public function setOldServicePasswordIv(string $oldServicePasswordIv): self
    {
        $this->oldServicePasswordIv = $oldServicePasswordIv;
        return $this;
    }

    public function getChangedByUserId(): int
    {
        return $this->changedByUserId;
    }

    public function setChangedByUserId(int $changedByUserId): self
    {
        $this->changedByUserId = $changedByUserId;
        return $this;
    }

    public function getChangedAt(): \DateTime
    {
        return $this->changedAt;
    }

    public function setChangedAt(\DateTime $changedAt): self
    {
        $this->changedAt = $changedAt;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'password_id' => $this->passwordId,
            'old_service_password' => $this->oldServicePassword,
            'old_service_password_iv' => $this->oldServicePasswordIv,
            'changed_by_user_id' => $this->changedByUserId,
            'changed_at' => $this->changedAt->format('Y-m-d H:i:s')
        ];
    }

    public static function fromArray(array $data): self
    {
        $history = new self();
        $history->id = $data['id'] ?? 0;
        $history->passwordId = $data['password_id'];
        $history->oldServicePassword = $data['old_service_password'];
        $history->oldServicePasswordIv = $data['old_service_password_iv'] ?? '';
        $history->changedByUserId = $data['changed_by_user_id'];
        $history->changedAt = new \DateTime($data['changed_at'] ?? 'now');

        return $history;
    }
}