<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Cdoebler\GenericUserSwitcher\Interfaces\ImpersonatorInterface;
use Cdoebler\Doppelganger\Authorization_Service;

/**
 * Tests for Authorization_Service class
 *
 * This service handles authorization checks for user switching,
 * ensuring that authorization is always checked against the ORIGINAL user,
 * not the currently impersonated user.
 */

describe('Authorization_Service', function (): void {

	describe('can_switch_users', function (): void {

		it('returns true when user has edit_users capability and not impersonating', function (): void {
			// Arrange
			$impersonator = Mockery::mock(ImpersonatorInterface::class);
			$impersonator->shouldReceive('isImpersonating')->once()->andReturn(false);
			$impersonator->shouldReceive('getOriginalUserId')->never();

			Functions\when('get_current_user_id')->justReturn(1);
			Functions\when('user_can')->alias(function ($user_id, $cap): true {
				return true; // User has permission
			});
			Functions\when('apply_filters')->alias(function ($hook, $value, $user_id) {
				return $value; // Pass through
			});

			$service = new Authorization_Service($impersonator);

			// Act
			$result = $service->can_switch_users();

			// Assert
			expect($result)->toBeTrue();
		});

		it('returns false when user does not have edit_users capability', function (): void {
			// Arrange
			$impersonator = Mockery::mock(ImpersonatorInterface::class);
			$impersonator->shouldReceive('isImpersonating')->once()->andReturn(false);
			$impersonator->shouldReceive('getOriginalUserId')->never();

			Functions\when('get_current_user_id')->justReturn(2);
			Functions\when('user_can')->alias(function ($user_id, $cap): false {
				return false; // User 2 doesn't have permission
			});
			Functions\when('apply_filters')->alias(function ($hook, $value, $user_id) {
				return $value; // Pass through the value
			});

			$service = new Authorization_Service($impersonator);

			// Act
			$result = $service->can_switch_users();

			// Assert
			expect($result)->toBeFalse();
		});

		it('checks original user capability when impersonating', function (): void {
			// Arrange - This is the CRITICAL test for the security fix
			// Admin (user 1) switches to regular user (user 2)
			// Authorization should check user 1, not user 2
			$impersonator = Mockery::mock(ImpersonatorInterface::class);
			$impersonator->shouldReceive('isImpersonating')->once()->andReturn(true);
			$impersonator->shouldReceive('getOriginalUserId')->once()->andReturn(1); // Original admin

			Functions\when('get_current_user_id')->justReturn(2); // Currently impersonating user 2
			Functions\when('user_can')->alias(function ($user_id, $cap): bool {
				// Check that we're checking user 1 (admin), not user 2
				return $user_id === 1; // Only admin (1) has permission
			});
			Functions\when('apply_filters')->alias(function ($hook, $value, $user_id) {
				return $value; // Pass through
			});

			$service = new Authorization_Service($impersonator);

			// Act
			$result = $service->can_switch_users();

			// Assert
			expect($result)->toBeTrue('Should check original user (1) who has edit_users, not current user (2)');
		});

		it('respects custom authorization callback when provided', function (): void {
			// Arrange
			$impersonator = Mockery::mock(ImpersonatorInterface::class);
			$impersonator->shouldReceive('isImpersonating')->once()->andReturn(false);
			$impersonator->shouldReceive('getOriginalUserId')->never();

			Functions\when('get_current_user_id')->justReturn(1);
			Functions\when('user_can')->alias(function ($user_id, $cap): true {
				return true; // User has permission
			});
			Functions\when('apply_filters')->alias(function ($hook, $value, $user_id) {
				// Custom filter overrides to false
				if ($hook === 'doppelganger_can_switch') {
					return false;
				}

				return $value;
			});

			$service = new Authorization_Service($impersonator);

			// Act
			$result = $service->can_switch_users();

			// Assert
			expect($result)->toBeFalse('Custom filter should override default capability check');
		});

		it('passes original user id to filter when impersonating', function (): void {
			// Arrange
			$impersonator = Mockery::mock(ImpersonatorInterface::class);
			$impersonator->shouldReceive('isImpersonating')->once()->andReturn(true);
			$impersonator->shouldReceive('getOriginalUserId')->once()->andReturn(1);

			$filter_user_id = null; // Track what user ID is passed to filter

			Functions\when('get_current_user_id')->justReturn(2); // Shouldn't be used
			Functions\when('user_can')->alias(fn($user_id, $cap): true => true);
			Functions\when('apply_filters')->alias(function ($hook, $value, $user_id) use (&$filter_user_id) {
				// Capture the user ID passed to filter
				$filter_user_id = $user_id;
				return $value;
			});

			$service = new Authorization_Service($impersonator);

			// Act
			$result = $service->can_switch_users();

			// Assert
			expect($result)->toBeTrue('Should return result from filter');
			expect($filter_user_id)->toBe(1, 'Should pass original user ID (1) to filter, not current user (2)');
		});
	});

	describe('Dev Mode Authorization Bypass', function (): void {

		it('bypasses authorization when dev mode is active', function (): void {
			// Arrange
			if (!defined('DOPPELGANGER_DEV_MODE')) {
				define('DOPPELGANGER_DEV_MODE', true);
			}

			$impersonator = Mockery::mock(ImpersonatorInterface::class);
			$impersonator->shouldReceive('isImpersonating')->andReturn(false);
			$config_helper = new \Cdoebler\Doppelganger\Config_Helper();

			Functions\when('get_current_user_id')->justReturn(999); // User without permissions
			Functions\when('user_can')->alias(fn($user_id, $cap): false => false); // No permission
			Functions\when('apply_filters')->alias(fn($hook, $value, $user_id) => $value);

			$service = new Authorization_Service($impersonator, $config_helper);

			// Act
			$result = $service->can_switch_users();

			// Assert
			expect($result)->toBeTrue('Dev mode should bypass authorization checks');
		});

		it('allows guests when dev mode is active', function (): void {
			// Arrange
			if (!defined('DOPPELGANGER_DEV_MODE')) {
				define('DOPPELGANGER_DEV_MODE', true);
			}

			$impersonator = Mockery::mock(ImpersonatorInterface::class);
			$impersonator->shouldReceive('isImpersonating')->andReturn(false);
			$config_helper = new \Cdoebler\Doppelganger\Config_Helper();

			Functions\when('get_current_user_id')->justReturn(0); // Guest (not logged in)
			Functions\when('user_can')->alias(fn($user_id, $cap): false => false); // No permission
			Functions\when('apply_filters')->alias(fn($hook, $value, $user_id) => $value);

			$service = new Authorization_Service($impersonator, $config_helper);

			// Act
			$result = $service->can_switch_users();

			// Assert
			expect($result)->toBeTrue('Dev mode should allow even guests to use switcher');
		});
	});
});
