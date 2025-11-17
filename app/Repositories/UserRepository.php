<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

final class UserRepository extends Repository
{
    public function findById(string $id): ?User
    {
        $record = $this->fetchOne('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $id]);
        return $record ? User::fromArray($record) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $record = $this->fetchOne('SELECT * FROM users WHERE email = :email LIMIT 1', ['email' => strtolower($email)]);
        return $record ? User::fromArray($record) : null;
    }

    public function listByOrganization(string $organizationId, int $limit = 25, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $records = $this->fetchAll(
            sprintf(
                'SELECT * FROM users WHERE organization_id = :organization_id ORDER BY created_at DESC LIMIT %d OFFSET %d',
                $limit,
                $offset
            ),
            [
                'organization_id' => $organizationId,
            ]
        );

        return array_map(static fn (array $record): User => User::fromArray($record), $records);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, User>
     */
    public function listAll(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $sql = 'SELECT * FROM users WHERE 1=1';
        $params = [];

        if (!empty($filters['user_type'])) {
            $sql .= ' AND user_type = :user_type';
            $params['user_type'] = $filters['user_type'];
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['organization_id'])) {
            $sql .= ' AND organization_id = :organization_id';
            $params['organization_id'] = $filters['organization_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND (email LIKE :search OR name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql .= sprintf(' ORDER BY created_at DESC LIMIT %d OFFSET %d', $limit, $offset);

        $records = $this->fetchAll($sql, $params);
        return array_map(static fn (array $record): User => User::fromArray($record), $records);
    }

    public function create(User $user): void
    {
        $this->insert(
            'INSERT INTO users (id, email, password_hash, name, organization_id, user_type, status, created_at) VALUES (:id, :email, :password_hash, :name, :organization_id, :user_type, :status, :created_at)',
            [
                'id' => $user->id,
                'email' => $user->email,
                'password_hash' => $user->passwordHash,
                'name' => $user->name,
                'organization_id' => $user->organizationId,
                'user_type' => $user->userType,
                'status' => $user->status,
                'created_at' => $user->createdAt,
            ]
        );
    }

    public function updatePassword(string $userId, string $passwordHash): void
    {
        $this->update(
            'UPDATE users SET password_hash = :password_hash WHERE id = :id',
            [
                'password_hash' => $passwordHash,
                'id' => $userId,
            ]
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function countAllFiltered(array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE 1=1';
        $params = [];

        if (!empty($filters['user_type'])) {
            $sql .= ' AND user_type = :user_type';
            $params['user_type'] = $filters['user_type'];
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['organization_id'])) {
            $sql .= ' AND organization_id = :organization_id';
            $params['organization_id'] = $filters['organization_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND (email LIKE :search OR name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        return (int) ($this->fetchColumn($sql, $params) ?? 0);
    }

    public function createOrgUser(
        string $email,
        string $passwordHash,
        string $userType,
        ?string $organizationId,
        ?string $name = null,
        string $status = 'active'
    ): User {
        $user = new User(
            \generate_uuid_v4(),
            strtolower($email),
            $passwordHash,
            $name,
            $organizationId,
            $userType,
            $status,
            date('Y-m-d H:i:s')
        );

        $this->create($user);
        return $user;
    }

    /**
     * @param array<int, string> $userIds
     * @return array<string, User>
     */
    public function findByIds(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds, static fn ($id) => is_string($id) && $id !== '')));
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $records = $this->fetchAll(
            sprintf('SELECT * FROM users WHERE id IN (%s)', $placeholders),
            $userIds
        );

        $users = [];
        foreach ($records as $record) {
            $user = User::fromArray($record);
            $users[$user->id] = $user;
        }

        return $users;
    }

    public function countAll(): int
    {
        return (int) ($this->fetchColumn('SELECT COUNT(*) FROM users') ?? 0);
    }

    /**
     * @return array<int, User>
     */
    public function recent(int $limit = 5): array
    {
        $limit = max(1, min(25, $limit));
        $records = $this->fetchAll(
            sprintf('SELECT * FROM users ORDER BY created_at DESC LIMIT %d', $limit)
        );

        return array_map(static fn (array $record): User => User::fromArray($record), $records);
    }
}
