<?php
/**
 * WordPress User Wrapper
 *
 * @package Cdoebler\Doppelganger
 */

declare(strict_types=1);

namespace Cdoebler\Doppelganger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cdoebler\GenericUserSwitcher\Interfaces\UserInterface;
use WP_User;

/**
 * WordPress User Wrapper class.
 *
 * Wraps WP_User to implement UserInterface.
 */
readonly class UserWrapper implements UserInterface {

	/**
	 * Constructor.
	 *
	 * @param WP_User $user WordPress user object.
	 */
	public function __construct(
		private WP_User $user
	) {}

	/**
	 * Get the user identifier.
	 *
	 * @return int The user ID.
	 */
	public function getIdentifier(): int {
		return $this->user->ID;
	}

	/**
	 * Get the user display name.
	 *
	 * @return string The user's display name.
	 */
	public function getDisplayName(): string {
		return $this->user->display_name;
	}
}
