<?php
/**
 * WordPress User Provider
 *
 * @package Cdoebler\Doppelganger
 */

declare(strict_types=1);

namespace Cdoebler\Doppelganger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cdoebler\GenericUserSwitcher\Interfaces\UserInterface;
use Cdoebler\GenericUserSwitcher\Interfaces\UserProviderInterface;
use WP_User;

/**
 * WordPress User Provider class.
 *
 * Provides the user switcher with WordPress users.
 */
readonly class UserProvider implements UserProviderInterface {

	/**
	 * Get all users.
	 *
	 * @return array<UserInterface> Array of user objects.
	 */
	public function getUsers(): array {
		/**
		 * WordPress users array.
		 *
		 * @var WP_User[] $wp_users
		 */
		$wp_users = get_users();

		$users = array();
		foreach ( $wp_users as $wp_user ) {
			$users[] = new UserWrapper( $wp_user );
		}

		return $users;
	}

	/**
	 * Find a user by their identifier.
	 *
	 * @param string|int $identifier The user identifier.
	 * @return UserInterface|null The user object or null if not found.
	 */
	public function findUserById( string|int $identifier ): ?UserInterface {
		$user = get_user_by( 'id', (int) $identifier );

		if ( false === $user ) {
			return null;
		}

		return new UserWrapper( $user );
	}
}
