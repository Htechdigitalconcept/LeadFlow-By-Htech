<?php
/** HTECH LeadFlow uninstall. Retain customer records by default. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; }

$htecl_uninstall = static function (): void {
	global $wpdb;
	wp_clear_scheduled_hook( 'htecl_cleanup' );
	$options = get_option( 'htecl_options', array() );
	if ( empty( $options['purge'] ) ) {
		return; }
	foreach ( array( 'forms', 'leads', 'notes', 'activities', 'followups', 'guard' ) as $table ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'htecl_' . $table ) );
	}
	foreach ( array( 'htecl_options', 'htecl_schema' ) as $option ) {
		delete_option( $option ); }
	foreach ( array( '_transient_htecl_', '_transient_timeout_htecl_' ) as $prefix ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( $prefix ) . '%' ) );
	}
	foreach ( wp_roles()->role_objects as $role ) {
		foreach ( array( 'view', 'edit', 'delete', 'export', 'forms', 'settings', 'analytics' ) as $cap ) {
			$role->remove_cap( 'htecl_' . $cap ); }
	}
	$offset = 0;
	do {
		$users = get_users(
			array(
				'fields'         => 'ID',
				'capability__in' => array( 'htecl_view', 'htecl_edit', 'htecl_delete', 'htecl_export', 'htecl_forms', 'htecl_settings', 'htecl_analytics' ),
				'number'         => 1000,
				'offset'         => $offset,
			)
		);
		foreach ( $users as $id ) {
			$user = new WP_User( $id );
			foreach ( array( 'view', 'edit', 'delete', 'export', 'forms', 'settings', 'analytics' ) as $cap ) {
				$user->remove_cap( 'htecl_' . $cap ); }
		}
		// Users matching only direct capabilities shrink out of this result.
	} while ( count( $users ) === 1000 );
};
if ( is_multisite() ) {
	$htecl_offset = 0;
	do {
		$htecl_sites = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $htecl_offset,
			)
		);
		foreach ( $htecl_sites as $htecl_site ) {
			switch_to_blog( (int) $htecl_site );
			$htecl_uninstall();
			restore_current_blog(); }
		$htecl_offset += 100;
	} while ( count( $htecl_sites ) === 100 );
} else {
	$htecl_uninstall(); }
