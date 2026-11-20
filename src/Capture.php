<?php
namespace HTECH\LeadFlow;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class Capture {
	public const TYPES = array( 'text', 'email', 'tel', 'select', 'textarea', 'number', 'checkbox' );
	public const MAP   = array(
		'name'    => 'Full name',
		'email'   => 'Email',
		'phone'   => 'Phone',
		'company' => 'Company',
		'message' => 'Enquiry',
	);
	public static function boot(): void {
		add_shortcode( 'htech_lead_form', array( self::class, 'shortcode' ) );
		add_shortcode( 'htech_whatsapp', static fn()=>self::button( false ) );
		add_action(
			'wp_footer',
			static function () {
				if ( Settings::get()['floating'] ) {
					echo self::button( true );
				} }
		);
		add_action(
			'wp_enqueue_scripts',
			static function () {
				wp_register_style( 'htecl-public', plugins_url( 'assets/public.css', FILE ), array(), VERSION );
				wp_register_script( 'htecl-public', plugins_url( 'assets/public.js', FILE ), array(), VERSION, true );
				if ( Settings::get()['visit_attribution'] ) {
					wp_add_inline_script( 'htecl-public', 'window.hteclRememberVisit = true;', 'before' );
					wp_enqueue_script( 'htecl-public' );
				}
			}
		);
		add_action( 'admin_post_htecl_submit', array( self::class, 'submit' ) );
		add_action( 'admin_post_nopriv_htecl_submit', array( self::class, 'submit' ) );
	}
	public static function defaults(): array {
		return array(
			'button'    => 'Send enquiry',
			'success'   => 'Thank you. Your enquiry has been received.',
			'redirect'  => '',
			'recipient' => '',
			'style'     => 'card',
			'fields'    => array(
				array(
					'key'      => 'full_name',
					'type'     => 'text',
					'label'    => 'Full name',
					'map'      => 'name',
					'required' => 1,
				),
				array(
					'key'      => 'email',
					'type'     => 'email',
					'label'    => 'Email address',
					'map'      => 'email',
					'required' => 1,
				),
				array(
					'key'   => 'phone',
					'type'  => 'tel',
					'label' => 'Phone number',
					'map'   => 'phone',
				),
				array(
					'key'      => 'enquiry',
					'type'     => 'textarea',
					'label'    => 'How can we help?',
					'map'      => 'message',
					'required' => 1,
				),
			),
		);
	}
	public static function config( array $input ): array|string {
		$d      = self::defaults();
		$fields = array();
		$keys   = array();
		$maps   = array();
		if ( ! is_array( $input['fields'] ?? null ) || count( $input['fields'] ) > 25 ) {
			return 'Add between 1 and 25 fields.'; }
		foreach ( $input['fields'] as $field ) {
			if ( ! is_array( $field ) || ! empty( $field['remove'] ) ) {
				continue; }
			$label = Security::text( $field['label'] ?? '', 100 );
			if ( ! $label ) {
				return 'All fields must have a label.'; }
			$type = Security::text( $field['type'] ?? '' );
			$map  = Security::text( $field['map'] ?? '' );
			$key  = sanitize_key( Security::text( $field['key'] ?? '', 50 ) );
			if ( $key === '' ) {
				$key = 'field_' . strtolower( wp_generate_password( 10, false, false ) ); }
			if ( ! in_array( $type, self::TYPES, true ) || isset( $keys[ $key ] ) ) {
				return 'Each field must have a supported type and a unique key.'; }
			if ( $map !== '' && ( ! isset( self::MAP[ $map ] ) || isset( $maps[ $map ] ) || in_array( $type, array( 'checkbox', 'number' ), true ) ) ) {
				return 'Use each lead field mapping at most once and only on compatible field types.'; }
			$options = array_values( array_unique( array_filter( array_map( static fn( $v )=>Security::text( $v, 100 ), preg_split( '/\r\n|\n|\r/', Security::scalar( $field['options'] ?? '' ) ) ), static fn( $v )=>$v !== '' ) ) );
			if ( $type === 'select' && ( ! $options || count( $options ) > 50 ) ) {
				return 'Dropdowns need between 1 and 50 options, one per line.'; }
			$consent      = $type === 'checkbox' && ! empty( $field['consent'] );
			$fields[]     = array(
				'key'         => $key,
				'label'       => $label,
				'type'        => $type,
				'map'         => $map,
				'options'     => $type === 'select' ? $options : array(),
				'required'    => ! empty( $field['required'] ) ? 1 : 0,
				'consent'     => $consent ? 1 : 0,
				'placeholder' => Security::text( $field['placeholder'] ?? '', 100 ),
				'description' => Security::text( $field['description'] ?? '', 250 ),
			);
			$keys[ $key ] = 1;
			if ( $map !== '' ) {
				$maps[ $map ] = 1; }
		}
		if ( ! $fields ) {
			return 'Add at least one field.'; }
		$redirect  = trim( Security::scalar( $input['redirect'] ?? '' ) );
		$recipient = trim( Security::scalar( $input['recipient'] ?? '' ) );
		if ( $redirect && ! Security::local_url( $redirect ) ) {
			return 'Use an absolute redirect URL on this website.'; }
		if ( $recipient && ! Security::email( $recipient ) ) {
			return 'Use a valid form notification email.'; }
		return array(
			'button'    => Security::text( $input['button'] ?? '', 60 ) ?: $d['button'],
			'success'   => Security::body( $input['success'] ?? '', 600 ) ?: $d['success'],
			'redirect'  => Security::local_url( $redirect ),
			'recipient' => Security::email( $recipient ),
			'style'     => ( $input['style'] ?? '' ) === 'plain' ? 'plain' : 'card',
			'fields'    => $fields,
		);
	}
	public static function validate( array $fields, array $input ): array|string {
		$lead   = array_fill_keys( array_keys( self::MAP ), '' );
		$custom = array();
		foreach ( $fields as $f ) {
			$type  = $f['type'];
			$label = $f['label'];
			$key   = $f['key'];
			$map   = $f['map'] ?? '';
			if ( isset( $input[ $key ] ) && ! is_string( $input[ $key ] ) ) {
				return 'Check ' . $label . '.'; }
			$raw = trim( Security::scalar( $input[ $key ] ?? '' ) );
			if ( $type === 'checkbox' ) {
				if ( ! in_array( $raw, array( '', '1' ), true ) ) {
					return 'Check ' . $label . '.'; }
				if ( ! empty( $f['required'] ) && $raw !== '1' ) {
					return 'Complete ' . $label . '.'; }
				$value = $raw === '1' ? 'Yes' : 'No';
			} else {
				$value = $type === 'textarea' ? Security::body( $raw ) : Security::text( $raw );
				if ( $value === '' && ( $raw !== '' || ! empty( $f['required'] ) ) ) {
					return 'Complete ' . $label . '.'; }
				if ( $value !== '' ) {
					if ( $type === 'select' && ! in_array( $raw, $f['options'], true ) ) {
						return 'Select an option for ' . $label . '.'; }
					if ( $type === 'number' && ( strlen( $raw ) > 32 || ! preg_match( '/^-?\d+(?:\.\d+)?$/D', $raw ) ) ) {
						return 'Enter a valid number for ' . $label . '.'; }
					if ( $type === 'email' || $map === 'email' ) {
						$value = Security::email( $raw ); }
					if ( $type === 'tel' || $map === 'phone' ) {
						$value = Security::phone( $raw, Settings::get()['country'] ); }
					if ( $value === '' ) {
						return 'Enter a valid value for ' . $label . '.'; }
				}
			}
			if ( $map !== '' ) {
				$lead[ $map ] = $map === 'message' ? $value : Security::text( $value ); }
			$custom[ $key ] = array(
				'label'   => $label,
				'value'   => $value,
				'consent' => ! empty( $f['consent'] ),
			);
		}
		$lead['fields'] = Store::json( $custom );
		return $lead;
	}
	public static function attribution( array $posted ): array {
		$local   = Security::local_url( $posted['landing'] ?? '' );
		$landing = Security::without_query( $local );
		parse_str( (string) wp_parse_url( $local, PHP_URL_QUERY ), $route );
		$safe = array();
		foreach ( array( 'p', 'page_id' ) as $key ) {
			if ( preg_match( '/^[0-9]{1,12}$/D', Security::scalar( $route[ $key ] ?? '' ) ) ) {
				$safe[ $key ] = $route[ $key ]; }
		}
		if ( $landing && $safe && strlen( $with_route = add_query_arg( $safe, $landing ) ) <= 512 ) {
			$landing = $with_route; }
		$ref    = Security::without_query( Security::url( $posted['referrer'] ?? '' ) );
		$domain = $ref ? (string) wp_parse_url( $ref, PHP_URL_HOST ) : '';
		$a      = array(
			'landing'  => $landing,
			'referrer' => $ref,
			'domain'   => $domain,
		);
		foreach ( array( 'source', 'medium', 'campaign', 'term', 'content' ) as $key ) {
			$a[ 'utm_' . $key ] = Security::text( $posted[ 'utm_' . $key ] ?? '' ); }
		$same   = $domain && strcasecmp( $domain, (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 0;
		$source = $a['utm_source'] ?: ( $domain ? ( $same ? 'Internal' : $domain ) : ( ( $posted['ref_state'] ?? '' ) === 'none' ? 'Direct / no referrer reported' : 'Unavailable' ) );
		return array(
			'source'      => Security::text( $source ),
			'landing'     => $landing,
			'campaign'    => $a['utm_campaign'],
			'attribution' => Store::json( $a ),
		);
	}
	public static function record( int $id, array $values, array $posted ): int {
		global $wpdb;
		$now               = time();
		$shape             = $id . '|' . Store::json( $values );
		$values           += self::attribution( $posted );
		$values['status']  = 'new';
		$values['form_id'] = $id;
		$values['score']   = Settings::score( $values );
		$prior             = $wpdb->suppress_errors( true );
		try {
			// A single unique slot blocks identical concurrent submissions across the full short window.
			$values['fingerprint'] = Security::sign( 'submission|' . $shape );
			$table                 = Store::table( 'leads' );
			$cleared               = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET fingerprint=NULL WHERE fingerprint=%s AND created<%s', $table, $values['fingerprint'], gmdate( 'Y-m-d H:i:s', $now - 600 ) ) );
			return $cleared === false ? 0 : Store::create( $values );
		} finally {
			$wpdb->suppress_errors( $prior ); }
	}
	public static function submit(): void {
		if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
			wp_die( 'Use the enquiry form to submit.', '', array( 'response' => 405 ) ); }
		$p      = wp_unslash( $_POST );
		$id     = absint( Security::scalar( $p['form_id'] ?? '' ) );
		$return = Security::local_url( $p['return_url'] ?? '' ) ?: home_url( '/' );
		$return = remove_query_arg( array( 'lf_form', 'lf_notice', 'lf_sig' ), $return );
		$form   = Store::get( 'forms', $id );
		$error  = '';
		if ( ! $form || ! (int) $form['active'] ) {
			$error = 'This form is unavailable.'; } elseif ( ! Security::ok_nonce( 'htecl_submit_' . $id, $p['_wpnonce'] ?? '' ) ) {
			$error = 'Your form session expired. Please refresh the page and try again.'; } elseif ( ! empty( $p['website_confirm'] ) || ! Security::time_ok( Security::scalar( $p['issued'] ?? '' ), Security::scalar( $p['finger'] ?? '' ), $id ) ) {
				$error = 'Please wait a moment after opening the form, then try again.'; } else {
				$ip = filter_var( $_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP ) ?: 'unknown';
				if ( ! Security::rate( $id, $ip ) ) {
					$error = 'Too many submissions. Please try again in a few minutes.'; } else {
								$c          = Store::decode( $form['config'] );
								$validation = self::validate( $c['fields'] ?? array(), is_array( $p['fields'] ?? null ) ? $p['fields'] : array() );
					if ( is_string( $validation ) ) {
						$error = 'Check the form fields and try again.'; } elseif ( $lead = self::record( $id, $validation, $p ) ) {
						Settings::notify( $lead );
						if ( $target = Security::local_url( $c['redirect'] ?? '' ) ) {
							wp_safe_redirect( $target, 303 );
							exit; }
						} else {
							$error = 'This enquiry may already have been recorded. Please try again later if needed.'; }
					}
				}
				$notice = $error ?: 'ok';
				wp_safe_redirect(
					add_query_arg(
						array(
							'lf_form'   => $id,
							'lf_notice' => rawurlencode( $notice ),
							'lf_sig'    => Security::sign( 'notice|' . $id . '|' . $notice ),
						),
						$return
					) . '#htecl-form-' . $id,
					303
				);
		exit;
	}
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'id' => '0' ), $atts, 'htech_lead_form' );
		$id   = absint( $atts['id'] );
		$form = Store::get( 'forms', $id );
		if ( ! $form || ! (int) $form['active'] ) {
			return '<p>' . esc_html__( 'This enquiry form is unavailable.', 'htech-leadflow' ) . '</p>'; }
		wp_enqueue_style( 'htecl-public' );
		wp_enqueue_script( 'htecl-public' );
		$c       = Store::decode( $form['config'] );
		$uid     = wp_unique_id( 'htecl-field-' );
		$issued  = (string) time();
		$landing = is_singular() ? get_permalink() : home_url( '/' );
		$msg     = '';
		$notice  = Security::text( wp_unslash( $_GET['lf_notice'] ?? '' ) );
		if ( (int) Security::scalar( $_GET['lf_form'] ?? '' ) === $id && hash_equals( Security::sign( 'notice|' . $id . '|' . $notice ), Security::scalar( $_GET['lf_sig'] ?? '' ) ) ) {
			$msg = $notice === 'ok' ? ( $c['success'] ?? 'Thank you.' ) : $notice;
		}
		ob_start(); ?>
		<form id="<?php echo esc_attr( wp_unique_id( 'htecl-form-instance-' ) ); ?>" class="htecl-public htecl-<?php echo esc_attr( $c['style'] ?? 'card' ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php
			if ( $msg !== '' ) {
				?>
				<p id="htecl-form-<?php echo esc_attr( (string) $id ); ?>" class="htecl-public-notice" role="<?php echo $notice === 'ok' ? 'status' : 'alert'; ?>"><?php echo esc_html( $msg ); ?></p><?php } ?>
			<input type="hidden" name="action" value="htecl_submit"><input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $id ); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'htecl_submit_' . $id ) ); ?>">
			<input type="hidden" name="issued" value="<?php echo esc_attr( $issued ); ?>"><input type="hidden" name="finger" value="<?php echo esc_attr( Security::sign( $id . '|' . $issued ) ); ?>">
			<input type="hidden" name="return_url" value="<?php echo esc_url( $landing ); ?>">
			<input type="hidden" name="landing" value="<?php echo esc_url( $landing ); ?>">
			<?php
			foreach ( array( 'referrer', 'ref_state', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) as $name ) {
				?>
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value=""><?php } ?>
			<div class="htecl-trap" aria-hidden="true"><label>Leave this empty <input name="website_confirm" tabindex="-1" autocomplete="off"></label></div>
			<?php
			foreach ( $c['fields'] ?? array() as $f ) {
				$type  = $f['type'];
				$field = $uid . '-' . $f['key'];
				$name  = 'fields[' . $f['key'] . ']';
				$hint  = $f['description'] ?? '';
				$attrs = ' id="' . esc_attr( $field ) . '" name="' . esc_attr( $name ) . '"' . ( ! empty( $f['required'] ) ? ' required' : '' ) . ( $hint ? ' aria-describedby="' . esc_attr( $field . '-hint' ) . '"' : '' );
				?>
				<div class="htecl-field"><label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $f['label'] ); ?>
				<?php
				if ( ! empty( $f['required'] ) ) {
					?>
					<span aria-hidden="true">*</span><?php } ?></label>
				<?php
				if ( $type === 'textarea' ) {
					?>
					<textarea<?php echo $attrs; ?> maxlength="5000" placeholder="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>" rows="4"></textarea>
					<?php
				} elseif ( $type === 'select' ) {
					?>
					<select<?php echo $attrs; ?>><option value=""><?php echo esc_html( ( $f['placeholder'] ?? '' ) ?: 'Select an option' ); ?></option>
					<?php
					foreach ( $f['options'] as $option ) {
						?>
	<option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option><?php } ?></select>
					<?php
				} else {
					?>
					<input<?php echo $attrs; ?> type="<?php echo esc_attr( $type ); ?>" 
					<?php
					if ( $type === 'checkbox' ) {
						?>
	value="1"
						<?php
					} else {
						?>
	placeholder="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>" 
						<?php
						if ( $type === 'number' ) {
							?>
					step="any"
										<?php
						} else {
							?>
						maxlength="<?php echo $type === 'tel' ? '32' : '190'; ?>"
							<?php
						}
					}
					?>
					>
					<?php
				}
				if ( $hint ) {
					?>
					<small id="<?php echo esc_attr( $field . '-hint' ); ?>"><?php echo esc_html( $hint ); ?></small><?php } ?></div>
				<?php
			} $privacy = (int) Settings::get()['privacy_page']; if ( $privacy && get_post_status( $privacy ) === 'publish' ) {
				?>
				<p><a href="<?php echo esc_url( get_permalink( $privacy ) ); ?>">Privacy policy</a></p><?php } ?>
			<button type="submit"><?php echo esc_html( $c['button'] ?? 'Send enquiry' ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}
	public static function button( bool $floating ): string {
		$s   = Settings::get();
		$url = Settings::whatsapp( $s['whatsapp'], $s['welcome'] );
		if ( ! $url || ( ! $s['desktop'] && ! $s['mobile'] ) ) {
			return ''; }
		wp_enqueue_style( 'htecl-public' );
		$classes = 'htecl-wa' . ( $floating ? ' htecl-float htecl-' . $s['wa_position'] : '' ) . ( ! $s['desktop'] ? ' htecl-hide-desktop' : '' ) . ( ! $s['mobile'] ? ' htecl-hide-mobile' : '' );
		return '<a class="' . esc_attr( $classes ) . '" style="--htecl-wa:' . esc_attr( sanitize_hex_color( $s['wa_color'] ) ?: '#0A1F44' ) . '" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $s['wa_label'] ) . '</a>';
	}
}
