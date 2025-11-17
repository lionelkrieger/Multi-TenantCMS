<?php

declare(strict_types=1);

namespace App\Controllers;

abstract class Controller
{
    protected function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = view_path($view . '.php');
        if (!file_exists($viewFile)) {
            throw new \RuntimeException(sprintf('View %s not found.', $view));
        }

        require $viewFile;
    }

    protected function redirect(string $path): void
    {
        redirect($path);
    }
}
