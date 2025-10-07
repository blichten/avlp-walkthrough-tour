# AVLP Walkthrough Tour - Testing Setup Guide

## Overview
This guide provides comprehensive instructions for setting up and running the testing framework for the AVLP Walkthrough Tour plugin, following VLP development standards.

## Prerequisites

### Required Software
- PHP 7.4 or higher
- WordPress 5.0 or higher
- PHPUnit 9.0 or higher
- Node.js 16.0 or higher
- npm or yarn package manager

### Required WordPress Plugins
- WordPress Test Suite
- VLP General Plugin (_avlp-general)

## Installation

### 1. Install PHPUnit
```bash
# Install PHPUnit globally
composer global require phpunit/phpunit

# Or install locally in plugin directory
cd /path/to/avlp-walkthrough-tour
composer install
```

### 2. Install Playwright for E2E Testing
```bash
# Install Playwright
npm install @playwright/test

# Install browsers
npx playwright install
```

### 3. Set Up WordPress Test Environment
```bash
# Download WordPress test suite
svn co https://develop.svn.wordpress.org/trunk/tests/phpunit/ /tmp/wordpress-tests-lib

# Or clone from GitHub
git clone https://github.com/WordPress/wordpress-develop.git /tmp/wordpress-develop
```

## Configuration

### 1. PHPUnit Configuration
Create `phpunit.xml` in the plugin root:

```xml
<?xml version="1.0"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         cacheResultFile=".phpunit.result.cache"
         executionOrder="depends,defects"
         requireCoverageMetadata="true"
         beStrictAboutCoverageMetadata="true"
         beStrictAboutOutputDuringTests="true"
         failOnRisky="true"
         failOnWarning="true"
         verbose="true">
    <testsuites>
        <testsuite name="unit">
            <directory>tests/unit</directory>
        </testsuite>
        <testsuite name="integration">
            <directory>tests/integration</directory>
        </testsuite>
        <testsuite name="e2e">
            <directory>tests/e2e</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">includes</directory>
            <file>default-walkthrough.php</file>
        </include>
    </source>
    <php>
        <const name="WP_TESTS_DOMAIN" value="localhost"/>
        <const name="WP_TESTS_EMAIL" value="admin@example.org"/>
        <const name="WP_TESTS_TITLE" value="Test Blog"/>
        <const name="WP_PHP_BINARY" value="php"/>
        <const name="WP_TESTS_FORCE_KNOWN_BUGS" value="true"/>
    </php>
</phpunit>
```

### 2. Test Bootstrap
Create `tests/bootstrap.php`:

```php
<?php
/**
 * Test bootstrap for AVLP Walkthrough Tour plugin
 */

// Define test environment
define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', __DIR__ . '/../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php');

// Load WordPress test suite
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

// Load plugin
function _manually_load_plugin() {
    require dirname(__DIR__) . '/default-walkthrough.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start WordPress
require $_tests_dir . '/includes/bootstrap.php';

// Activate plugin
activate_plugin('avlp-walkthrough-tour/default-walkthrough.php');
```

### 3. Playwright Configuration
Create `playwright.config.js`:

```javascript
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',
  use: {
    baseURL: 'http://localhost:8080',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
    },
    {
      name: 'Mobile Chrome',
      use: { ...devices['Pixel 5'] },
    },
    {
      name: 'Mobile Safari',
      use: { ...devices['iPhone 12'] },
    },
  ],
  webServer: {
    command: 'php -S localhost:8080',
    port: 8080,
  },
});
```

## Running Tests

### Unit Tests
```bash
# Run all unit tests
./vendor/bin/phpunit tests/unit

# Run specific test file
./vendor/bin/phpunit tests/unit/test-walkthrough-database.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage tests/unit
```

### Integration Tests
```bash
# Run integration tests
./vendor/bin/phpunit tests/integration

# Run specific integration test
./vendor/bin/phpunit tests/integration/test-walkthrough-admin.php
```

### End-to-End Tests
```bash
# Run all E2E tests
npx playwright test

# Run specific E2E test
npx playwright test tests/e2e/tour-functionality.spec.js

# Run with UI mode
npx playwright test --ui

# Run on specific browser
npx playwright test --project=chromium
```

### All Tests
```bash
# Run complete test suite
npm run test

# Run with coverage
npm run test:coverage
```

## Test Structure

### Unit Tests (`tests/unit/`)
- `test-walkthrough-database.php` - Database operations
- `test-walkthrough-frontend.php` - Frontend functionality
- `test-walkthrough-admin.php` - Admin functionality
- `test-walkthrough-shortcodes.php` - Shortcode functionality

### Integration Tests (`tests/integration/`)
- `test-walkthrough-integration.php` - Plugin integration
- `test-walkthrough-api.php` - AJAX API endpoints
- `test-walkthrough-permissions.php` - User permissions

### End-to-End Tests (`tests/e2e/`)
- `tour-functionality.spec.js` - Complete tour workflows
- `admin-interface.spec.js` - Admin interface testing
- `responsive-design.spec.js` - Mobile/responsive testing

## Writing Tests

### Unit Test Example
```php
<?php
/**
 * Unit tests for walkthrough database functionality
 */

class TestWalkthroughDatabase extends WP_UnitTestCase {
    
    public function setUp(): void {
        parent::setUp();
        
        // Create test data
        $this->tour_id = vlp_walkthrough_create_tour([
            'tour_name' => 'Test Tour',
            'tour_description' => 'Test Description',
            'tour_trigger_type' => 'automatic'
        ]);
    }
    
    public function test_create_tour() {
        $this->assertIsInt($this->tour_id);
        $this->assertGreaterThan(0, $this->tour_id);
        
        $tour = vlp_walkthrough_get_tour($this->tour_id);
        $this->assertEquals('Test Tour', $tour->tour_name);
    }
    
    public function test_create_tour_step() {
        $step_id = vlp_walkthrough_create_tour_step([
            'tour_id' => $this->tour_id,
            'step_order' => 1,
            'step_title' => 'Test Step',
            'step_content' => 'Test Content',
            'target_selector' => '#test-element'
        ]);
        
        $this->assertIsInt($step_id);
        
        $steps = vlp_walkthrough_get_tour_steps($this->tour_id);
        $this->assertCount(1, $steps);
    }
    
    public function tearDown(): void {
        // Clean up test data
        vlp_walkthrough_delete_tour($this->tour_id);
        
        parent::tearDown();
    }
}
```

### E2E Test Example
```javascript
import { test, expect } from '@playwright/test';

test.describe('Tour Functionality', () => {
  test('should complete a basic tour', async ({ page }) => {
    // Navigate to test page
    await page.goto('/test-page');
    
    // Wait for tour to appear
    await page.waitForSelector('#vlp-walkthrough-container');
    
    // Check tour is visible
    await expect(page.locator('.vlp-walkthrough-tooltip')).toBeVisible();
    
    // Navigate through tour
    await page.click('.vlp-walkthrough-next');
    await page.click('.vlp-walkthrough-next');
    await page.click('.vlp-walkthrough-next');
    
    // Complete tour
    await page.click('.vlp-walkthrough-next');
    
    // Verify tour is closed
    await expect(page.locator('#vlp-walkthrough-container')).toBeHidden();
  });
  
  test('should handle skip functionality', async ({ page }) => {
    await page.goto('/test-page');
    await page.waitForSelector('#vlp-walkthrough-container');
    
    // Skip tour
    await page.click('.vlp-walkthrough-skip');
    
    // Verify tour is closed
    await expect(page.locator('#vlp-walkthrough-container')).toBeHidden();
  });
});
```

## Continuous Integration

### GitHub Actions Workflow
Create `.github/workflows/walkthrough-regression-tests.yml`:

```yaml
name: Walkthrough Tour Regression Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  unit-tests:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: mbstring, xml, ctype, iconv, intl, pdo_mysql, dom, filter, gd, json
        
    - name: Install dependencies
      run: composer install --no-dev --optimize-autoloader
      
    - name: Run unit tests
      run: ./vendor/bin/phpunit tests/unit --coverage-clover coverage.xml
      
    - name: Upload coverage
      uses: codecov/codecov-action@v3
      with:
        file: coverage.xml

  e2e-tests:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        
    - name: Install dependencies
      run: npm ci
      
    - name: Install Playwright
      run: npx playwright install --with-deps
      
    - name: Run E2E tests
      run: npx playwright test
      
    - name: Upload test results
      uses: actions/upload-artifact@v3
      if: failure()
      with:
        name: playwright-report
        path: playwright-report/
```

## Monitoring

### Production Monitoring
Create `monitoring/walkthrough-functionality-monitor.php`:

```php
<?php
/**
 * Production monitoring for AVLP Walkthrough Tour plugin
 */

class VLPWalkthroughMonitor {
    
    public function check_tour_functionality() {
        $issues = [];
        
        // Check database tables
        if (!$this->check_database_tables()) {
            $issues[] = 'Database tables missing or corrupted';
        }
        
        // Check tour data integrity
        if (!$this->check_tour_data_integrity()) {
            $issues[] = 'Tour data integrity issues detected';
        }
        
        // Check performance
        if (!$this->check_performance()) {
            $issues[] = 'Performance issues detected';
        }
        
        return $issues;
    }
    
    private function check_database_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'avlp_tours',
            $wpdb->prefix . 'avlp_tour_steps',
            $wpdb->prefix . 'avlp_tour_user_tracking'
        ];
        
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                return false;
            }
        }
        
        return true;
    }
    
    private function check_tour_data_integrity() {
        // Check for orphaned steps
        global $wpdb;
        
        $orphaned_steps = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}avlp_tour_steps s 
             LEFT JOIN {$wpdb->prefix}avlp_tours t ON s.tour_id = t.tour_id 
             WHERE t.tour_id IS NULL"
        );
        
        return $orphaned_steps == 0;
    }
    
    private function check_performance() {
        // Check query performance
        $start_time = microtime(true);
        
        vlp_walkthrough_get_active_tours();
        
        $execution_time = microtime(true) - $start_time;
        
        return $execution_time < 0.1; // Should complete in under 100ms
    }
}
```

## Troubleshooting

### Common Issues

1. **PHPUnit not found**
   ```bash
   # Install globally
   composer global require phpunit/phpunit
   
   # Add to PATH
   export PATH="$HOME/.composer/vendor/bin:$PATH"
   ```

2. **WordPress test suite not found**
   ```bash
   # Download manually
   svn co https://develop.svn.wordpress.org/trunk/tests/phpunit/ /tmp/wordpress-tests-lib
   ```

3. **Playwright browser issues**
   ```bash
   # Reinstall browsers
   npx playwright install --force
   ```

4. **Database connection issues**
   - Check `wp-config.php` test database settings
   - Ensure test database exists
   - Verify database user permissions

### Debug Mode
Enable debug mode for detailed test output:

```bash
# PHPUnit debug
./vendor/bin/phpunit --debug tests/unit

# Playwright debug
npx playwright test --debug
```

## Best Practices

1. **Test Isolation**: Each test should be independent and clean up after itself
2. **Descriptive Names**: Use clear, descriptive test and method names
3. **Single Responsibility**: Each test should test one specific behavior
4. **Mock External Dependencies**: Use mocks for external services and APIs
5. **Coverage Goals**: Maintain at least 80% code coverage
6. **Performance Testing**: Include performance benchmarks in tests
7. **Accessibility Testing**: Test keyboard navigation and screen readers
8. **Cross-Browser Testing**: Test on multiple browsers and devices

## Resources

- [PHPUnit Documentation](https://phpunit.readthedocs.io/)
- [Playwright Documentation](https://playwright.dev/)
- [WordPress Testing Documentation](https://make.wordpress.org/core/handbook/testing/)
- [VLP Development Standards](../docs/VLP%20Development%20Standards.txt)

---

**Last Updated**: December 2024  
**Version**: 1.0.0
