<?php

namespace JobMetric\PackageTesterComposerPlugin\Discoverers;

class PackageAnalyzer
{
    /**
     * Default test directory names
     */
    protected const DEFAULT_TEST_DIRS = [
        'tests',
        'test',
        'Tests',
        'Test',
    ];

    /**
     * Sub-directories to ignore during discovery.
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
     * Create a new discovery instance
     */
    public function __construct() {}

    /**
     * Discover package test configuration
     *
     * @param string $packagePath
     * @return array|null
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

        $result = [
            'name' => $packageName,
            'version' => $packageVersion,
            'path' => $packagePath,
            'tests' => $tests,
            'options' => (array) ($testerConfig['options'] ?? []),
            'autoload_dev' => $autoloadDev,
        ];

        return $result;
    }

    /**
     * Discover test suites from config or auto-detect
     *
     * @param string $packagePath
     * @param array $testerConfig
     * @return array
     */
    protected function discoverTests(string $packagePath, array $testerConfig): array
    {
        $tests = $testerConfig['tests'] ?? [];

        // If tests defined in config, validate them
        if (!empty($tests)) {
            return $this->validateTests($packagePath, $tests);
        }

        // Auto-discover test directories
        return $this->autoDiscoverTests($packagePath);
    }

    /**
     * Validate configured test paths
     *
     * @param string $packagePath
     * @param array $tests
     * @return array
     */
    protected function validateTests(string $packagePath, array $tests): array
    {
        $valid = [];

        foreach ($tests as $test) {
            if (is_string($test)) {
                $test = ['path' => $test];
            }

            if (!is_array($test)) {
                continue;
            }

            $path = $test['path'] ?? 'tests';
            $fullPath = $this->joinPath($packagePath, $path);

            if (!is_dir($fullPath)) {
                continue;
            }

            $valid[] = [
                'name' => $test['name'] ?? basename($path),
                'path' => $path,
                'namespace' => $test['namespace'] ?? null,
                'options' => (array) ($test['options'] ?? []),
                'filter' => $test['filter'] ?? null,
            ];
        }

        return $valid;
    }

    /**
     * Auto-discover test directories
     *
     * @param string $packagePath
     * @return array
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

        if (!empty($subDirs)) {
            foreach ($subDirs as $subDir) {
                $tests[] = [
                    'name' => $subDir,
                    'path' => $testDir . '/' . $subDir,
                    'namespace' => null,
                    'options' => [],
                    'filter' => null,
                ];
            }
        } else {
            // No sub-directories, use main test dir
            $tests[] = [
                'name' => 'Tests',
                'path' => $testDir,
                'namespace' => null,
                'options' => [],
                'filter' => null,
            ];
        }

        return $tests;
    }

    /**
     * Find main test directory
     *
     * @param string $packagePath
     * @param array|null $possiblePaths
     * @return string|null
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
     * Find test sub-directories (Unit, Feature, Integration, etc.)
     *
     * @param string $testPath
     * @return array
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

            if (!in_array($dirName, $found)) {
                $found[] = $dirName;
            }
        }

        return $found;
    }

    /**
     * Read composer.json from package
     *
     * @param string $packagePath
     * @return array|null
     */
    protected function readComposerJson(string $packagePath): ?array
    {
        $file = $this->joinPath($packagePath, 'composer.json');

        if (!is_file($file)) {
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
     * Join path segments
     *
     * @param string ...$parts
     * @return string
     */
    protected function joinPath(string ...$parts): string
    {
        return implode(DIRECTORY_SEPARATOR, array_map(
            fn($part) => rtrim($part, DIRECTORY_SEPARATOR),
            $parts
        ));
    }
}
