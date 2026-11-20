<?php
namespace HTECH\LeadFlow;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class Settings {
	public const MAIL_FIELDS = array(
		'name'    => 'Name',
		'email'   => 'Email',
		'phone'   => 'Phone',
		'company' => 'Company',
		'message' => 'Enquiry',
		'source'  => 'Source',
	);
	public static function defaults(): array {
		return array(
			'business'          => get_bloginfo( 'name' ),
			'email'             => get_option( 'admin_email' ),
			'country'           => '234',
			'whatsapp'          => '',
			'welcome'           => 'Hello, I would like to make an enquiry.',
			'follow_text'       => 'Hello [Name], thank you for contacting [Business Name]. I am following up on your enquiry.',
			'wa_label'          => 'Chat on WhatsApp',
			'wa_position'       => 'right',
			'wa_color'          => '#0A1F44',
			'floating'          => 0,
			'desktop'           => 1,
			'mobile'            => 1,
			'notify'            => 0,
			'assign_mail'       => 0,
			'recipient'         => '',
			'subject'           => 'New enquiry at [Business Name]',
			'mail_body'         => "A new enquiry has been recorded.\n\n[Details]\n\nOpen in WordPress: [Link]",
			'mail_fields'       => array( 'name', 'email' ),
			'retention'         => 0,
			'purge'             => 0,
			'visit_attribution' => 0,
			'privacy_page'      => 0,
			'setup'             => 0,
			'score_email'       => 10,
			'score_phone'       => 10,
			'score_message'     => 5,
			'score_qualified'   => 20,
			'score_threshold'   => 25,
		);
	}
	public static function get(): array {
		return array_replace( self::defaults(), (array) get_option( 'htecl_options', array() ) ); }
	public static function save( string $section, array $data ): string {
		$s    = self::get();
		$bool = static fn( $key )=> ! empty( $data[ $key ] ) ? 1 : 0;
		if ( $section === 'settings' || $section === 'setup' ) {
			$business = Security::text( $data['business'] ?? '' );
			$email    = Security::email( $data['email'] ?? '' );
			if ( ! $business || ! $email ) {
				return 'Provide a business name and valid business email.'; }
			$s['business'] = $business;
			$s['email']    = $email;
		}
		if ( $section === 'settings' ) {
			$s['retention']         = min( 3650, absint( $data['retention'] ?? 0 ) );
			$s['purge']             = $bool( 'purge' );
			$s['visit_attribution'] = $bool( 'visit_attribution' );
			$s['privacy_page']      = absint( $data['privacy_page'] ?? 0 );
			foreach ( array( 'email', 'phone', 'message', 'qualified', 'threshold' ) as $key ) {
				$s[ 'score_' . $key ] = min( 1000, absint( $data[ 'score_' . $key ] ?? 0 ) ); }
		}
		if ( $section === 'whatsapp' || $section === 'setup' ) {
			$country = Security::text( $data['country'] ?? $s['country'], 8 );
			if ( ! preg_match( '/^[1-9][0-9]{0,2}$/D', $country ) ) {
				return 'Enter a 1–3 digit country calling code.'; }
			$raw    = Security::text( $data['whatsapp'] ?? '' );
			$number = Security::phone( $raw, $country );
			if ( $raw && ! $number ) {
				return 'Enter a valid WhatsApp telephone number, preferably beginning with +.'; }
			$s['country']  = $country;
			$s['whatsapp'] = $number;
		}
		if ( $section === 'whatsapp' ) {
			foreach ( array( 'welcome', 'follow_text' ) as $key ) {
				$s[ $key ] = Security::body( $data[ $key ] ?? '', 1000 ); }
			$s['wa_label']    = Security::text( $data['wa_label'] ?? '', 60 ) ?: 'Chat on WhatsApp';
			$s['wa_position'] = ( $data['wa_position'] ?? '' ) === 'left' ? 'left' : 'right';
			$s['wa_color']    = sanitize_hex_color( Security::scalar( $data['wa_color'] ?? '' ) ) ?: '#0A1F44';
			foreach ( array( 'floating', 'desktop', 'mobile' ) as $key ) {
				$s[ $key ] = $bool( $key ); }
		}
		if ( $section === 'notifications' || $section === 'setup' ) {
			$s['notify']    = $bool( 'notify' );
			$raw            = Security::text( $data['recipient'] ?? '' );
			$s['recipient'] = Security::email( $raw );
			if ( $raw && ! $s['recipient'] ) {
				return 'Enter a valid notification recipient.'; }
		}
		if ( $section === 'notifications' ) {
			$s['assign_mail'] = $bool( 'assign_mail' );
			$s['subject']     = Security::text( $data['subject'] ?? '' ) ?: self::defaults()['subject'];
			$s['mail_body']   = Security::body( $data['mail_body'] ?? '' ) ?: self::defaults()['mail_body'];
			$s['mail_fields'] = array_values( array_intersect( array_keys( self::MAIL_FIELDS ), array_filter( (array) ( $data['mail_fields'] ?? array() ), 'is_string' ) ) );
		}
		update_option( 'htecl_options', $s, false );
		return '';
	}
	public static function score( array $lead ): int {
		$s        = self::get();
		$parts    = explode( '@', $lead['email'] ?? '' );
		$domain   = end( $parts );
		$personal = array( 'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com', 'live.com', 'aol.com', 'proton.me', 'yahoo.co.uk', 'protonmail.com' );
		return ( count( $parts ) === 2 && ! in_array( $domain, $personal, true ) ? $s['score_email'] : 0 ) +
			( ! empty( $lead['phone'] ) ? $s['score_phone'] : 0 ) + ( mb_strlen( $lead['message'] ?? '' ) >= 100 ? $s['score_message'] : 0 ) +
			( in_array( $lead['status'] ?? '', array( 'qualified', 'converted' ), true ) ? $s['score_qualified'] : 0 );
	}
	public static function whatsapp( string $phone, string $message ): string {
		$number = Security::phone( $phone, self::get()['country'] );
		return $number ? 'https://wa.me/' . substr( $number, 1 ) . ( $message !== '' ? '?text=' . rawurlencode( $message ) : '' ) : '';
	}
	public static function notify( int $id, bool $assignment = false ): void {
		$s    = self::get();
		$lead = Store::get( 'leads', $id );
		if ( ! $lead || ! ( $assignment ? $s['assign_mail'] : $s['notify'] ) ) {
			return; }
		$user    = $assignment ? get_userdata( (int) $lead['assigned'] ) : null;
		$form    = $assignment || ! $lead['form_id'] ? null : Store::get( 'forms', (int) $lead['form_id'] );
		$form_to = $form ? ( Store::decode( $form['config'] )['recipient'] ?? '' ) : '';
		$to      = $assignment ? ( $user && user_can( $user, 'htecl_view' ) ? $user->user_email : '' ) : ( $form_to ?: ( $s['recipient'] ?: $s['email'] ) );
		if ( ! is_email( $to ) ) {
			return; }
		$link    = Admin::url( 'leads', array( 'id' => $id ) );
		$details = array();
		foreach ( $s['mail_fields'] as $field ) {
			if ( isset( self::MAIL_FIELDS[ $field ], $lead[ $field ] ) && $lead[ $field ] !== '' ) {
				$details[] = self::MAIL_FIELDS[ $field ] . ': ' . $lead[ $field ]; }
		}
		$replace = array(
			'[Business Name]' => $s['business'],
			'[Details]'       => implode( "\n", $details ),
			'[Link]'          => $link,
		);
		$body    = $assignment ? "A lead has been assigned to you.\n\n$link" : strtr( $s['mail_body'], $replace );
		if ( ! str_contains( $body, $link ) ) {
			$body .= "\n\n" . $link; }
		if ( ! wp_mail( $to, $assignment ? 'A lead was assigned to you' : strtr( $s['subject'], array( '[Business Name]' => $s['business'] ) ), $body ) ) {
			Store::activity( $id, 'WordPress did not accept the notification for sending.' );
		}
	}
}
