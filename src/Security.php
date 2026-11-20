<?php
namespace HTECH\LeadFlow;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class Security {
	public static function scalar( $value ): string {
		return is_string( $value ) || is_int( $value ) || is_float( $value ) ? substr( (string) $value, 0, 20000 ) : '';
	}
	public static function text( $value, int $limit = 190 ): string {
		return mb_substr( sanitize_text_field( self::scalar( $value ) ), 0, $limit ); }
	public static function body( $value, int $limit = 5000 ): string {
		return mb_substr( sanitize_textarea_field( self::scalar( $value ) ), 0, $limit ); }
	public static function email( $value ): string {
		$raw = trim( self::scalar( $value ) );
		return strlen( $raw ) <= 190 && is_email( $raw ) ? strtolower( $raw ) : '';
	}
	public static function date( string $value ): string {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value && substr( $value, 0, 4 ) >= '1000' ? $value : '';
	}
	public static function phone( $value, string $country ): string {
		$raw = trim( self::scalar( $value ) );
		if ( strlen( $raw ) > 32 || ! preg_match( '/^\+?[0-9 ().-]+$/D', $raw ) ) {
			return ''; }
		$digits = preg_replace( '/\D/', '', $raw );
		if ( str_starts_with( $raw, '+' ) ) {
		} elseif ( str_starts_with( $digits, '00' ) ) {
			$digits = substr( $digits, 2 ); } elseif ( str_starts_with( $digits, '0' ) ) {
			$digits = $country . substr( $digits, 1 ); } elseif ( $country && ! str_starts_with( $digits, $country ) ) {
				$digits = $country . $digits; }
			return preg_match( '/^[1-9][0-9]{7,14}$/D', $digits ) ? '+' . $digits : '';
	}
	public static function url( $value ): string {
		$raw = self::scalar( $value );
		if ( strlen( $raw ) > 512 ) {
			return ''; }
		$url   = esc_url_raw( $raw, array( 'http', 'https' ) );
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return ''; }
		return $url;
	}
	public static function local_url( $value ): string {
		$url = self::url( $value );
		if ( ! $url ) {
			return ''; }
		$a = wp_parse_url( $url );
		$b = wp_parse_url( home_url() );
		return strtolower( $a['host'] ) === strtolower( $b['host'] ) && $a['scheme'] === $b['scheme'] && ( $a['port'] ?? null ) === ( $b['port'] ?? null ) ? $url : '';
	}
	public static function without_query( string $value ): string {
		return preg_split( '/[?#]/', self::url( $value ), 2 )[0];
	}
	public static function require( string $cap ): void {
		if ( ! current_user_can( 'htecl_' . $cap ) ) {
			wp_die( 'You do not have permission to perform this action.', '', array( 'response' => 403 ) ); }
	}
	public static function ok_nonce( string $action, $nonce ): bool {
		return (bool) wp_verify_nonce( self::scalar( $nonce ), $action ); }
	public static function sign( string $payload ): string {
		return hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) ); }
	public static function time_ok( string $issued, string $signature, int $id ): bool {
		return ctype_digit( $issued ) && (int) $issued <= time() - 2 && (int) $issued >= time() - 43200 && hash_equals( self::sign( $id . '|' . $issued ), $signature );
	}
	public static function rate( int $id, string $ip, ?int $clock = null ): bool {
		global $wpdb;
		$clock  = $clock ?? time();
		$bucket = (int) floor( $clock / 600 );
		// Do not trust client-provided forwarding headers; retain only a keyed, short-lived hash.
		$key    = self::sign( 'rate|' . $id . '|' . $bucket . '|' . $ip );
		$result = $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (token,hits,expires) VALUES (%s,1,%d) ON DUPLICATE KEY UPDATE hits=hits+1', Store::table( 'guard' ), $key, ( $bucket + 1 ) * 600 ) );
		if ( $result === false ) {
			return false; }
		$hits = $wpdb->get_var( $wpdb->prepare( 'SELECT hits FROM %i WHERE token=%s', Store::table( 'guard' ), $key ) );
		return $hits !== null && (int) $hits <= 12;
	}
	public static function csv( $value ): string {
		$value = self::scalar( $value );
		return preg_match( '/^[\p{Z}\p{C}]*[=+@-]|^[\t\r\n]/u', $value ) ? "'" . $value : $value;
	}
}
