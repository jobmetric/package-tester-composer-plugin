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

/**
 * Class Plugin
 *
 * Composer plugin that automatically discovers and registers test namespaces
 * from packages into the root package's autoload-dev configuration.
 *
 * This plugin hooks into Composer's pre-autoload-dump event to inject
 * PSR-4 autoload mappings for package tests, enabling seamless test execution
 * across multiple packages in a monorepo or development environment.
 *
 * @package JobMetric\PackageTesterComposerPlugin
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * The Composer instance.
     *
     * @var Composer|null
     */
    protected ?Composer $composer = null;

    /**
     * The IO interface for console output.
     *
     * @var IOInterface|null
     */
    protected ?IOInterface $io = null;

    /**
     * The package discoverer instance.
     *
     * @var Discoverer|null
     */
    protected ?Discoverer $discoverer = null;

    /**
     * The configuration extra handler instance.
     *
     * @var ConfigExtra|null
     */
    protected ?ConfigExtra $configExtra = null;

    /**
     * Activate the plugin.
     *
     * Called when the plugin is activated. Initializes the composer instance,
     * IO interface, discoverer, and configuration handler.
     *
     * @param Composer    $composer The Composer instance
     * @param IOInterface $io       The IO interface for console output
     *
     * @return void
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->discoverer = new Discoverer($composer);
        $this->configExtra = new ConfigExtra($this->getBasePath());

        $this->io->write("PackageTester Plugin activated");
    }

    /**
     * Deactivate the plugin.
     *
     * Called when the plugin is deactivated. Currently performs no cleanup actions.
     *
     * @param Composer    $composer The Composer instance
     * @param IOInterface $io       The IO interface for console output
     *
     * @return void
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // No cleanup needed on deactivation
    }

    /**
     * Uninstall the plugin.
     *
     * Called when the plugin is being uninstalled. Clears any persisted
     * configuration files created by the plugin.
     *
     * @param Composer    $composer The Composer instance
     * @param IOInterface $io       The IO interface for console output
     *
     * @return void
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $basePath = $this->getBasePath();
        (new ConfigExtra($basePath))->clear();
    }

    /**
     * Get the list of subscribed events.
     *
     * Returns an array of event names this plugin subscribes to,
     * along with the method names to be called when those events are fired.
     *
     * @return array<string, string> Array of event names and their handler methods
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::PRE_AUTOLOAD_DUMP => 'onPreAutoloadDump',
        ];
    }

    /**
     * Handle the pre-autoload-dump event.
     *
     * Discovers packages with test namespaces and injects them into
     * the root package's autoload-dev configuration before the autoload
     * files are generated.
     *
     * @param Event $event The Composer script event
     *
     * @return void
     */
    public function onPreAutoloadDump(Event $event): void
    {
        if (!$this->ensureInitialized($event->getComposer(), $event->getIO())) {
            return;
        }

        $this->io->write('<info>Package Tester:</info> Discovering and registering test namespaces...');

        // Discover packages with test configurations
        $packages = $this->discoverer->discover();

        if (empty($packages)) {
            $this->io->write('<comment>Package Tester:</comment> No packages with tests found.');

            return;
        }

        // Persist discovered packages for runtime command usage
        $this->configExtra->save($packages);

        // Inject namespaces into root package's autoload-dev
        $this->injectAutoloadDev($packages);

        $count = count($packages);
        $this->io->write("<info>Package Tester:</info> Registered {$count} package(s) test namespaces.");
    }

    /**
     * Inject discovered autoload-dev namespaces into root package.
     *
     * Iterates through discovered packages and adds their test namespace
     * mappings to the root package's PSR-4 autoload-dev configuration.
     *
     * @param array<string, array{path: string, autoload_dev: array<string, string|array>}> $packages Array of discovered packages
     *
     * @return void
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

                    // Normalize namespace with trailing backslash
                    $ns = rtrim($namespace, '\\') . '\\';

                    // Get relative path from project root
                    $relativePath = $this->getRelativePath($fullPath);

                    // Add to autoload-dev psr-4 if not already exists
                    if (!isset($autoloadDev['psr-4'][$ns])) {
                        $autoloadDev['psr-4'][$ns] = $relativePath;
                        $injectedCount++;

                        if ($this->io->isVerbose()) {
                            $this->io->write("  + {$ns} => {$relativePath}");
                        }
                    } elseif ($this->io->isVeryVerbose()) {
                        $this->io->write("  <comment>Skip (already exists): {$ns}</comment>");
                    }
                }
            }
        }

        // Update root package's autoload-dev configuration
        $rootPackage->setDevAutoload($autoloadDev);

        if ($this->io->isVerbose()) {
            $this->io->write("<info>Package Tester:</info> Injected {$injectedCount} namespace(s) into autoload-dev.");
        }
    }

    /**
     * Get relative path from project base directory.
     *
     * Converts an absolute path to a path relative to the project's base directory.
     * If the path cannot be made relative, returns the original absolute path.
     *
     * @param string $absolutePath The absolute path to convert
     *
     * @return string The relative path or original absolute path if conversion fails
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

    /**
     * Get the base path of the project.
     *
     * Returns the directory containing the vendor folder, which is typically
     * the project root directory. Falls back to current working directory
     * if Composer is not initialized.
     *
     * @return string The project base path
     */
    protected function getBasePath(): string
    {
        if ($this->composer === null) {
            return getcwd() ?: '';
        }

        return dirname($this->composer->getConfig()->get('vendor-dir'));
    }

    /**
     * Ensure the plugin is properly initialized.
     *
     * Initializes or updates the internal state with provided Composer and IO instances.
     * Creates the discoverer and config extra instances if they don't exist.
     *
     * @param Composer|null    $composer The Composer instance to use
     * @param IOInterface|null $io       The IO interface to use
     *
     * @return bool True if initialization successful, false otherwise
     */
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
