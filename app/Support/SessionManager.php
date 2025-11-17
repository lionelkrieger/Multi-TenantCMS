<?php

declare(strict_types=1);

namespace App\Support;

final class SessionManager
{
    private bool $started = false;

    public function __construct(private readonly array $config)
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $this->config['cookie_lifetime'] ?? $cookieParams['lifetime'],
            'path' => $cookieParams['path'] ?? '/',
            'domain' => $cookieParams['domain'] ?? '',
            'secure' => $this->config['cookie_secure'] ?? true,
            'httponly' => $this->config['cookie_httponly'] ?? true,
            'samesite' => $this->config['cookie_samesite'] ?? 'Strict',
        ]);

        if (isset($this->config['name'])) {
            session_name($this->config['name']);
        }

        session_start();
        $this->started = true;
    }

    public function regenerate(): void
    {
        if (!$this->started) {
            $this->start();
        }

        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        if (!$this->started && session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
        $this->started = false;
    }
}
