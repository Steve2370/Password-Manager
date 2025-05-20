<?php

namespace Models\Inventory\Entities;

class PasswordShare
{
    private int $id;
    private int $passwordId;
    private int $fromUserId;
    private int $toUserId;
    private \DateTime $sharedAt;

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

    public function getFromUserId(): int
    {
        return $this->fromUserId;
    }

    public function setFromUserId(int $fromUserId): self
    {
        $this->fromUserId = $fromUserId;
        return $this;
    }

    public function getToUserId(): int
    {
        return $this->toUserId;
    }

    public function setToUserId(int $toUserId): self
    {
        $this->toUserId = $toUserId;
        return $this;
    }

    public function getSharedAt(): \DateTime
    {
        return $this->sharedAt;
    }

    public function setSharedAt(\DateTime $sharedAt): self
    {
        $this->sharedAt = $sharedAt;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'password_id' => $this->passwordId,
            'from_user_id' => $this->fromUserId,
            'to_user_id' => $this->toUserId,
            'shared_at' => $this->sharedAt->format('Y-m-d H:i:s')
        ];
    }

    public static function fromArray(array $data): self
    {
        $share = new self();
        $share->id = $data['id'] ?? 0;
        $share->passwordId = $data['password_id'];
        $share->fromUserId = $data['from_user_id'];
        $share->toUserId = $data['to_user_id'];
        $share->sharedAt = new \DateTime($data['shared_at'] ?? 'now');

        return $share;
    }
}