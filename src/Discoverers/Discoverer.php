<?php

namespace JobMetric\PackageTesterComposerPlugin\Discoverers;

use Composer\Composer;

/**
 * Class Discoverer
 *
 * Entry point for package discovery operations.
 * Acts as a facade for the PackageDiscoverer to simplify usage within the plugin.
 *
 * @package JobMetric\PackageTesterComposerPlugin\Discoverers
 */
class Discoverer
{
    /**
     * The project base path.
     *
     * @var string
     */
    protected string $basePath;
    
    /**
     * Create a new discoverer instance.
     *
     * @param Composer $composer The Composer instance to extract base path from
     */
    public function __construct(Composer $composer)
    {
        $this->basePath = dirname($composer->getConfig()->get('vendor-dir'));
    }
    
    /**
     * Discover all packages with test configurations.
     *
     * Scans the vendor directory and returns an array of packages
     * that have package-tester.json configuration files.
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
     * }> Array of discovered packages indexed by package name
     */
    public function discover(): array
    {
        return (new PackageDiscoverer($this->basePath))->discover()->getPackages();
    }
}
