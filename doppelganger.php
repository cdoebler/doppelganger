<?php
/**
 * Plugin Name: Doppelganger
 * Description: Fast user switching for WordPress.
 * Version: 1.0.0
 * Author: Christian Doebler
 * Author URI: https://christian-doebler.net/
 * License: MIT
 *
 * @package Cdoebler\Doppelganger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

use Cdoebler\Doppelganger\Authorization_Service;
use Cdoebler\Doppelganger\Config_Helper;
use Cdoebler\Doppelganger\CookieImpersonator;
use Cdoebler\Doppelganger\Plugin;
use Cdoebler\Doppelganger\UserProvider;

add_action( 'plugins_loaded', 'doppelganger_init' );

/**
 * Initialize the Doppelganger plugin.
 *
 * @return void
 */
function doppelganger_init(): void {
	$user_provider = new UserProvider();
	$impersonator  = new CookieImpersonator();
	$config        = new Config_Helper();
	$authorization = new Authorization_Service( $impersonator, $config );

	$plugin = new Plugin( $user_provider, $impersonator, $authorization, $config );
	$plugin->run();
}
