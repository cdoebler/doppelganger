<?php
/**
 * Authorization Service
 *
 * Checks permissions against the ORIGINAL user to prevent privilege escalation.
 *
 * @package Cdoebler\Doppelganger
 */

declare(strict_types=1);

namespace Cdoebler\Doppelganger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cdoebler\GenericUserSwitcher\Interfaces\ImpersonatorInterface;

/**
 * Authorization Service class.
 *
 * Checks permissions against the ORIGINAL user to prevent privilege escalation.
 */
final readonly class Authorization_Service {

	/**
	 * Constructor.
	 *
	 * @param ImpersonatorInterface $impersonator The impersonator instance.
	 * @param Config_Helper|null    $config_helper Optional configuration helper.
	 */
	public function __construct(
		private ImpersonatorInterface $impersonator,
		private ?Config_Helper $config_helper = null
	) {}

	/**
	 * Checks permissions against the original user (when impersonating)
	 * to prevent the widget from disappearing after switching to a less-privileged user.
	 */
	public function can_switch_users(): bool {
		if ( $this->config_helper?->is_dev_mode_active() ) {
			return true;
		}

		$user_id_to_check = $this->get_user_id_for_authorization();

		$can_switch = user_can( $user_id_to_check, 'edit_users' );

		/**
		 * Filter to determine if a user is allowed to use the switcher.
		 *
		 * @param bool $can_switch Whether the user can switch users.
		 * @param int  $user_id_to_check The user ID being checked (original user if impersonating).
		 */
		return (bool) apply_filters( 'doppelganger_can_switch', $can_switch, $user_id_to_check );
	}

	/**
	 * Get the user ID to use for authorization checks.
	 *
	 * Returns the original user ID when impersonating, otherwise current user ID.
	 *
	 * @return int The user ID for authorization.
	 */
	private function get_user_id_for_authorization(): int {
		if ( $this->impersonator->isImpersonating() ) {
			$original_user_id = $this->impersonator->getOriginalUserId();
			if ( null !== $original_user_id ) {
				return (int) $original_user_id;
			}
		}

		return get_current_user_id();
	}
}
