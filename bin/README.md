# Bin Scripts Documentation

This directory contains various utility scripts for the SeQura Magento2 Core module development environment. All scripts are designed to work with Docker containers to maintain consistency across different development environments.

## Scripts Overview

### 🐋 Docker & Magento Core Scripts

#### `composer`
**Description:** Runs Composer commands inside the Magento Docker container.  
**Usage:** `./bin/composer [composer-command] [options]`  
**Parameters:** Any valid Composer command and options  
**Examples:**
- `./bin/composer install` - Install dependencies
- `./bin/composer require vendor/package` - Add a new package
- `./bin/composer update` - Update all dependencies

#### `magento`
**Description:** Executes Magento CLI commands inside the Docker container.  
**Usage:** `./bin/magento [magento-command] [options]`  
**Parameters:** Any valid Magento CLI command and options  
**Examples:**
- `./bin/magento setup:upgrade` - Run database upgrades
- `./bin/magento cache:flush` - Clear all caches
- `./bin/magento module:enable Sequra_Core` - Enable a module

#### `n98-magerun2`
**Description:** Runs n98-magerun2 commands for advanced Magento management.  
**Usage:** `./bin/n98-magerun2 [command] [options]`  
**Parameters:** Any valid n98-magerun2 command and options  
**Examples:**
- `./bin/n98-magerun2 sys:info` - Display system information
- `./bin/n98-magerun2 dev:module:list` - List all modules
- `./bin/n98-magerun2 cache:clean` - Clean cache

#### `update-sequra`
**Description:** Updates the SeQura Core module in the docker with the current code in the workinng directory.
**Usage:** `./bin/update-sequra`
**Parameters:** None  
**What it does:**
- Removes the current sequra/magento2-core package
- Clears composer cache
- Reinstalls the latest version
- Enables the module and runs setup upgrade

#### `update-integration-core-ui`
**Description:** Updates the assets required for the integration core UI.  
**Usage:** `./bin/update-integration-core-ui`  
**Parameters:** None  
**What it does:**
- Runs `npm install`
- Runs `npm update`
- Copies resources using `npm run copy-resources`

---

### 🧪 Code Quality & Testing Scripts

#### `phpcs`
**Description:** Runs PHP CodeSniffer to check code standards compliance.  
**Usage:** `./bin/phpcs [options]`  
**Parameters:** Any valid phpcs options (uses .phpcs.xml.dist by default)  
**Examples:**
- `./bin/phpcs` - Check all files
- `./bin/phpcs --report=summary` - Show summary report

#### `phpcbf`
**Description:** Runs PHP Code Beautifier and Fixer to automatically fix code standards issues.  
**Usage:** `./bin/phpcbf`  
**Parameters:** Uses .phpcs.xml.dist configuration automatically  
**Note:** Automatically fixes coding standard violations where possible

#### `phpstan`
**Description:** Runs PHPStan static analysis on the codebase.  
**Usage:** `./bin/phpstan [options]`  
**Parameters:** Any valid PHPStan options  
**Examples:**
- `./bin/phpstan` - Run analysis with default settings
- `./bin/phpstan --level=5` - Run with specific analysis level

#### `playwright`
**Description:** Runs Playwright end-to-end tests.  
**Usage:** `./bin/playwright [options]`  
**Parameters:** Any valid Playwright test options  
**Examples:**
- `./bin/playwright` - Run all tests in headless mode
- `./bin/playwright --headed` - Run tests with browser UI
- `./bin/playwright --ui` - Run tests with Playwright UI

**Special features:**
- Waits for ngrok tunnel to be ready
- Supports both headless and headed modes
- Uses environment variables from .env file

---

### 🛠️ Development Tools

#### `npm`
**Description:** Runs npm commands using a Node.js Alpine Docker container.  
**Usage:** `./bin/npm [npm-command] [options]`  
**Parameters:** Any valid npm command and options  
**Examples:**
- `./bin/npm install` - Install Node.js dependencies
- `./bin/npm run build` - Run build script
- `./bin/npm test` - Run tests

#### `xdebug`
**Description:** Toggles Xdebug functionality in the Docker container.  
**Usage:** `./bin/xdebug [options]`  
**Parameters:** Any options accepted by the toggle-xdebug script  
**Examples:**
- `./bin/xdebug` - Toggle Xdebug on/off
- `./bin/xdebug on` - Enable Xdebug
- `./bin/xdebug off` - Disable Xdebug

---

### 🎨 Hyva Theme Scripts

#### `install-hyva`
**Description:** Installs Hyva theme and SeQura Core compatibility module.  
**Usage:** `./bin/install-hyva [version]`  
**Parameters:**
- `version` (optional): SeQura Core Hyva compatibility version (default: dev-master)  
**Examples:**
- `./bin/install-hyva` - Install with dev-master version
- `./bin/install-hyva v1.2.3` - Install specific version

**Note** Access to Hyva gitlab is required add your public key to your account and create a docker-compose.override.yml file with

```yaml
service:
    magento:
        volumes:
        - ~/.ssh:/var/www/.ssh:ro
```

`
---

## Prerequisites

Before using these scripts, ensure you have:

1. **Docker and Docker Compose** installed and running
2. **Proper environment configuration** (`.env` file)
3. **Magento Docker containers** up and running
4. **Appropriate permissions** to execute shell scripts

## Environment Setup

Make sure your `.env` file contains the necessary configuration variables, especially:
- `M2_URL` or `PUBLIC_URL` for Playwright tests
- Database connection details
- Docker container configurations

## Getting Started

1. Make sure all scripts are executable:
   ```bash
   chmod +x bin/*
   ```

2. Start your Docker environment:
   ```bash
   docker-compose up -d
   ```

3. Run any script from the project root:
   ```bash
   ./bin/[script-name] [parameters]
   ```

## Troubleshooting

- **Permission issues:** Ensure scripts have execute permissions
- **Docker issues:** Verify containers are running with `docker-compose ps`
- **Network issues:** Check if ports are properly exposed and not conflicting
- **Environment variables:** Verify your `.env` file contains all required variables

For more detailed information about specific tools, refer to their respective documentation:
- [Magento CLI Documentation](https://devdocs.magento.com/guides/v2.4/config-guide/cli/config-cli.html)
- [Composer Documentation](https://getcomposer.org/doc/)
- [PHPStan Documentation](https://phpstan.org/)
- [Playwright Documentation](https://playwright.dev/)