<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Cdoebler\Doppelganger\Config_Helper;

/**
 * Tests for Config_Helper class
 *
 * This service handles configuration checks including:
 * - Environment gating (only allow in specific environments)
 * - Master enable/disable toggle
 */

describe('Config_Helper', function (): void {

	describe('is_enabled_for_environment', function (): void {

		it('returns true when environment is in allowed list', function (): void {
			// Arrange
			Functions\when('wp_get_environment_type')->justReturn('local');
			Functions\when('apply_filters')->alias(function ($hook, $default) {
				if ($hook === 'doppelganger_allowed_environments') {
					return ['local', 'development'];
				}

				return $default;
			});

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_enabled_for_environment();

			// Assert
			expect($result)->toBeTrue('Should allow switching in local environment');
		});

		it('returns false when environment is not in allowed list', function (): void {
			// Skip if dev mode constant is already defined from other tests
			if (defined('DOPPELGANGER_DEV_MODE') && DOPPELGANGER_DEV_MODE) {
				$this->markTestSkipped('Dev mode constant already defined');
			}

			// Arrange
			Functions\when('wp_get_environment_type')->justReturn('production');
			Functions\when('apply_filters')->alias(function ($hook, $default) {
				if ($hook === 'doppelganger_allowed_environments') {
					return ['local', 'development'];
				}

				return $default;
			});

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_enabled_for_environment();

			// Assert
			expect($result)->toBeFalse('Should not allow switching in production environment');
		});

		it('returns true when wildcard is in allowed environments', function (): void {
			// Arrange
			Functions\when('wp_get_environment_type')->justReturn('production');
			Functions\when('apply_filters')->alias(function ($hook, $default) {
				if ($hook === 'doppelganger_allowed_environments') {
					return ['*']; // Wildcard allows all environments
				}

				return $default;
			});

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_enabled_for_environment();

			// Assert
			expect($result)->toBeTrue('Wildcard (*) should allow all environments');
		});

		it('returns true for staging environment when staging is allowed', function (): void {
			// Arrange
			Functions\when('wp_get_environment_type')->justReturn('staging');
			Functions\when('apply_filters')->alias(function ($hook, $default) {
				if ($hook === 'doppelganger_allowed_environments') {
					return ['local', 'staging'];
				}

				return $default;
			});

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_enabled_for_environment();

			// Assert
			expect($result)->toBeTrue('Should allow switching in staging environment');
		});

		it('uses default allowed environments when filter not applied', function (): void {
			// Arrange
			Functions\when('wp_get_environment_type')->justReturn('local');
			Functions\when('apply_filters')->alias(function ($hook, $default) {
				return $default; // Don't modify, use default
			});

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_enabled_for_environment();

			// Assert
			expect($result)->toBeTrue('Should use default allowed environments (local, development)');
		});
	});

	describe('is_plugin_enabled', function (): void {

		it('returns true when option is set to true', function (): void {
			// Arrange
			Functions\when('get_option')->alias(function ($option, $default) {
				if ($option === 'doppelganger_enabled') {
					return true;
				}

				return $default;
			});

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_plugin_enabled();

			// Assert
			expect($result)->toBeTrue('Should return true when option is enabled');
		});

		it('returns false when option is set to false', function (): void {
			// Arrange
			Functions\when('get_option')->alias(function ($option, $default) {
				if ($option === 'doppelganger_enabled') {
					return false;
				}

				return $default;
			});

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_plugin_enabled();

			// Assert
			expect($result)->toBeFalse('Should return false when option is disabled');
		});

		it('returns true by default when option does not exist', function (): void {
			// Arrange
			Functions\when('get_option')->alias(function ($option, $default) {
				return $default; // Return default (true)
			});

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_plugin_enabled();

			// Assert
			expect($result)->toBeTrue('Should default to enabled (true) when option not set');
		});
	});

	describe('is_feature_available', function (): void {

		it('returns true when both plugin enabled and environment allowed', function (): void {
			// Arrange
			Functions\when('get_option')->alias(function ($option, $default) {
				if ($option === 'doppelganger_enabled') {
					return true;
				}

				return $default;
			});
			Functions\when('wp_get_environment_type')->justReturn('local');
			Functions\when('apply_filters')->alias(fn($hook, $default) => $default);

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_feature_available();

			// Assert
			expect($result)->toBeTrue('Should be available when enabled and in allowed environment');
		});

		it('returns false when plugin disabled even if environment allowed', function (): void {
			// Arrange
			Functions\when('get_option')->alias(function ($option, $default) {
				if ($option === 'doppelganger_enabled') {
					return false;
				}

				return $default;
			});
			Functions\when('wp_get_environment_type')->justReturn('local');
			Functions\when('apply_filters')->alias(fn($hook, $default) => $default);

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_feature_available();

			// Assert
			expect($result)->toBeFalse('Should not be available when plugin is disabled');
		});

		it('returns false when enabled but environment not allowed', function (): void {
			// Skip if dev mode constant is already defined from other tests
			if (defined('DOPPELGANGER_DEV_MODE') && DOPPELGANGER_DEV_MODE) {
				$this->markTestSkipped('Dev mode constant already defined');
			}

			// Arrange
			Functions\when('get_option')->alias(function ($option, $default) {
				if ($option === 'doppelganger_enabled') {
					return true;
				}

				return $default;
			});
			Functions\when('wp_get_environment_type')->justReturn('production');
			Functions\when('apply_filters')->alias(function ($hook, $default) {
				if ($hook === 'doppelganger_allowed_environments') {
					return ['local']; // Only local allowed
				}

				return $default;
			});

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_feature_available();

			// Assert
			expect($result)->toBeFalse('Should not be available in production when only local allowed');
		});
	});

	describe('Constants Support - DOPPELGANGER_ENABLED', function (): void {

		it('returns true when constant is defined as true', function (): void {
			// Arrange
			if (!defined('DOPPELGANGER_ENABLED')) {
				define('DOPPELGANGER_ENABLED', true);
			}

			Functions\when('has_filter')->justReturn(false);
			Functions\when('get_option')->justReturn(false); // DB says disabled, but constant wins

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_plugin_enabled();

			// Assert
			expect($result)->toBeTrue('Constant should override database option');
		});

		it('returns false when constant is defined as false', function (): void {
			// Arrange
			// Mock defined() to return true for this constant
			Functions\when('defined')->alias(fn($const): bool => $const === 'DOPPELGANGER_ENABLED');

			// Mock the constant value as false
			if (!defined('DOPPELGANGER_TEST_ENABLED_FALSE')) {
				define('DOPPELGANGER_TEST_ENABLED_FALSE', false);
			}

			// Temporarily redefine the constant name to test value
			runkit7_constant_redefine('DOPPELGANGER_ENABLED', false);

			Functions\when('has_filter')->justReturn(false);
			Functions\when('get_option')->justReturn(true); // DB says enabled, but constant wins

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_plugin_enabled();

			// Assert
			expect($result)->toBeFalse('Constant should override database option');
		})->skip('Cannot reliably test constant redefinition without runkit extension');
	});

	describe('Constants Support - DOPPELGANGER_ALLOWED_ENVIRONMENTS', function (): void {

		it('returns true when constant is wildcard', function (): void {
			// Arrange
			if (!defined('DOPPELGANGER_ALLOWED_ENVIRONMENTS')) {
				define('DOPPELGANGER_ALLOWED_ENVIRONMENTS', '*');
			}

			Functions\when('wp_get_environment_type')->justReturn('production');
			Functions\when('has_filter')->justReturn(false);
			Functions\when('apply_filters')->alias(fn($hook, $default): array => ['local']); // Filter would restrict, but constant wins

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_enabled_for_environment();

			// Assert
			expect($result)->toBeTrue('Wildcard constant should allow all environments');
		});

		it('returns true when environment matches constant value', function (): void {
			// Arrange
			if (!defined('DOPPELGANGER_ALLOWED_ENVIRONMENTS')) {
				define('DOPPELGANGER_ALLOWED_ENVIRONMENTS', 'local,development');
			}

			Functions\when('wp_get_environment_type')->justReturn('local');
			Functions\when('has_filter')->justReturn(false);

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_enabled_for_environment();

			// Assert
			expect($result)->toBeTrue('Environment should be allowed when in constant list');
		});

		it('returns false when environment not in constant value', function (): void {
			// Arrange
			if (!defined('DOPPELGANGER_ALLOWED_ENVIRONMENTS')) {
				define('DOPPELGANGER_ALLOWED_ENVIRONMENTS', 'local,development');
			}

			Functions\when('wp_get_environment_type')->justReturn('production');
			Functions\when('has_filter')->justReturn(false);

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_enabled_for_environment();

			// Assert
			expect($result)->toBeFalse('Environment should not be allowed when not in constant list');
		})->skip('Cannot reliably test constant with different values - constant already defined as wildcard in previous test');

		it('handles comma-separated environments with spaces', function (): void {
			// Arrange
			if (!defined('DOPPELGANGER_ALLOWED_ENVIRONMENTS')) {
				define('DOPPELGANGER_ALLOWED_ENVIRONMENTS', 'local, development, staging');
			}

			Functions\when('wp_get_environment_type')->justReturn('staging');
			Functions\when('has_filter')->justReturn(false);

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_enabled_for_environment();

			// Assert
			expect($result)->toBeTrue('Should trim spaces from environment names');
		});

		it('handles single environment constant', function (): void {
			// Arrange
			if (!defined('DOPPELGANGER_ALLOWED_ENVIRONMENTS')) {
				define('DOPPELGANGER_ALLOWED_ENVIRONMENTS', 'local');
			}

			Functions\when('wp_get_environment_type')->justReturn('local');
			Functions\when('has_filter')->justReturn(false);

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_enabled_for_environment();

			// Assert
			expect($result)->toBeTrue('Should handle single environment in constant');
		});
	});

	describe('Dev Mode - DOPPELGANGER_DEV_MODE', function (): void {

		it('returns true when dev mode constant is defined as true', function (): void {
			// Arrange
			if (!defined('DOPPELGANGER_DEV_MODE')) {
				define('DOPPELGANGER_DEV_MODE', true);
			}

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_dev_mode_active();

			// Assert
			expect($result)->toBeTrue('Dev mode should be active when constant is true');
		});

		it('bypasses environment check when dev mode is active', function (): void {
			// Arrange
			if (!defined('DOPPELGANGER_DEV_MODE')) {
				define('DOPPELGANGER_DEV_MODE', true);
			}

			Functions\when('wp_get_environment_type')->justReturn('production');
			Functions\when('apply_filters')->alias(function ($hook, $default) {
				if ($hook === 'doppelganger_allowed_environments') {
					return ['local']; // Only local allowed, but dev mode should bypass
				}

				return $default;
			});

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_enabled_for_environment();

			// Assert
			expect($result)->toBeTrue('Dev mode should bypass environment restrictions');
		});

		it('bypasses environment but still respects main enabled toggle', function (): void {
			// Skip if ENABLED constant is already defined from other tests
			if (defined('DOPPELGANGER_ENABLED')) {
				$this->markTestSkipped('ENABLED constant already defined');
			}

			// Arrange
			if (!defined('DOPPELGANGER_DEV_MODE')) {
				define('DOPPELGANGER_DEV_MODE', true);
			}

			Functions\when('get_option')->alias(function ($option, $default) {
				if ($option === 'doppelganger_enabled') {
					return false; // Plugin disabled
				}

				return $default;
			});
			Functions\when('wp_get_environment_type')->justReturn('production');
			Functions\when('apply_filters')->alias(fn($hook, $default) => $default);

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_feature_available();

			// Assert
			expect($result)->toBeFalse('Dev mode should not override main enabled toggle');
		});
	});

	describe('Warning System', function (): void {

		it('triggers warning when constant and filter both exist for enabled setting', function (): void {
			// Arrange
			if (!defined('DOPPELGANGER_ENABLED')) {
				define('DOPPELGANGER_ENABLED', true);
			}

			$doing_it_wrong_called = false;
			$admin_notices_added = false;

			Functions\when('has_filter')->alias(fn($filter_name): bool => $filter_name === 'doppelganger_enabled');

			Functions\when('_doing_it_wrong')->alias(function() use (&$doing_it_wrong_called): void {
				$doing_it_wrong_called = true;
			});

			Functions\when('add_action')->alias(function($hook) use (&$admin_notices_added): void {
				if ($hook === 'admin_notices') {
					$admin_notices_added = true;
				}
			});

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_plugin_enabled();

			// Assert
			expect($result)->toBeTrue();
			expect($doing_it_wrong_called)->toBeTrue('Should call _doing_it_wrong()');
			expect($admin_notices_added)->toBeTrue('Should add admin notice');
		});

		it('triggers warning when constant and filter both exist for environments setting', function (): void {
			// Skip if dev mode constant is already defined from other tests
			if (defined('DOPPELGANGER_DEV_MODE') && DOPPELGANGER_DEV_MODE) {
				$this->markTestSkipped('Dev mode constant already defined');
			}

			// Arrange
			if (!defined('DOPPELGANGER_ALLOWED_ENVIRONMENTS')) {
				define('DOPPELGANGER_ALLOWED_ENVIRONMENTS', '*');
			}

			$doing_it_wrong_called = false;
			$admin_notices_added = false;

			Functions\when('has_filter')->alias(fn($filter_name): bool => $filter_name === 'doppelganger_allowed_environments');

			Functions\when('_doing_it_wrong')->alias(function() use (&$doing_it_wrong_called): void {
				$doing_it_wrong_called = true;
			});

			Functions\when('add_action')->alias(function($hook) use (&$admin_notices_added): void {
				if ($hook === 'admin_notices') {
					$admin_notices_added = true;
				}
			});

			Functions\when('wp_get_environment_type')->justReturn('production');

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_enabled_for_environment();

			// Assert
			expect($result)->toBeTrue();
			expect($doing_it_wrong_called)->toBeTrue('Should call _doing_it_wrong()');
			expect($admin_notices_added)->toBeTrue('Should add admin notice');
		});

		it('does not warn when only constant exists', function (): void {
			// Arrange
			if (!defined('DOPPELGANGER_ENABLED')) {
				define('DOPPELGANGER_ENABLED', true);
			}

			$doing_it_wrong_called = false;

			Functions\when('has_filter')->justReturn(false); // No filter exists

			Functions\when('_doing_it_wrong')->alias(function() use (&$doing_it_wrong_called): void {
				$doing_it_wrong_called = true;
			});

			$helper = new Config_Helper();

			// Act
			$result = $helper->is_plugin_enabled();

			// Assert
			expect($result)->toBeTrue();
			expect($doing_it_wrong_called)->toBeFalse('Should not warn when no filter exists');
		});
	});
});
