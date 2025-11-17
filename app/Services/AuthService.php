<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use RuntimeException;

final class AuthService
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function attempt(string $email, string $password): ?User
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return null;
        }

        if (!\Auth::verifyPassword($password, $user->passwordHash)) {
            return null;
        }

        return $user;
    }

    public function createUser(
        string $email,
        string $password,
        string $userType = 'user',
        ?string $organizationId = null,
        ?string $name = null
    ): User {
        if ($this->users->findByEmail($email) !== null) {
            throw new RuntimeException('Email address already registered.');
        }

        $user = new User(
            \generate_uuid_v4(),
            strtolower($email),
            \Auth::hashPassword($password),
            $name,
            $organizationId,
            $userType,
            'active',
            date('Y-m-d H:i:s')
        );

        $this->users->create($user);
        return $user;
    }

    public function updatePassword(string $userId, string $newPassword): void
    {
        $hash = \Auth::hashPassword($newPassword);
        $this->users->updatePassword($userId, $hash);
    }
}
