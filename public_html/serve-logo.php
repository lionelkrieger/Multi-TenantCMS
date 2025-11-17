<?php
declare(strict_types=1);

$logo = $_GET['logo'] ?? '';
$logoPath = realpath(__DIR__ . '/../app/uploads/logos/' . basename($logo));
$basePath = realpath(__DIR__ . '/../app/uploads/logos');

if ($logo === '' || $logoPath === false || $basePath === false || !str_starts_with($logoPath, $basePath)) {
    http_response_code(404);
    exit('Not found');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($logoPath) ?: 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($logoPath));
readfile($logoPath);
