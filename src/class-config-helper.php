<?php
/**
 * Configuration Helper
 *
 * @package Cdoebler\Doppelganger
 */

declare(strict_types=1);

namespace Cdoebler\Doppelganger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configuration Helper class.
 *
 * Handles configuration and environment checks for the plugin.
 */
final readonly class Config_Helper {

	private const DEFAULT_ALLOWED_ENVIRONMENTS = array( '*' );

	/**
	 * Check if dev mode is active.
	 *
	 * @return bool True if dev mode is active, false otherwise.
	 */
	public function is_dev_mode_active(): bool {
		return defined( 'DOPPELGANGER_DEV_MODE' ) && DOPPELGANGER_DEV_MODE;
	}

	/**
	 * Check if the plugin is enabled for the current environment.
	 *
	 * @return bool True if enabled for this environment, false otherwise.
	 */
	public function is_enabled_for_environment(): bool {
		if ( $this->is_dev_mode_active() ) {
			return true;
		}

		if ( defined( 'DOPPELGANGER_ALLOWED_ENVIRONMENTS' ) ) {
			$this->warn_if_filter_exists( 'doppelganger_allowed_environments' );
			$allowed_environments = $this->parse_environments_constant();
		} else {
			/**
			 * Filter the allowed environments for user switching.
			 *
			 * @param array<string> $environments List of allowed environment types.
			 *                                    Use ['*'] to allow all environments.
			 *                                    Default: ['*'] (all environments)
			 */
			$allowed_environments = apply_filters(
				'doppelganger_allowed_environments',
				self::DEFAULT_ALLOWED_ENVIRONMENTS
			);
		}

		// Wildcard '*' allows all environments.
		if ( in_array( '*', $allowed_environments, true ) ) {
			return true;
		}

		$current_environment = wp_get_environment_type();

		return in_array( $current_environment, $allowed_environments, true );
	}

	/**
	 * Check if the plugin is enabled.
	 *
	 * @return bool True if plugin is enabled, false otherwise.
	 */
	public function is_plugin_enabled(): bool {
		if ( defined( 'DOPPELGANGER_ENABLED' ) ) {
			$this->warn_if_filter_exists( 'doppelganger_enabled' );
			return DOPPELGANGER_ENABLED;
		}

		return (bool) get_option( 'doppelganger_enabled', true );
	}

	/**
	 * Check if the feature is available.
	 *
	 * @return bool True if feature is available, false otherwise.
	 */
	public function is_feature_available(): bool {
		return $this->is_plugin_enabled() && $this->is_enabled_for_environment();
	}

	/**
	 * Parse the DOPPELGANGER_ALLOWED_ENVIRONMENTS constant value.
	 *
	 * @return array<string> Array of allowed environment names
	 */
	private function parse_environments_constant(): array {
		$value = DOPPELGANGER_ALLOWED_ENVIRONMENTS;

		if ( '*' === $value ) {
			return array( '*' );
		}

		$environments = explode( ',', $value );
		return array_map( trim( ... ), $environments );
	}

	/**
	 * Warn if a filter exists when a constant is defined.
	 *
	 * Triggers _doing_it_wrong() for debug logs and adds admin notice.
	 *
	 * @param string $filter_name The filter hook name to check.
	 */
	private function warn_if_filter_exists( string $filter_name ): void {
		if ( ! has_filter( $filter_name ) ) {
			return;
		}

		$constant_name = $this->get_constant_name_for_filter( $filter_name );

		_doing_it_wrong(
			__METHOD__,
			sprintf(
				'The constant %s is defined. The filter "%s" will be ignored.',
				$constant_name, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- _doing_it_wrong() is for debug output.
				$filter_name // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- _doing_it_wrong() is for debug output.
			),
			'1.0.0'
		);

		add_action(
			'admin_notices',
			function () use ( $constant_name, $filter_name ): void {
				printf(
					'<div class="notice notice-warning"><p><strong>Doppelganger:</strong> The constant %s is defined. The filter "%s" will be ignored.</p></div>',
					esc_html( $constant_name ),
					esc_html( $filter_name )
				);
			}
		);
	}

	/**
	 * Get the constant name that corresponds to a filter name.
	 *
	 * @param string $filter_name The filter hook name.
	 * @return string The constant name.
	 */
	private function get_constant_name_for_filter( string $filter_name ): string {
		$map = array(
			'doppelganger_enabled'              => 'DOPPELGANGER_ENABLED',
			'doppelganger_allowed_environments' => 'DOPPELGANGER_ALLOWED_ENVIRONMENTS',
		);

		return $map[ $filter_name ] ?? '';
	}
}
