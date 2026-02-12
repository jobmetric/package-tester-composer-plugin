<?php

namespace JobMetric\PackageTesterComposerPlugin\Discoverers;

/**
 * Class PackageAnalyzer
 *
 * Analyzes individual packages to extract test configuration and metadata.
 * Supports both explicit configuration via composer.json extra and auto-discovery
 * of test directories.
 *
 * @package JobMetric\PackageTesterComposerPlugin\Discoverers
 */
class PackageAnalyzer
{
    /**
     * Default test directory names to search for during auto-discovery.
     *
     * @var array<int, string>
     */
    protected const DEFAULT_TEST_DIRS = [
        'tests',
        'test',
        'Tests',
        'Test',
    ];
    
    /**
     * Subdirectories to ignore during test discovery.
     *
     * @var array<int, string>
     */
    protected const IGNORED_SUB_DIRS = [
        'Fixtures',
        'Stubs',
        'data',
        'fixtures',
        'stubs',
        '__snapshots__',
    ];
    
    /**
     * Discover package test configuration.
     *
     * Reads the package's composer.json and extracts test configuration
     * from the package-tester extra section. Returns null if no valid
     * configuration is found.
     *
     * @param string $packagePath Absolute path to the package directory
     *
     * @return array{
     *     name: string,
     *     version: string,
     *     path: string,
     *     tests: array,
     *     options: array,
     *     autoload_dev: array<string, string|array>
     * }|null Package configuration or null if not found
     */
    public function discover(string $packagePath): ?array
    {
        // Read composer.json
        $composerData = $this->readComposerJson($packagePath);
        
        if ($composerData === null) {
            return null;
        }
        
        // Check for package-tester config
        $testerConfig = $composerData['extra']['package-tester'] ?? null;
        
        if ($testerConfig === null) {
            return null;
        }
        
        // Get package info
        $packageName = $composerData['name'] ?? basename($packagePath);
        $packageVersion = $composerData['version'] ?? 'dev';
        
        // Get autoload-dev namespaces
        $autoloadDev = $composerData['autoload-dev']['psr-4'] ?? [];
        
        // Discover test suites
        $tests = $this->discoverTests($packagePath, $testerConfig);
        
        if (empty($tests) && empty($autoloadDev)) {
            return null;
        }
        
        return [
            'name'         => $packageName,
            'version'      => $packageVersion,
            'path'         => $packagePath,
            'tests'        => $tests,
            'options'      => (array) ($testerConfig['options'] ?? []),
            'autoload_dev' => $autoloadDev,
        ];
    }
    
    /**
     * Discover test suites from configuration or auto-detect.
     *
     * If tests are explicitly defined in the tester config, validates them.
     * Otherwise, attempts to auto-discover test directories.
     *
     * @param string $packagePath                Absolute path to the package
     * @param array<string, mixed> $testerConfig The package-tester configuration
     *
     * @return array<int, array{name: string, path: string, namespace: string|null, options: array, filter:
     *                    string|null}>
     */
    protected function discoverTests(string $packagePath, array $testerConfig): array
    {
        $tests = $testerConfig['tests'] ?? [];
        
        // If tests defined in config, validate them
        if (! empty($tests)) {
            return $this->validateTests($packagePath, $tests);
        }
        
        // Auto-discover test directories
        return $this->autoDiscoverTests($packagePath);
    }
    
    /**
     * Validate configured test paths.
     *
     * Verifies that each configured test path exists and normalizes
     * the configuration structure.
     *
     * @param string $packagePath                   Absolute path to the package
     * @param array<int|string, mixed>|array $tests Raw test configurations
     *
     * @return array<int, array{name: string, path: string, namespace: string|null, options: array, filter:
     *                    string|null}>
     */
    protected function validateTests(string $packagePath, array $tests): array
    {
        $valid = [];
        
        foreach ($tests as $test) {
            if (is_string($test)) {
                $test = ['path' => $test];
            }
            
            if (! is_array($test)) {
                continue;
            }
            
            $path = $test['path'] ?? 'tests';
            $fullPath = $this->joinPath($packagePath, $path);
            
            if (! is_dir($fullPath)) {
                continue;
            }
            
            $valid[] = [
                'name'      => $test['name'] ?? basename($path),
                'path'      => $path,
                'namespace' => $test['namespace'] ?? null,
                'options'   => (array) ($test['options'] ?? []),
                'filter'    => $test['filter'] ?? null,
            ];
        }
        
        return $valid;
    }
    
    /**
     * Auto-discover test directories.
     *
     * Searches for common test directory patterns and their sub-directories
     * (Unit, Feature, Integration, etc.) to build test configuration automatically.
     *
     * @param string $packagePath Absolute path to the package
     *
     * @return array<int, array{name: string, path: string, namespace: string|null, options: array, filter:
     *                    string|null}>
     */
    protected function autoDiscoverTests(string $packagePath): array
    {
        $tests = [];
        
        // First, find main test directory
        $testDir = $this->findTestDirectory($packagePath);
        
        if ($testDir === null) {
            return [];
        }
        
        $testPath = $this->joinPath($packagePath, $testDir);
        
        // Check for sub-directories (Unit, Feature, etc.)
        $subDirs = $this->findTestSubDirectories($testPath);
        
        if (! empty($subDirs)) {
            foreach ($subDirs as $subDir) {
                $tests[] = [
                    'name'      => $subDir,
                    'path'      => $testDir . '/' . $subDir,
                    'namespace' => null,
                    'options'   => [],
                    'filter'    => null,
                ];
            }
        }
        else {
            // No subdirectories, use main test dir
            $tests[] = [
                'name'      => 'Tests',
                'path'      => $testDir,
                'namespace' => null,
                'options'   => [],
                'filter'    => null,
            ];
        }
        
        return $tests;
    }
    
    /**
     * Find the main test directory.
     *
     * Searches for a test directory in the package using either the provided
     * possible paths or the default test directory names.
     *
     * @param string $packagePath                    Absolute path to the package
     * @param array<int, string>|null $possiblePaths Optional custom paths to search
     *
     * @return string|null The relative test directory path or null if not found
     */
    public function findTestDirectory(string $packagePath, ?array $possiblePaths = null): ?string
    {
        $possiblePaths = $possiblePaths ?? self::DEFAULT_TEST_DIRS;
        
        foreach ($possiblePaths as $testPath) {
            $fullPath = $this->joinPath($packagePath, $testPath);
            
            if (is_dir($fullPath)) {
                return $testPath;
            }
        }
        
        return null;
    }
    
    /**
     * Find test subdirectories.
     *
     * Searches for common test subdirectories like Unit, Feature, Integration,
     * Functional, Api, and any custom directories while ignoring fixture directories.
     *
     * @param string $testPath Absolute path to the test directory
     *
     * @return array<int, string> List of discovered subdirectory names
     */
    protected function findTestSubDirectories(string $testPath): array
    {
        $commonDirs = ['Unit', 'Feature', 'Integration', 'Functional', 'Api'];
        $found = [];
        
        foreach ($commonDirs as $dir) {
            $fullPath = $this->joinPath($testPath, $dir);
            
            if (is_dir($fullPath)) {
                $found[] = $dir;
            }
        }
        
        // Also check for custom directories
        $allDirs = glob($testPath . '/*', GLOB_ONLYDIR) ?: [];
        
        foreach ($allDirs as $dir) {
            $dirName = basename($dir);
            
            // Skip common non-test directories
            if (in_array($dirName, self::IGNORED_SUB_DIRS)) {
                continue;
            }
            
            if (! in_array($dirName, $found)) {
                $found[] = $dirName;
            }
        }
        
        return $found;
    }
    
    /**
     * Read and parse composer.json from a package.
     *
     * @param string $packagePath Absolute path to the package directory
     *
     * @return array<string, mixed>|null Parsed composer.json data or null on failure
     */
    protected function readComposerJson(string $packagePath): ?array
    {
        $file = $this->joinPath($packagePath, 'composer.json');
        
        if (! is_file($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        
        if ($content === false) {
            return null;
        }
        
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return $data;
    }
    
    /**
     * Join path segments with the directory separator.
     *
     * @param string ...$parts Path segments to join
     *
     * @return string The joined path
     */
    protected function joinPath(string ...$parts): string
    {
        return implode(DIRECTORY_SEPARATOR, array_map(fn ($part) => rtrim($part, DIRECTORY_SEPARATOR), $parts));
    }
}
