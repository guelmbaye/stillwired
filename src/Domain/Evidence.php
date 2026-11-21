<?php

declare(strict_types=1);

namespace Stillwired\Domain;

final class Evidence
{
    /** @param array<string,mixed> $report */
    public static function write(string $path, array $report): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create evidence directory');
        }
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents($path, $json . "\n");
        file_put_contents($path . '.sha256', hash('sha256', $json . "\n") . "\n");
    }

    public static function checksumValid(string $path): bool
    {
        if (!is_file($path) || !is_file($path . '.sha256')) {
            return false;
        }
        $checksum = trim((string) file_get_contents($path . '.sha256'));
        return hash_equals($checksum, hash_file('sha256', $path));
    }
}
