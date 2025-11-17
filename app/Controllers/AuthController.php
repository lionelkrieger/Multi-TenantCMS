<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Support\RequestValidator;

final class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function showLogin(?string $error = null): void
    {
        $this->render('auth/login', [
            'error' => $error,
        ]);
    }

    public function login(array $input): void
    {
    if (!\CSRF::validate($input['csrf_token'] ?? '')) {
            $this->showLogin('Invalid security token. Please refresh and try again.');
            return;
        }

        try {
            $validated = RequestValidator::validate($input, [
                'email' => static fn ($value) => RequestValidator::email($value),
                'password' => static fn ($value) => is_string($value) && $value !== '' ? $value : null,
            ]);
        } catch (\Throwable $throwable) {
            $this->showLogin($throwable->getMessage());
            return;
        }

        $user = $this->authService->attempt($validated['email'], $validated['password']);
        if ($user === null) {
            $this->showLogin('Invalid credentials.');
            return;
        }

        \Auth::login($user);
        $this->redirect('/index.php');
    }

    public function logout(): void
    {
        \Auth::logout();
        $this->redirect('/login.php');
    }

    public function showRegister(?string $error = null): void
    {
        $this->render('auth/register', [
            'error' => $error,
        ]);
    }

    public function register(array $input): void
    {
    if (!\CSRF::validate($input['csrf_token'] ?? '')) {
            $this->showRegister('Invalid security token.');
            return;
        }

        try {
            $validated = RequestValidator::validate($input, [
                'email' => static fn ($value) => RequestValidator::email($value),
                'password' => static fn ($value) => is_string($value) && strlen($value) >= 8 ? $value : null,
                'name' => static fn ($value) => RequestValidator::stringOrNull($value, 1, 255),
            ]);
        } catch (\Throwable $throwable) {
            $this->showRegister($throwable->getMessage());
            return;
        }

        try {
            $user = $this->authService->createUser($validated['email'], $validated['password'], 'user', null, $validated['name']);
            \Auth::login($user);
            $this->redirect('/index.php');
        } catch (\Throwable $throwable) {
            $this->showRegister($throwable->getMessage());
        }
    }
}
