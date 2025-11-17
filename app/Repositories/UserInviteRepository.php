<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\UserInvite;

final class UserInviteRepository extends Repository
{
    public function create(UserInvite $invite): void
    {
        $this->insert(
            'INSERT INTO user_invites (id, email, organization_id, inviter_user_id, invite_type, token, status, created_at, expires_at) VALUES (:id, :email, :organization_id, :inviter_user_id, :invite_type, :token, :status, :created_at, :expires_at)',
            [
                'id' => $invite->id,
                'email' => $invite->email,
                'organization_id' => $invite->organizationId,
                'inviter_user_id' => $invite->inviterUserId,
                'invite_type' => $invite->inviteType,
                'token' => $invite->token,
                'status' => $invite->status,
                'created_at' => $invite->createdAt,
                'expires_at' => $invite->expiresAt,
            ]
        );
    }

    public function findByToken(string $token): ?UserInvite
    {
        $record = $this->fetchOne(
            'SELECT * FROM user_invites WHERE token = :token LIMIT 1',
            ['token' => $token]
        );

        return $record ? UserInvite::fromArray($record) : null;
    }

    public function findById(string $id): ?UserInvite
    {
        $record = $this->fetchOne('SELECT * FROM user_invites WHERE id = :id LIMIT 1', ['id' => $id]);
        return $record ? UserInvite::fromArray($record) : null;
    }

    /**
     * @return array<int, UserInvite>
     */
    public function listPending(?string $search = null, int $limit = 25, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $sql = 'SELECT * FROM user_invites WHERE status = :status';
        $params = ['status' => 'pending'];

        if ($search !== null && $search !== '') {
            $sql .= ' AND email LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= sprintf(' ORDER BY created_at DESC LIMIT %d OFFSET %d', $limit, $offset);

        $records = $this->fetchAll($sql, $params);
        return array_map(static fn (array $record): UserInvite => UserInvite::fromArray($record), $records);
    }

    public function countPending(?string $search = null): int
    {
        $sql = 'SELECT COUNT(*) FROM user_invites WHERE status = :status';
        $params = ['status' => 'pending'];

        if ($search !== null && $search !== '') {
            $sql .= ' AND email LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        return (int) ($this->fetchColumn($sql, $params) ?? 0);
    }

    public function updateStatus(string $inviteId, string $status): void
    {
        $this->update(
            'UPDATE user_invites SET status = :status WHERE id = :id',
            [
                'status' => $status,
                'id' => $inviteId,
            ]
        );
    }

    public function deleteExpired(): int
    {
        return $this->delete(
            'DELETE FROM user_invites WHERE status = :status AND expires_at IS NOT NULL AND expires_at < NOW()',
            ['status' => 'pending']
        );
    }
}
