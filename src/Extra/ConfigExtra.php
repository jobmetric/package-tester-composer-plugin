<?php

namespace JobMetric\PackageTesterComposerPlugin\Extra;

class ConfigExtra
{
    protected string $basePath;
    protected const INTERNAL_DIR = '.package-tester';
    protected const INTERNAL_FILE = 'config.json';
    protected const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(string $basePath)
    {
        // Project base path.
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
    }

    /**
     * Persist configuration to the internal runtime file.
     */
    public function save(array $packages): void
    {
        $this->writeJsonFile($this->getInternalDir(), $this->getInternalPath(), $this->normalizePackages($packages));
    }

    /**
     * Load configuration from the internal runtime file.
     */
    public function load(): array
    {
        $path = $this->getInternalPath();

        if (!is_file($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Remove persisted configuration files.
     */
    public function clear(): void
    {
        $this->deleteFile($this->getInternalPath());
    }

    protected function getInternalDir(): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . self::INTERNAL_DIR;
    }

    protected function getInternalPath(): string
    {
        return $this->getInternalDir() . DIRECTORY_SEPARATOR . self::INTERNAL_FILE;
    }

    protected function writeJsonFile(string $dir, string $path, array $data): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }

        if (!$this->isWritableTarget($dir, $path)) {
            return;
        }

        $json = json_encode($data, self::JSON_FLAGS);
        if ($json === false) {
            return;
        }

        file_put_contents($path, $json);
    }

    /**
     * Keep only package autoload-dev mapping in runtime config.
     */
    protected function normalizePackages(array $packages): array
    {
        $normalized = [];

        foreach ($packages as $packageName => $package) {
            if (!is_array($package)) {
                continue;
            }

            $resolvedName = is_string($packageName) && $packageName !== ''
                ? $packageName
                : (string) ($package['name'] ?? '');

            if ($resolvedName === '') {
                continue;
            }

            $autoloadDev = $package['autoload_dev'] ?? [];
            if (!is_array($autoloadDev)) {
                $autoloadDev = [];
            }

            $normalized[$resolvedName] = [
                'autoload_dev' => $autoloadDev,
            ];
        }

        return $normalized;
    }

    protected function isWritableTarget(string $dir, string $path): bool
    {
        if (is_file($path)) {
            return is_writable($path);
        }

        return is_writable($dir);
    }

    protected function deleteFile(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
