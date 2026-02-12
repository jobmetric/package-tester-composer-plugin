<?php

namespace JobMetric\PackageTesterComposerPlugin\Discoverers;

/**
 * Class PackageDiscoverer
 *
 * Scans the vendor directory to discover packages with package-tester.json
 * configuration files and extracts their test configurations.
 *
 * @package JobMetric\PackageTesterComposerPlugin\Discoverers
 */
class PackageDiscoverer
{
    /**
     * Discovered packages with test configurations.
     *
     * @var array<string, array{
     *     name: string,
     *     version: string,
     *     description: string,
     *     path: string,
     *     tests: array,
     *     options: array,
     *     dependencies: array,
     *     autoload_dev: array<string, string|array>,
     *     package_tester_config: string
     * }>
     */
    protected array $packages = [];
    
    /**
     * The project base path.
     *
     * @var string
     */
    protected string $basePath;
    
    /**
     * The package analyzer instance.
     *
     * @var PackageAnalyzer
     */
    protected PackageAnalyzer $packageAnalyzer;
    
    /**
     * Create a new package discoverer instance.
     *
     * @param string|null $basePath The project base path (defaults to current working directory)
     */
    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? getcwd();
        $this->packageAnalyzer = new PackageAnalyzer();
    }
    
    /**
     * Discover all packages with test configuration.
     *
     * Scans the vendor directory for packages containing package-tester.json
     * and extracts their test configurations.
     *
     * @return static Returns self for method chaining
     */
    public function discover(): static
    {
        $this->packages = [];
        $this->scanDirectory($this->basePath . DIRECTORY_SEPARATOR . 'vendor', true);
        
        return $this;
    }
    
    /**
     * Scan a directory for packages.
     *
     * @param string $directory     The directory to scan
     * @param bool $nestedStructure Whether to expect vendor/namespace/package structure
     *
     * @return void
     */
    protected function scanDirectory(string $directory, bool $nestedStructure = true): void
    {
        if (! is_dir($directory)) {
            return;
        }
        
        $dirs = glob($directory . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        
        foreach ($dirs as $dir) {
            if ($nestedStructure) {
                $packages = glob($dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
                foreach ($packages as $packageDir) {
                    $this->processPackage($packageDir);
                }
            }
            else {
                $this->processPackage($dir);
            }
        }
    }
    
    /**
     * Process a single package directory.
     *
     * Checks for composer.json and package-tester.json files, then extracts
     * the package's test configuration if valid.
     *
     * @param string $packagePath Absolute path to the package directory
     *
     * @return void
     */
    protected function processPackage(string $packagePath): void
    {
        $composerFile = $packagePath . DIRECTORY_SEPARATOR . 'composer.json';
        $packageTesterFile = $packagePath . DIRECTORY_SEPARATOR . 'package-tester.json';
        
        if (! is_file($composerFile)) {
            return;
        }
        
        // Check for package-tester.json file - this is the primary criterion
        if (! is_file($packageTesterFile)) {
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
        if (isset($packageTesterConfig['runner']['enabled']) && ! $packageTesterConfig['runner']['enabled']) {
            return;
        }
        
        $autoloadDev = $composer['autoload-dev']['psr-4'] ?? [];
        $tests = $this->extractTestPathsFromConfig($packageTesterConfig, $packagePath, $composer, $autoloadDev);
        
        if (empty($tests)) {
            return;
        }
        
        $this->packages[$packageName] = [
            'name'                  => $packageName,
            'version'               => $composer['version'] ?? 'dev',
            'description'           => $composer['description'] ?? '',
            'path'                  => $packagePath,
            'tests'                 => $tests,
            'options'               => (array) ($packageTesterConfig['namespace']['option'] ?? []),
            'dependencies'          => (array) ($packageTesterConfig['dependency-packages'] ?? []),
            'autoload_dev'          => $autoloadDev ?: [],
            'package_tester_config' => $packageTesterFile,
        ];
    }
    
    /**
     * Extract test paths from package-tester.json configuration.
     *
     * Parses the package-tester configuration and autoload-dev to build
     * a list of valid test paths with their namespace mappings.
     *
     * @param array<string, mixed> $packageTesterConfig The package-tester.json configuration
     * @param string $packagePath                       Absolute path to the package
     * @param array<string, mixed> $composer            The composer.json data
     * @param array<string, string|array> $autoloadDev  The autoload-dev PSR-4 mappings
     *
     * @return array<int, array{name: string, path: string, namespace: string|null, options: array, filter:
     *                    string|null}>
     */
    protected function extractTestPathsFromConfig(
        array $packageTesterConfig,
        string $packagePath,
        array $composer,
        array $autoloadDev
    ): array {
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
                    'name'      => $namespaceName ? rtrim($namespaceName, '\\') : 'Tests',
                    'path'      => $testPath,
                    'namespace' => $namespaceName,
                    'options'   => (array) ($namespace['option'] ?? []),
                    'filter'    => $namespace['filter'] ?? null,
                ];
            }
        }
        else {
            // Fallback to autoload-dev
            $validTests = [];
            
            foreach ($autoloadDev as $namespace => $paths) {
                $pathList = is_array($paths) ? $paths : [$paths];
                
                foreach ($pathList as $testPath) {
                    $fullPath = $packagePath . DIRECTORY_SEPARATOR . ltrim($testPath, DIRECTORY_SEPARATOR);
                    if (! is_dir($fullPath)) {
                        continue;
                    }
                    
                    $normalized = rtrim($testPath, '/\\');
                    $namespaceName = $pathToNamespace[$normalized] ?? $namespace;
                    
                    $validTests[] = [
                        'name'      => $namespaceName ? rtrim($namespaceName, '\\') : 'Tests',
                        'path'      => $testPath,
                        'namespace' => $namespaceName,
                        'options'   => [],
                        'filter'    => null,
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
     *
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
                if (! is_string($path) || $path === '') {
                    continue;
                }
                $normalized = rtrim($path, '/\\');
                if (! in_array($normalized, $paths, true)) {
                    $paths[] = $normalized;
                }
                if (! isset($map[$normalized])) {
                    $map[$normalized] = $namespace;
                }
            }
        }
        
        return [
            'paths' => $paths,
            'map'   => $map,
        ];
    }
    
    /**
     * Get all discovered packages.
     *
     * @return array<string, array{
     *     name: string,
     *     version: string,
     *     description: string,
     *     path: string,
     *     tests: array,
     *     options: array,
     *     dependencies: array,
     *     autoload_dev: array<string, string|array>,
     *     package_tester_config: string
     * }> Array of packages indexed by package name
     */
    public function getPackages(): array
    {
        return $this->packages;
    }
    
    /**
     * Get a specific package by name.
     *
     * @param string $packageName The package name to retrieve
     *
     * @return array{
     *     name: string,
     *     version: string,
     *     description: string,
     *     path: string,
     *     tests: array,
     *     options: array,
     *     dependencies: array,
     *     autoload_dev: array<string, string|array>,
     *     package_tester_config: string
     * }|null The package data or null if not found
     */
    public function getPackage(string $packageName): ?array
    {
        return $this->packages[$packageName] ?? null;
    }
    
    /**
     * Check if a package exists in the discovered packages.
     *
     * @param string $packageName The package name to check
     *
     * @return bool True if the package exists, false otherwise
     */
    public function hasPackage(string $packageName): bool
    {
        return isset($this->packages[$packageName]);
    }
    
    /**
     * Get the number of discovered packages.
     *
     * @return int The count of discovered packages
     */
    public function count(): int
    {
        return count($this->packages);
    }
    
    /**
     * Get test paths for a specific package.
     *
     * @param string $packageName The package name to get test paths for
     *
     * @return array<int, array{name: string, path: string, namespace: string|null, options: array, filter:
     *                    string|null}>
     */
    public function getTestPaths(string $packageName): array
    {
        $package = $this->getPackage($packageName);
        
        return $package['tests'] ?? [];
    }
}
