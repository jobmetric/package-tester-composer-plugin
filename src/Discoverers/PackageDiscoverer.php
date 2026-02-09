<?php

namespace JobMetric\PackageTesterComposerPlugin\Discoverers;

use JobMetric\PackageTesterComposerPlugin\Discoverers\PackageAnalyzer;

class PackageDiscoverer
{
    /**
     * Discovered packages with tests
     *
     * @var array
     */
    protected array $packages = [];

    /**
     * Base path
     *
     * @var string
     */
    protected string $basePath;

    /**
     * Package analyzer helper
     *
     * @var PackageAnalyzer
     */
    protected PackageAnalyzer $packageAnalyzer;

    /**
     * Create a new discoverer instance
     *
     * @param string|null $basePath
     */
    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? getcwd();
        $this->packageAnalyzer = new PackageAnalyzer();
    }

    /**
     * Discover all packages with test configuration
     *
     * @return self
     */
    public function discover(): static
    {
        $this->packages = [];

        $this->scanDirectory($this->basePath . DIRECTORY_SEPARATOR . 'vendor', true);


        return $this;
    }

    /**
     * Scan a directory for packages
     *
     * @param string $directory
     * @param bool $nestedStructure
     * @return void
     */
    protected function scanDirectory(string $directory, bool $nestedStructure = true): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $dirs = glob($directory . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];

        foreach ($dirs as $dir) {
            if ($nestedStructure) {
                $packages = glob($dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
                foreach ($packages as $packageDir) {
                    $this->processPackage($packageDir);
                }
            } else {
                $this->processPackage($dir);
            }
        }
    }

    /**
     * Process a single package
     *
     * @param string $packagePath
     * @return void
     */
    protected function processPackage(string $packagePath): void
    {
        $composerFile = $packagePath . DIRECTORY_SEPARATOR . 'composer.json';
        $packageTesterFile = $packagePath . DIRECTORY_SEPARATOR . 'package-tester.json';

        if (!is_file($composerFile)) {
            return;
        }

        // Check for package-tester.json file - this is the primary criterion
        if (!is_file($packageTesterFile)) {
            return;
        }

        $content = file_get_contents($composerFile);
        $composer = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return;
        }

        $packageName = $composer['name'] ?? basename($packagePath);

        if (isset($this->packages[$packageName])) {
            return;
        }

        // Read package-tester.json configuration
        $packageTesterContent = file_get_contents($packageTesterFile);
        $packageTesterConfig = json_decode($packageTesterContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return;
        }

        // Skip if runner is disabled
        if (isset($packageTesterConfig['runner']['enabled']) && !$packageTesterConfig['runner']['enabled']) {
            return;
        }

        $autoloadDev = $composer['autoload-dev']['psr-4'] ?? [];
        $tests = $this->extractTestPathsFromConfig($packageTesterConfig, $packagePath, $composer, $autoloadDev);

        if (empty($tests)) {
            return;
        }

        $this->packages[$packageName] = [
            'name' => $packageName,
            'version' => $composer['version'] ?? 'dev',
            'description' => $composer['description'] ?? '',
            'path' => $packagePath,
            'tests' => $tests,
            'options' => (array) ($packageTesterConfig['namespace']['option'] ?? []),
            'dependencies' => (array) ($packageTesterConfig['dependency-packages'] ?? []),
            'autoload_dev' => $autoloadDev ?: [],
            'package_tester_config' => $packageTesterFile,
        ];
    }

    /**
     * Extract test paths from package-tester.json configuration
     *
     * @param array $packageTesterConfig
     * @param string $packagePath
     * @param array $composer
     * @param array $autoloadDev
     * @return array
     */
    protected function extractTestPathsFromConfig(array $packageTesterConfig, string $packagePath, array $composer, array $autoloadDev): array
    {
        $autoloadCandidates = $this->extractAutoloadDevPaths($composer);
        $pathToNamespace = $autoloadCandidates['map'];

        $tests = [];

        // If namespace configuration exists in package-tester.json
        if (isset($packageTesterConfig['namespace'])) {
            $namespace = $packageTesterConfig['namespace'];
            $testPath = $namespace['path'] ?? 'tests';
            $fullPath = $packagePath . DIRECTORY_SEPARATOR . ltrim($testPath, DIRECTORY_SEPARATOR);

            if (is_dir($fullPath)) {
                $normalized = rtrim($testPath, '/\\');
                $namespaceName = $pathToNamespace[$normalized] ?? null;

                $tests[] = [
                    'name' => $namespaceName ? rtrim($namespaceName, '\\') : 'Tests',
                    'path' => $testPath,
                    'namespace' => $namespaceName,
                    'options' => (array) ($namespace['option'] ?? []),
                    'filter' => $namespace['filter'] ?? null,
                ];
            }
        } else {
            // Fallback to autoload-dev
            $validTests = [];

            foreach ($autoloadDev as $namespace => $paths) {
                $pathList = is_array($paths) ? $paths : [$paths];

                foreach ($pathList as $testPath) {
                    $fullPath = $packagePath . DIRECTORY_SEPARATOR . ltrim($testPath, DIRECTORY_SEPARATOR);
                    if (!is_dir($fullPath)) {
                        continue;
                    }

                    $normalized = rtrim($testPath, '/\\');
                    $namespaceName = $pathToNamespace[$normalized] ?? $namespace;

                    $validTests[] = [
                        'name' => $namespaceName ? rtrim($namespaceName, '\\') : 'Tests',
                        'path' => $testPath,
                        'namespace' => $namespaceName,
                        'options' => [],
                        'filter' => null,
                    ];
                }
            }

            $tests = $validTests;
        }

        return $tests;
    }

    /**
     * Extract autoload-dev test paths
     *
     * @param array $composer
     * @return array{paths: array<int, string>, map: array<string, string>}
     */
    protected function extractAutoloadDevPaths(array $composer): array
    {
        $psr4 = (array) ($composer['autoload-dev']['psr-4'] ?? []);
        $paths = [];
        $map = [];

        foreach ($psr4 as $namespace => $pathOrPaths) {
            $pathList = is_array($pathOrPaths) ? $pathOrPaths : [$pathOrPaths];
            foreach ($pathList as $path) {
                if (!is_string($path) || $path === '') {
                    continue;
                }
                $normalized = rtrim($path, '/\\');
                if (!in_array($normalized, $paths, true)) {
                    $paths[] = $normalized;
                }
                if (!isset($map[$normalized])) {
                    $map[$normalized] = $namespace;
                }
            }
        }

        return [
            'paths' => $paths,
            'map' => $map,
        ];
    }

    /**
     * Get all discovered packages
     *
     * @return array
     */
    public function getPackages(): array
    {
        return $this->packages;
    }

    /**
     * Get a specific package by name
     *
     * @param string $packageName
     * @return array|null
     */
    public function getPackage(string $packageName): ?array
    {
        return $this->packages[$packageName] ?? null;
    }

    /**
     * Check if a package exists
     *
     * @param string $packageName
     * @return bool
     */
    public function hasPackage(string $packageName): bool
    {
        return isset($this->packages[$packageName]);
    }

    /**
     * Get packages count
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->packages);
    }

    /**
     * Get test paths for a package
     *
     * @param string $packageName
     * @return array
     */
    public function getTestPaths(string $packageName): array
    {
        $package = $this->getPackage($packageName);

        return $package['tests'] ?? [];
    }
}
