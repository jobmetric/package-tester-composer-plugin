<?php

namespace JobMetric\PackageTesterComposerPlugin\Discoverers;

use Composer\Composer;

class Discoverer
{
    /**
     * Composer instance
     *
     * @var Composer
     */
    protected Composer $composer;

    /**
     * Base path
     *
     * @var string
     */
    protected string $basePath;

    /**
     * Create a new discoverer instance
     *
     * @param Composer $composer
     */
    public function __construct(Composer $composer)
    {
        $this->composer = $composer;
        $this->basePath = dirname($composer->getConfig()->get('vendor-dir'));
    }

    /**
     * Discover packages with tests
     *
     * @return array
     */
    public function discover(): array
    {
        $discoverer = new PackageDiscoverer($this->basePath);
        $discoverer->discover();

        return $discoverer->getPackages();
    }
}
