# Plugin System Documentation

## Overview

The AquaRelay plugin system allows you to extend the proxy server with custom functionality. Plugins are loaded automatically from the `plugins` directory.

## Creating a Plugin

### Step 1: Create Plugin Structure

Create a directory for your plugin in the `plugins` folder:

```
plugins/
└── MyPlugin/
    ├── plugin.yml
    └── src/
        └── MyPlugin.php
```

### Step 2: Create plugin.yml

The `plugin.yml` file contains metadata about your plugin:

```yaml
name: MyPlugin
version: 1.0.0
main: MyPlugin\MyPlugin
description: My awesome plugin
authors:
  - YourName
website: https://example.com
dependencies: []
soft-dependencies: []
```

**Fields:**
- `name`: Plugin name (must be unique)
- `version`: Plugin version (semver format)
- `main`: Full class path to your main plugin class
- `description`: Short description of the plugin
- `authors`: List of plugin authors
- `website`: Plugin website (optional)
- `dependencies`: Hard dependencies (plugin won't load if missing)
- `soft-dependencies`: Soft dependencies (plugin loads even if missing)

### Step 3: Create Main Plugin Class

Create your plugin's main class extending the `Plugin` class:

```php
<?php

namespace MyPlugin;

use aquarelay\plugin\PluginBase;

class MyPlugin extends PluginBase {

    public function onLoad() : void
    {
        // Called when the plugin is loaded
        $this->getServer()->getLogger()->info("MyPlugin loaded!");
    }

    public function onEnable() : void
    {
        // Called when the plugin is enabled
        $this->getServer()->getLogger()->info("MyPlugin enabled!");
    }

    public function onDisable() : void
    {
        // Called when the plugin is disabled
        $this->getServer()->getLogger()->info("MyPlugin disabled!");
    }
}
```

## Plugin Class Methods

### Lifecycle Methods

- `onLoad() : void` - Called when the plugin is loaded (before enabling)
- `onEnable() : void` - Called when the plugin is enabled
- `onDisable() : void` - Called when the plugin is disabled

### Accessor Methods

- `getServer() : ProxyServer` - Get the proxy server instance
- `getName() : string` - Get the plugin name
- `getVersion() : string` - Get the plugin version
- `getPluginDescription() : string` - Get the plugin description
- `getAuthors() : array` - Get the plugin authors
- `getDescription() : PluginDescription` - Get the full plugin description object

### Status Methods

- `isEnabled() : bool` - Check if the plugin is enabled
- `setEnabled(bool $enabled) : void` - Enable or disable the plugin

## Using the Server Instance

Access the proxy server and its features:

```php
public function onEnable() : void
{
    $server = $this->getServer();
    
    // Get the logger
    $logger = $server->getLogger();
    $logger->info("Plugin is enabled!");
    
    // Access other server components
    $config = $server->getConfig();
    $playerManager = $server->getPlayerManager();
}
```

## Plugin with Composer Dependencies

If your plugin needs external dependencies via Composer:

1. Create a `composer.json` in your plugin directory
2. Install dependencies: `composer install`
3. The plugin loader will automatically include your `vendor/autoload.php`

Example `composer.json`:

```json
{
    "name": "vendor/myplugin",
    "require": {
        "some/package": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "MyPlugin\\": "src/"
        }
    }
}
```

## Plugin Distribution

Plugins can be distributed as:

1. **Directory**: Simple folder structure in the `plugins` directory
2. **Phar Archive**: Packaged as a `.phar` file in the `plugins` directory

## Example: Full Plugin

```php
<?php

namespace MyAwesomePlugin;

use aquarelay\plugin\PluginBase;

class MyAwesomePlugin extends PluginBase {

    public function onLoad() : void
    {
        $this->getServer()->getLogger()->info(
            "Loading " . $this->getName() . " v" . $this->getVersion()
        );
    }

    public function onEnable() : void
    {
        $this->getServer()->getLogger()->info(
            "Enabling " . $this->getName()
        );
        
        // Initialize your plugin here
        $this->setupConfiguration();
        $this->registerListeners();
    }

    public function onDisable() : void
    {
        $this->getServer()->getLogger()->info(
            "Disabling " . $this->getName()
        );
        
        // Cleanup code
    }

    private function setupConfiguration() : void
    {
        // Load and setup configuration
    }

    private function registerListeners() : void
    {
        // Register event listeners
    }
}
```

## Plugin Manager Usage

The PluginManager handles plugin loading and lifecycle:

```php
// In ProxyServer
$pluginManager = $this->getPluginManager();

// Load all plugins
$pluginManager->loadPlugins();

// Enable all plugins
$pluginManager->enablePlugins();

// Get a specific plugin
$plugin = $pluginManager->getPlugin("MyPlugin");

// Check if a plugin is loaded
if ($pluginManager->isPluginLoaded("MyPlugin")) {
    // Plugin is available
}

// Get all plugins
$allPlugins = $pluginManager->getPlugins();

// Disable all plugins
$pluginManager->disablePlugins();
```

## Best Practices

1. **Use PSR-4 Autoloading**: Organize your code with proper namespacing
2. **Handle Dependencies**: Declare all required plugins in `plugin.yml`
3. **Log Appropriately**: Use the server logger for debugging and information
4. **Clean Up Resources**: Free up resources in `onDisable()`
5. **Version Your Plugin**: Use semantic versioning
6. **Document Your Code**: Provide clear documentation for plugin users
7. **Test Thoroughly**: Test your plugin with different proxy configurations

## Troubleshooting

### Plugin Not Loading

- Check that the main class extends `Plugin`
- Verify the `main` field in `plugin.yml` matches your class path
- Ensure `plugin.yml` is valid YAML syntax
- Check the server logs for specific error messages

### Plugin Not Enabling

- Check for unmet dependencies
- Review the server logs for errors during `onEnable()`
- Verify the plugin has all required permissions
