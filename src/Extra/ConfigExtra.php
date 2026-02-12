<?php

namespace JobMetric\PackageTesterComposerPlugin\Extra;

/**
 * Class ConfigExtra
 *
 * Manages runtime configuration persistence for the package-tester plugin.
 * Handles saving, loading, and clearing of discovered package configurations
 * in a JSON file within the .package-tester directory.
 *
 * @package JobMetric\PackageTesterComposerPlugin\Extra
 */
class ConfigExtra
{
    /**
     * The project base path.
     *
     * @var string
     */
    protected string $basePath;
    
    /**
     * The internal configuration directory name.
     *
     * @var string
     */
    protected const INTERNAL_DIR = '.package-tester';
    
    /**
     * The internal configuration file name.
     *
     * @var string
     */
    protected const INTERNAL_FILE = 'config.json';
    
    /**
     * JSON encoding flags for pretty output.
     *
     * @var int
     */
    protected const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    
    /**
     * Create a new configuration handler instance.
     *
     * @param string $basePath The project base path
     */
    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
    }
    
    /**
     * Persist package configuration to the internal runtime file.
     *
     * @param array<string, array{autoload_dev: array<string, string|array>}> $packages The packages to save
     *
     * @return void
     */
    public function save(array $packages): void
    {
        $this->writeJsonFile($this->getInternalDir(), $this->getInternalPath(), $this->normalizePackages($packages));
    }
    
    /**
     * Load configuration from the internal runtime file.
     *
     * @return array<string, array{autoload_dev: array<string, string|array>}> The loaded package configurations
     */
    public function load(): array
    {
        $path = $this->getInternalPath();
        
        if (! is_file($path)) {
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
     *
     * @return void
     */
    public function clear(): void
    {
        $this->deleteFile($this->getInternalPath());
    }
    
    /**
     * Get the internal configuration directory path.
     *
     * @return string The absolute path to the configuration directory
     */
    protected function getInternalDir(): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . self::INTERNAL_DIR;
    }
    
    /**
     * Get the internal configuration file path.
     *
     * @return string The absolute path to the configuration file
     */
    protected function getInternalPath(): string
    {
        return $this->getInternalDir() . DIRECTORY_SEPARATOR . self::INTERNAL_FILE;
    }
    
    /**
     * Write data to a JSON file.
     *
     * Creates the directory if it doesn't exist and writes the JSON-encoded
     * data to the specified file path.
     *
     * @param string $dir                The directory path
     * @param string $path               The file path
     * @param array<string, mixed> $data The data to encode and write
     *
     * @return void
     */
    protected function writeJsonFile(string $dir, string $path, array $data): void
    {
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return;
        }
        
        if (! $this->isWritableTarget($dir, $path)) {
            return;
        }
        
        $json = json_encode($data, self::JSON_FLAGS);
        if ($json === false) {
            return;
        }
        
        file_put_contents($path, $json);
    }
    
    /**
     * Normalize packages to keep only autoload-dev mapping.
     *
     * Filters and restructures the package data to contain only the
     * essential autoload-dev configuration for runtime use.
     *
     * @param array<string, array<string, mixed>> $packages The raw package data
     *
     * @return array<string, array{autoload_dev: array<string, string|array>}> Normalized package data
     */
    protected function normalizePackages(array $packages): array
    {
        $normalized = [];
        
        foreach ($packages as $packageName => $package) {
            if (! is_array($package)) {
                continue;
            }
            
            $resolvedName = is_string($packageName) && $packageName !== '' ? $packageName : (string) ($package['name'] ?? '');
            
            if ($resolvedName === '') {
                continue;
            }
            
            $autoloadDev = $package['autoload_dev'] ?? [];
            if (! is_array($autoloadDev)) {
                $autoloadDev = [];
            }
            
            $normalized[$resolvedName] = [
                'autoload_dev' => $autoloadDev,
            ];
        }
        
        return $normalized;
    }
    
    /**
     * Check if the target file or directory is writable.
     *
     * @param string $dir  The directory path
     * @param string $path The file path
     *
     * @return bool True if the target is writable, false otherwise
     */
    protected function isWritableTarget(string $dir, string $path): bool
    {
        if (is_file($path)) {
            return is_writable($path);
        }
        
        return is_writable($dir);
    }
    
    /**
     * Delete a file if it exists.
     *
     * @param string $path The file path to delete
     *
     * @return void
     */
    protected function deleteFile(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
