<?php
/**
 * Plugin Name: HTECH LeadFlow
 * Description: Capture. Organize. Convert. Self-hosted forms and lead management.
 * Version: 0.9.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: HTECH Digital Solutions
 * License: GPL-2.0-or-later
 * Text Domain: htech-leadflow
 */
namespace HTECH\LeadFlow;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
const VERSION = '0.9.0';
const FILE    = __FILE__;
foreach ( array( 'Store', 'Security', 'Settings', 'Capture', 'Admin', 'Tools' ) as $class ) {
	require_once __DIR__ . '/src/' . $class . '.php';
}
register_activation_hook( FILE, array( Store::class, 'activate' ) );
register_deactivation_hook( FILE, array( Store::class, 'deactivate' ) );
add_action(
	'plugins_loaded',
	static function () {
		if ( get_option( 'htecl_schema' ) !== Store::SCHEMA ) {
			Store::activate();
		}
		if ( Store::SCHEMA !== get_option( 'htecl_schema' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-error"><p>HTECH LeadFlow could not install its database schema. Check the database account permissions before using LeadFlow.</p></div>';
					}
				}
			);
			return;
		}
		Capture::boot();
		Admin::boot();
		Tools::boot();
	}
);
