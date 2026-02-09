<?php

namespace JobMetric\PackageTesterComposerPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use JobMetric\PackageTesterComposerPlugin\Discoverers\Discoverer;
use JobMetric\PackageTesterComposerPlugin\Extra\ConfigExtra;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    protected ?Composer $composer = null;
    protected ?IOInterface $io = null;
    protected ?Discoverer $discoverer = null;
    protected ?ConfigExtra $configExtra = null;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->discoverer = new Discoverer($composer);
        $this->configExtra = new ConfigExtra($this->getBasePath());
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $basePath = $this->getBasePath();
        (new ConfigExtra($basePath))->clear();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::PRE_AUTOLOAD_DUMP => 'onPreAutoloadDump'
        ];
    }

    /**
     * Before autoload dump - inject test namespaces into root package
     */
    public function onPreAutoloadDump(Event $event): void
    {
        if (!$this->ensureInitialized($event->getComposer(), $event->getIO())) {
            return;
        }

        $this->io->write('<info>Package Tester:</info> Discovering and registering test namespaces...');

        // Discover packages
        $packages = $this->discoverer->discover();

        if (empty($packages)) {
            $this->io->write('<comment>Package Tester:</comment> No packages with tests found.');
            return;
        }

        // Keep discovered packages available for runtime command usage.
        $this->configExtra->save($packages);

        // Inject namespaces into root package's autoload-dev
        $this->injectAutoloadDev($packages);

        $count = count($packages);
        $this->io->write("<info>Package Tester:</info> Registered {$count} package(s) test namespaces.");
    }

    /**
     * Inject discovered autoload-dev namespaces into root package
     */
    protected function injectAutoloadDev(array $packages): void
    {
        $rootPackage = $this->composer->getPackage();
        $autoloadDev = $rootPackage->getDevAutoload();

        if (!isset($autoloadDev['psr-4'])) {
            $autoloadDev['psr-4'] = [];
        }

        $injectedCount = 0;

        foreach ($packages as $packageName => $package) {
            $packagePath = $package['path'] ?? '';
            $packageAutoloadDev = $package['autoload_dev'] ?? [];

            foreach ($packageAutoloadDev as $namespace => $paths) {
                // Handle both string and array paths
                $paths = (array) $paths;

                foreach ($paths as $path) {
                    // Build full path correctly
                    $fullPath = rtrim($packagePath, '/') . '/' . ltrim($path, '/');

                    // Verify directory exists
                    if (!is_dir($fullPath)) {
                        if ($this->io->isVerbose()) {
                            $this->io->write("  <comment>Warning: Directory not found: {$fullPath}</comment>");
                        }
                        continue;
                    }

                    // Normalize namespace (ensure trailing backslash)
                    $ns = rtrim($namespace, '\\') . '\\';

                    // Get relative path from project root
                    $relativePath = $this->getRelativePath($fullPath);

                    // Add to autoload-dev psr-4
                    if (!isset($autoloadDev['psr-4'][$ns])) {
                        $autoloadDev['psr-4'][$ns] = $relativePath;
                        $injectedCount++;

                        if ($this->io->isVerbose()) {
                            $this->io->write("  + {$ns} => {$relativePath}");
                        }
                    } else if ($this->io->isVeryVerbose()) {
                        $this->io->write("  <comment>Skip (already exists): {$ns}</comment>");
                    }
                }
            }
        }

        // Update root package's autoload-dev
        $rootPackage->setDevAutoload($autoloadDev);

        if ($this->io->isVerbose()) {
            $this->io->write("<info>Package Tester:</info> Injected {$injectedCount} namespace(s) into autoload-dev.");
        }
    }

    /**
     * Get relative path from project base
     */
    protected function getRelativePath(string $absolutePath): string
    {
        $basePath = $this->getBasePath();
        $realBase = realpath($basePath);
        $realPath = realpath($absolutePath);

        if ($realBase && $realPath && str_starts_with($realPath, $realBase)) {
            return ltrim(substr($realPath, strlen($realBase)), '/');
        }

        return $absolutePath;
    }

    protected function getBasePath(): string
    {
        if ($this->composer === null) {
            return getcwd() ?: '';
        }

        return dirname($this->composer->getConfig()->get('vendor-dir'));
    }

    protected function ensureInitialized(?Composer $composer = null, ?IOInterface $io = null): bool
    {
        if ($this->composer === null && $composer !== null) {
            $this->composer = $composer;
        }

        if ($this->io === null && $io !== null) {
            $this->io = $io;
        }

        if ($this->composer === null || $this->io === null) {
            return false;
        }

        if ($this->discoverer === null) {
            $this->discoverer = new Discoverer($this->composer);
        }

        if ($this->configExtra === null) {
            $this->configExtra = new ConfigExtra($this->getBasePath());
        }

        return true;
    }
}
