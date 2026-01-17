<?php
/**
 * Cookie-Based Impersonator
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
 * Cookie-Based Impersonator class.
 *
 * Manages user impersonation using secure cookies.
 */
final readonly class CookieImpersonator implements ImpersonatorInterface {

	private const COOKIE_NAME = 'doppelganger_impersonator';

	/**
	 * Start impersonating a user.
	 *
	 * @param string|int $identifier The user identifier to impersonate.
	 * @return void
	 */
	public function impersonate( string|int $identifier ): void {
		$identifier = (string) $identifier;
		$hash       = $this->generate_hash( $identifier );
		$value      = $identifier . '|' . $hash;

		$expire   = time() + DAY_IN_SECONDS;
		$secure   = is_ssl();
		$httponly = true;

		$cookie_path   = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
		$cookie_domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';

		setcookie(
			self::COOKIE_NAME,
			$value,
			array(
				'expires'  => $expire,
				'path'     => $cookie_path,
				'domain'   => $cookie_domain,
				'secure'   => $secure,
				'httponly' => $httponly,
			)
		);
	}

	/**
	 * Stop impersonating and return to original user.
	 *
	 * @return void
	 */
	public function stopImpersonating(): void {
		$cookie_path   = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
		$cookie_domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';

		setcookie(
			self::COOKIE_NAME,
			'',
			array(
				'expires' => time() - 3600,
				'path'    => $cookie_path,
				'domain'  => $cookie_domain,
			)
		);
		unset( $_COOKIE[ self::COOKIE_NAME ] );
	}

	/**
	 * Check if currently impersonating.
	 *
	 * @return bool True if impersonating, false otherwise.
	 */
	public function isImpersonating(): bool {
		return $this->getOriginalUserId() !== null;
	}

	/**
	 * Get the original user ID when impersonating.
	 *
	 * @return int|null The original user ID or null if not impersonating.
	 */
	public function getOriginalUserId(): int|null {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cookie value is validated via hash comparison.
		$value = $_COOKIE[ self::COOKIE_NAME ];
		$parts = explode( '|', (string) $value );

		if ( count( $parts ) !== 2 ) {
			return null;
		}

		[ $identifier, $hash ] = $parts;

		// Verify hash to prevent tampering.
		if ( ! hash_equals( $this->generate_hash( $identifier ), $hash ) ) {
			return null;
		}

		return (int) $identifier;
	}

	/**
	 * Generate a secure hash for the given data.
	 *
	 * Uses wp_hash() with nonce salt to prevent tampering.
	 *
	 * @param string $data The data to hash.
	 * @return string The generated hash.
	 */
	private function generate_hash( string $data ): string {
		return wp_hash( $data, 'nonce' );
	}
}
