<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\User;
use App\Models\UserInvite;
use App\Repositories\UserInviteRepository;
use App\Repositories\UserRepository;
use App\Support\RequestValidator;
use RuntimeException;

final class UserInviteService
{
    private const DEFAULT_EXPIRY_INTERVAL = '+7 days';
    private const ALLOWED_INVITE_TYPES = ['org_admin', 'employee', 'user'];

    public function __construct(
        private readonly UserInviteRepository $invites,
        private readonly UserRepository $users
    ) {
    }

    public function issueInvite(
        string $email,
        string $inviteType,
        ?string $organizationId,
        string $inviterUserId,
        ?string $expiresAt = null
    ): UserInvite {
        $normalizedEmail = RequestValidator::email($email);
        if ($normalizedEmail === null) {
            throw ValidationException::fromField('email');
        }

        $normalizedInviteType = $this->normalizeInviteType($inviteType);
        if (in_array($normalizedInviteType, ['org_admin', 'employee'], true) && empty($organizationId)) {
            throw ValidationException::fromField('organization_id');
        }

        if ($this->users->findByEmail($normalizedEmail) !== null) {
            throw new RuntimeException('User already exists for this email.');
        }

    $token = \random_token(32);
        $now = date('Y-m-d H:i:s');
        $expiresAtValue = $expiresAt ?? date('Y-m-d H:i:s', strtotime(self::DEFAULT_EXPIRY_INTERVAL));

        $invite = new UserInvite(
            \generate_uuid_v4(),
            $normalizedEmail,
            $organizationId,
            $inviterUserId,
            $normalizedInviteType,
            $token,
            'pending',
            $now,
            $expiresAtValue
        );

        $this->invites->create($invite);
        return $invite;
    }

    public function validateToken(string $token): ?UserInvite
    {
        $invite = $this->invites->findByToken($token);
        if ($invite === null) {
            return null;
        }

        if ($invite->status !== 'pending' || $this->isExpired($invite)) {
            if ($invite->status === 'pending' && $this->isExpired($invite)) {
                $this->invites->updateStatus($invite->id, 'expired');
            }
            return null;
        }

        return $invite;
    }

    public function acceptInvite(string $token, string $password, ?string $name = null): ?User
    {
        $invite = $this->validateToken($token);
        if ($invite === null) {
            return null;
        }

        if ($this->users->findByEmail($invite->email) !== null) {
            $this->invites->updateStatus($invite->id, 'accepted');
            return null;
        }

        $user = $this->users->createOrgUser(
            $invite->email,
            \Auth::hashPassword($password),
            $invite->inviteType,
            $invite->organizationId,
            $name
        );

        $this->invites->updateStatus($invite->id, 'accepted');
        return $user;
    }

    public function revokeInvite(string $inviteId): void
    {
        $invite = $this->invites->findById($inviteId);
        if ($invite === null) {
            return;
        }

        $this->invites->updateStatus($invite->id, 'revoked');
    }

    private function normalizeInviteType(string $inviteType): string
    {
        $normalized = strtolower($inviteType);
        if (!in_array($normalized, self::ALLOWED_INVITE_TYPES, true)) {
            throw ValidationException::fromField('invite_type');
        }

        return $normalized;
    }

    private function isExpired(UserInvite $invite): bool
    {
        if ($invite->expiresAt === null) {
            return false;
        }

        return strtotime($invite->expiresAt) < time();
    }
}
