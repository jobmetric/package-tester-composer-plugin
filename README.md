[contributors-shield]: https://img.shields.io/github/contributors/jobmetric/package-tester-composer-plugin.svg?style=for-the-badge
[contributors-url]: https://github.com/jobmetric/package-tester-composer-plugin/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/jobmetric/package-tester-composer-plugin.svg?style=for-the-badge&label=Fork
[forks-url]: https://github.com/jobmetric/package-tester-composer-plugin/network/members
[stars-shield]: https://img.shields.io/github/stars/jobmetric/package-tester-composer-plugin.svg?style=for-the-badge
[stars-url]: https://github.com/jobmetric/package-tester-composer-plugin/stargazers
[license-shield]: https://img.shields.io/github/license/jobmetric/package-tester-composer-plugin.svg?style=for-the-badge
[license-url]: https://github.com/jobmetric/package-tester-composer-plugin/blob/master/LICENCE.md
[linkedin-shield]: https://img.shields.io/badge/-LinkedIn-blue.svg?style=for-the-badge&logo=linkedin&colorB=555
[linkedin-url]: https://linkedin.com/in/majidmohammadian
[php-shield]: https://img.shields.io/badge/PHP->=8.0.1-777BB4?style=for-the-badge&logo=php&logoColor=white
[composer-shield]: https://img.shields.io/badge/Composer-Plugin-885630?style=for-the-badge&logo=composer&logoColor=white

[![Contributors][contributors-shield]][contributors-url]
[![Forks][forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![MIT License][license-shield]][license-url]
[![LinkedIn][linkedin-shield]][linkedin-url]
![PHP Version][php-shield]
![Composer Plugin][composer-shield]

# Package Tester Composer Plugin

A powerful Composer plugin designed to manage local package development workflow by automatically discovering and registering test namespaces from packages into the root package's autoload-dev configuration.

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [How It Works](#how-it-works)
- [Configuration](#configuration)
- [Architecture](#architecture)
- [Usage](#usage)
- [API Reference](#api-reference)
- [Contributing](#contributing)
- [License](#license)

## Overview

`package-tester-composer-plugin` is a Composer plugin that streamlines the testing workflow for Laravel packages in a monorepo or multi-package development environment. It automatically discovers packages with test configurations and injects their PSR-4 autoload mappings into the root package, enabling seamless test execution across multiple packages.

### Why Use This Plugin?

When developing multiple packages locally, managing autoload configurations for tests can become tedious. This plugin automates the process by:

- Scanning your vendor directory for packages with `package-tester.json` configuration files
- Extracting test namespace mappings from each package's `composer.json`
- Injecting these mappings into the root package's `autoload-dev` configuration
- Persisting configuration for runtime command usage

## Features

- 🔍 **Automatic Package Discovery** - Scans vendor directory for packages with test configurations
- 📝 **PSR-4 Namespace Injection** - Automatically registers test namespaces in autoload-dev
- 🎯 **Smart Test Detection** - Auto-discovers test directories (Unit, Feature, Integration, etc.)
- 💾 **Configuration Persistence** - Saves discovered packages for runtime usage
- 🔧 **Flexible Configuration** - Supports both explicit and auto-discovered test paths
- 🧹 **Clean Uninstall** - Removes all persisted configurations when uninstalled
- 📦 **Laravel Integration** - Includes a Laravel service provider for easy integration

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | >= 8.0.1 |
| Composer | >= 2.0 |
| composer/composer | ^2.9 |

## Installation

Install the plugin via Composer:

```bash
composer require jobmetric/package-tester-composer-plugin --dev
```

The plugin will automatically activate and start discovering packages on subsequent `composer install` or `composer update` commands.

## How It Works

### Plugin Lifecycle

```
┌─────────────────────────────────────────────────────┐
│                 Composer Event Flow                 │
├─────────────────────────────────────────────────────┤
│                                                     │
│  composer install/update                            │
│           │                                         │
│           ▼                                         │
│  ┌──────────────────┐                               │
│  │ Plugin Activated │                               │
│  └────────┬─────────┘                               │
│           │                                         │
│           ▼                                         │
│  ┌───────────────────────────────────────────────┐  │
│  │         PRE_AUTOLOAD_DUMP Event               │  │
│  │  ┌─────────────────────────────────────────┐  │  │
│  │  │ 1. Discover packages with tests         │  │  │
│  │  │ 2. Extract autoload-dev configurations  │  │  │
│  │  │ 3. Inject namespaces into root package  │  │  │
│  │  │ 4. Persist configuration to JSON file   │  │  │
│  │  └─────────────────────────────────────────┘  │  │
│  └───────────────────────────────────────────────┘  │
│           │                                         │
│           ▼                                         │
│  ┌─────────────────────┐                            │
│  │ Autoload files      │                            │
│  │ generated with      │                            │
│  │ injected namespaces │                            │
│  └─────────────────────┘                            │
│                                                     │
└─────────────────────────────────────────────────────┘
```

### Discovery Process

1. **Package Scanning**: The plugin scans the `vendor` directory for packages
2. **Configuration Check**: Each package is checked for a `package-tester.json` file
3. **Metadata Extraction**: Package information and autoload-dev mappings are extracted
4. **Namespace Injection**: Test namespaces are injected into the root package's autoload-dev
5. **Persistence**: Configuration is saved to `.package-tester/config.json` for runtime use

## Configuration

### Package Configuration (package-tester.json)

To enable test discovery for your package, create a `package-tester.json` file in your package root:

```json
{
    "runner": {
        "enabled": true
    },
    "namespace": {
        "path": "tests",
        "option": [],
        "filter": null
    },
    "dependency-packages": []
}
```

#### Configuration Options

| Option                | Type         | Description                                        |
|-----------------------|--------------|----------------------------------------------------|
| `runner.enabled`      |  boolean     | Enable/disable test runner for this package        |
| `namespace.path`      | string       | Path to test directory (relative to package root)  |
| `namespace.option`    | array        | Additional PHPUnit options                         |
| `namespace.filter`    | string\|null | Filter pattern for tests                           |
| `dependency-packages` | array        | List of dependent packages                         |

### Composer.json autoload-dev

The plugin reads the `autoload-dev` section from each package's `composer.json`:

```json
{
    "autoload-dev": {
        "psr-4": {
            "YourPackage\\Tests\\": "tests/"
        }
    }
}
```

## Architecture

### Directory Structure

```
package-tester-composer-plugin/
├── src/
│   ├── Plugin.php                              # Main plugin class
│   ├── PackageTesterComposerPluginServiceProvider.php  # Laravel service provider
│   ├── Discoverers/
│   │   ├── Discoverer.php                      # Entry point for discovery
│   │   ├── PackageDiscoverer.php               # Scans vendor for packages
│   │   └── PackageAnalyzer.php                 # Analyzes individual packages
│   └── Extra/
│       └── ConfigExtra.php                     # Configuration persistence
├── composer.json
├── CONTRIBUTING.md
├── LICENCE.md
└── README.md
```

### Core Components

#### Plugin (`src/Plugin.php`)

The main plugin class that implements Composer's `PluginInterface` and `EventSubscriberInterface`. It:

- Activates/deactivates the plugin
- Subscribes to the `PRE_AUTOLOAD_DUMP` event
- Coordinates package discovery and namespace injection

#### Discoverer (`src/Discoverers/Discoverer.php`)

Entry point for package discovery operations. Acts as a facade for `PackageDiscoverer`.

#### PackageDiscoverer (`src/Discoverers/PackageDiscoverer.php`)

Scans the vendor directory and extracts test configurations from packages that have `package-tester.json` files.

**Key Methods:**
- `discover()` - Initiates the discovery process
- `getPackages()` - Returns all discovered packages
- `getPackage(string $name)` - Gets a specific package by name
- `hasPackage(string $name)` - Checks if a package exists
- `getTestPaths(string $name)` - Gets test paths for a package

#### PackageAnalyzer (`src/Discoverers/PackageAnalyzer.php`)

Analyzes individual packages to extract test configuration and metadata.

**Features:**
- Auto-discovers test directories (tests, test, Tests, Test)
- Finds test subdirectories (Unit, Feature, Integration, Functional, Api)
- Ignores fixture directories (Fixtures, Stubs, data, etc.)

#### ConfigExtra (`src/Extra/ConfigExtra.php`)

Manages runtime configuration persistence in `.package-tester/config.json`.

**Key Methods:**
- `save(array $packages)` - Persists package configuration
- `load()` - Loads persisted configuration
- `clear()` - Removes persisted configuration

## Usage

### Basic Usage

Once installed, the plugin works automatically. Run composer commands as usual:

```bash
# Install dependencies and trigger package discovery
composer install

# Update dependencies and re-discover packages
composer update

# Regenerate autoload files with verbose output
composer dump-autoload -v
```

### Verbose Output

Use verbose flags to see detailed discovery information:

```bash
# Show registered namespaces
composer dump-autoload -v

# Show skipped namespaces
composer dump-autoload -vv

# Show warnings about missing directories
composer dump-autoload -vvv
```

### Example Output

```
Package Tester: Discovering and registering test namespaces...
  + YourPackage\Tests\ => vendor/yourvendor/yourpackage/tests/
  + AnotherPackage\Tests\ => vendor/yourvendor/anotherpackage/tests/
Package Tester: Registered 2 package(s) test namespaces.
```

### Laravel Integration

The plugin includes a Laravel service provider that registers the `ConfigExtra` class as a singleton:

```php
use JobMetric\PackageTesterComposerPlugin\Extra\ConfigExtra;

// Resolve from container
$configExtra = app(ConfigExtra::class);

// Load discovered packages
$packages = $configExtra->load();
```

## API Reference

### Plugin Class

```php
namespace JobMetric\PackageTesterComposerPlugin;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    // Activate the plugin
    public function activate(Composer $composer, IOInterface $io): void;
    
    // Deactivate the plugin
    public function deactivate(Composer $composer, IOInterface $io): void;
    
    // Uninstall the plugin (clears configuration)
    public function uninstall(Composer $composer, IOInterface $io): void;
    
    // Get subscribed events
    public static function getSubscribedEvents(): array;
    
    // Handle pre-autoload-dump event
    public function onPreAutoloadDump(Event $event): void;
}
```

### PackageDiscoverer Class

```php
namespace JobMetric\PackageTesterComposerPlugin\Discoverers;

class PackageDiscoverer
{
    // Discover all packages with test configuration
    public function discover(): static;
    
    // Get all discovered packages
    public function getPackages(): array;
    
    // Get a specific package by name
    public function getPackage(string $packageName): ?array;
    
    // Check if a package exists
    public function hasPackage(string $packageName): bool;
    
    // Get the count of discovered packages
    public function count(): int;
    
    // Get test paths for a specific package
    public function getTestPaths(string $packageName): array;
}
```

### ConfigExtra Class

```php
namespace JobMetric\PackageTesterComposerPlugin\Extra;

class ConfigExtra
{
    // Save package configuration
    public function save(array $packages): void;
    
    // Load persisted configuration
    public function load(): array;
    
    // Clear persisted configuration
    public function clear(): void;
}
```

### Persisted Configuration Structure

The plugin persists discovered package configurations to `.package-tester/config.json`. This file contains only the `autoload_dev` mappings:

```json
{
    "jobmetric/laravel-env-modifier": {
        "autoload_dev": {
            "JobMetric\\EnvModifier\\Tests\\": "tests/"
        }
    },
    "vendor/another-package": {
        "autoload_dev": {
            "Vendor\\AnotherPackage\\Tests\\": "tests/"
        }
    }
}
```

## Troubleshooting

### Common Issues

**Package not discovered:**
- Ensure `package-tester.json` exists in the package root
- Check that `runner.enabled` is not set to `false`
- Verify the package is installed in the vendor directory

**Namespace not injected:**
- Check that the test directory exists
- Ensure `autoload-dev` is properly configured in `composer.json`
- Run `composer dump-autoload -v` to see verbose output

**Configuration not persisted:**
- Check write permissions for the project directory
- Ensure `.package-tester` directory can be created

## Contributing

Thank you for considering contributing to Package Tester Composer Plugin! Please read our [Contributing Guide](CONTRIBUTING.md) for details on our code of conduct and the process for submitting pull requests.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/jobmetric/package-tester-composer-plugin.git

# Install dependencies
composer install

# Run tests
composer test
```

## License

The Package Tester Composer Plugin is open-sourced software licensed under the [MIT license](LICENCE.md).

## Authors

- **Majid Mohammadian** - *Full Stack Developer* - [LinkedIn](https://www.linkedin.com/in/majidmohammadian/)
- **Matin Bagheri** - *PHP Developer* - [LinkedIn](https://www.linkedin.com/in/bagherimatin/)

## Support

- 📧 Email: jobmetricnet@gmail.com
- 🐛 Issues: [GitHub Issues](https://github.com/jobmetric/package-tester-composer-plugin/issues)
- 📚 Documentation: [https://jobmetric.github.io/packages/package-tester-composer-plugin/](https://jobmetric.github.io/packages/package-tester-composer-plugin/)
