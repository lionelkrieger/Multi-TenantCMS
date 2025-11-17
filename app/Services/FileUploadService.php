<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class FileUploadService
{
    /**
     * @param array{tmp_name:string,name:string,size:int,error:int,type?:string} $file
     */
    public function storeLogo(array $file): string
    {
        return $this->store($file, storage_path('logos'), ['image/png', 'image/jpeg', 'image/webp'], 5 * 1024 * 1024);
    }

    /**
     * @param array{tmp_name:string,name:string,size:int,error:int,type?:string} $file
     */
    public function storeDocument(array $file): string
    {
        return $this->store($file, storage_path('documents'), ['application/pdf'], 10 * 1024 * 1024);
    }

    /**
     * @param array{tmp_name:string,name:string,size:int,error:int,type?:string} $file
     * @return string relative path from storage root
     */
    private function store(array $file, string $destinationDir, array $allowedMime, int $maxBytes): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed.');
        }

        if (($file['size'] ?? 0) > $maxBytes) {
            throw new RuntimeException('Uploaded file exceeds maximum size.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if ($mime === false || !in_array($mime, $allowedMime, true)) {
            throw new RuntimeException('Invalid file type.');
        }

        $extension = $this->extensionFromMime($mime);
        $filename = sprintf('%s.%s', random_token(24), $extension);
        $this->ensureDirectory($destinationDir);
        $targetPath = $destinationDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Failed to store uploaded file.');
        }

        chmod($targetPath, 0600);
        return basename($destinationDir) . '/' . $filename;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
