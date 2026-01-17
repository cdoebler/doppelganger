<?php
/**
 * Main Plugin Class
 *
 * @package Cdoebler\Doppelganger
 */

declare(strict_types=1);

namespace Cdoebler\Doppelganger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cdoebler\GenericUserSwitcher\Renderer\UserSwitcherRenderer;
use Cdoebler\GenericUserSwitcher\Interfaces\UserProviderInterface;
use Cdoebler\GenericUserSwitcher\Interfaces\ImpersonatorInterface;

/**
 * Main Plugin class.
 *
 * Handles user switching functionality and integration with WordPress.
 */
final readonly class Plugin {

	private const PARAM_NAME = 'doppelganger_switch';

	/**
	 * Constructor.
	 *
	 * @param UserProviderInterface $user_provider User provider instance.
	 * @param ImpersonatorInterface $impersonator Impersonator instance.
	 * @param Authorization_Service $authorization Authorization service instance.
	 * @param Config_Helper         $config Configuration helper instance.
	 */
	public function __construct(
		private UserProviderInterface $user_provider,
		private ImpersonatorInterface $impersonator,
		private Authorization_Service $authorization,
		private Config_Helper $config
	) {}

	/**
	 * Initialize the plugin hooks.
	 *
	 * @return void
	 */
	public function run(): void {
		if ( $this->config->is_dev_mode_active() ) {
			add_action( 'admin_notices', $this->show_dev_mode_warning( ... ) );
		}

		if ( ! $this->config->is_feature_available() ) {
			return;
		}

		add_action( 'init', $this->handle_switch_request( ... ) );
		add_action( 'wp_footer', $this->render_switcher( ... ) );
		add_action( 'admin_footer', $this->render_switcher( ... ) );
	}

	/**
	 * Handle user switch requests from URL parameters.
	 *
	 * @return void
	 */
	public function handle_switch_request(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation, nonce not required.
		if ( isset( $_GET[ self::PARAM_NAME ] ) && '_stop' === $_GET[ self::PARAM_NAME ] ) {
			$original_id = $this->impersonator->getOriginalUserId();
			if ( null !== $original_id ) {
				$this->impersonator->stopImpersonating();
				wp_set_auth_cookie( $original_id );
				$this->redirect_and_exit();
			}

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation, nonce not required.
		if ( ! isset( $_GET[ self::PARAM_NAME ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- User ID is cast to int, which sanitizes it.
		$target_user_id = (int) $_GET[ self::PARAM_NAME ];

		// Checks the ORIGINAL user's permissions, not the current impersonated user.
		if ( ! $this->authorization->can_switch_users() ) {
			return;
		}

		$target_user = get_user_by( 'id', $target_user_id );
		if ( ! $target_user ) {
			return;
		}

		if ( ! $this->impersonator->isImpersonating() ) {
			$current_user_id = get_current_user_id();
			$this->impersonator->impersonate( $current_user_id );
		}

		wp_set_auth_cookie( $target_user->ID );

		// Redirect to avoid URL parameter staying in the address bar.
		$this->redirect_and_exit();
	}

	/**
	 * Render the user switcher widget.
	 *
	 * @return void
	 */
	public function render_switcher(): void {
		$is_impersonating = $this->impersonator->isImpersonating();

		// Allow if authorized OR currently impersonating (so they can stop).
		if ( ! $this->authorization->can_switch_users() && ! $is_impersonating ) {
			return;
		}

		$renderer = new UserSwitcherRenderer( $this->user_provider, $this->impersonator );

		/**
		 * Filter the user switcher configuration
		 *
		 * @param array<string, mixed> $config Configuration array with keys:
		 *                                     - param_name: URL parameter name
		 *                                     - current_user_id: Current user ID
		 *                                     - position: Widget position (bottom-right, etc.)
		 */
		$config = apply_filters(
			'doppelganger_config',
			array(
				'param_name'      => self::PARAM_NAME,
				'current_user_id' => get_current_user_id(),
				'position'        => 'bottom-right',
			)
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer output contains safe HTML.
		echo $renderer->render( $config );
	}

	/**
	 * Redirect to current URL without switcher parameter and exit.
	 *
	 * @return never
	 */
	private function redirect_and_exit(): never {
		$url = remove_query_arg( self::PARAM_NAME );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Display dev mode security warning in admin.
	 *
	 * @return void
	 */
	public function show_dev_mode_warning(): void {
		echo '<div class="notice notice-error"><p>';
		echo '<strong>⚠️ SECURITY WARNING: Doppelganger Dev Mode Active</strong><br>';
		echo 'The constant <code>DOPPELGANGER_DEV_MODE</code> is enabled. ';
		echo '<strong>ALL users (including guests) can switch to any account.</strong> ';
		echo 'This bypasses all authorization and environment checks. ';
		echo '<strong>NEVER use this in production!</strong> ';
		echo 'Remove this constant from wp-config.php immediately after debugging.';
		echo '</p></div>';
	}
}
