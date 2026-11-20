<?php
namespace HTECH\LeadFlow;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
require_once __DIR__ . '/Screens.php';

final class Admin {
	private static array $hooks = array();
	public const PAGES = array(
		'overview'      => array( 'Overview', 'view' ),
		'leads'         => array( 'Leads', 'view' ),
		'forms'         => array( 'Forms', 'forms' ),
		'pipeline'      => array( 'Pipeline', 'view' ),
		'followups'     => array( 'Follow-ups', 'view' ),
		'analytics'     => array( 'Analytics', 'analytics' ),
		'whatsapp'      => array( 'WhatsApp Settings', 'settings' ),
		'notifications' => array( 'Notifications', 'settings' ),
		'settings'      => array( 'General Settings', 'settings' ),
		'tools'         => array( 'Tools', 'view' ),
	);
	public static function boot(): void {
		add_action( 'admin_menu', array( self::class, 'menus' ) );
		add_action(
			'admin_enqueue_scripts',
			static function ( string $hook ) {
				if ( ! in_array( $hook, self::$hooks, true ) ) {
					return; }
				wp_enqueue_style( 'htecl-admin', plugins_url( 'assets/admin.css', FILE ), array(), VERSION );
				wp_enqueue_script( 'htecl-admin', plugins_url( 'assets/admin.js', FILE ), array(), VERSION, true );
			}
		);
		add_action( 'admin_post_htecl_admin', array( self::class, 'handle' ) );
	}
	public static function page(): string {
		$raw = Security::text( $_GET['page'] ?? '' );
		return str_starts_with( $raw, 'htecl-' ) ? substr( $raw, 6 ) : '';
	}
	public static function url( string $page, array $args = array() ): string {
		return add_query_arg( array( 'page' => 'htecl-' . $page ) + $args, admin_url( 'admin.php' ) ); }
	public static function menus(): void {
		$allowed = array_filter( self::PAGES, static fn( $p )=>current_user_can( 'htecl_' . $p[1] ) );
		if ( ! $allowed ) {
			return; }
		$first = array_key_first( $allowed );
		self::$hooks[] = add_menu_page( 'HTECH LeadFlow', 'LeadFlow', 'htecl_' . $allowed[ $first ][1], 'htecl-' . $first, array( self::class, 'render' ), 'dashicons-chart-area', 27 );
		foreach ( $allowed as $slug => [$name, $cap] ) {
			self::$hooks[] = add_submenu_page( 'htecl-' . $first, $name, $name, 'htecl_' . $cap, 'htecl-' . $slug, array( self::class, 'render' ) ); }
	}
	public static function complete( string $page, string $message, array $args = array(), bool $error = false ): void {
		set_transient( 'htecl_flash_' . get_current_user_id(), array( $message, $error ), 60 );
		wp_safe_redirect( self::url( $page, $args ), 303 );
		exit;
	}
	public static function fields( string $op, int $id = 0 ): void {
		echo '<input type="hidden" name="action" value="htecl_admin"><input type="hidden" name="op" value="' . esc_attr( $op ) . '"><input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '">';
		wp_nonce_field( 'htecl_' . $op . '_' . $id, 'htecl_nonce', false );
	}
	public static function start( string $op, int $id = 0, string $class = '' ): void {
		echo '<form class="' . esc_attr( $class ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		self::fields( $op, $id );
	}
	/** Keep delivery settings out of the form-editing capability. */
	public static function form_config( array $input, ?array $form ): array|string {
		if ( ! current_user_can( 'htecl_settings' ) ) {
			$stored = $form ? Store::decode( $form['config'] ) : array();
			$input['recipient'] = $stored['recipient'] ?? '';
		}
		return Capture::config( $input );
	}
	public static function handle(): void {
		if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
			wp_die( 'Method not allowed.', '', array( 'response' => 405 ) ); }
		$p   = wp_unslash( $_POST );
		$op  = Security::text( $p['op'] ?? '' );
		$id  = absint( Security::scalar( $p['id'] ?? '' ) );
		$cap = array(
			'lead'           => 'edit',
			'stage'          => 'edit',
			'note'           => 'edit',
			'follow'         => 'edit',
			'done'           => 'edit',
			'merge'          => 'edit',
			'delete'         => 'delete',
			'bulk_delete'    => 'delete',
			'form'           => 'forms',
			'form_delete'    => 'forms',
			'whatsapp'       => 'settings',
			'notifications'  => 'settings',
			'settings'       => 'settings',
			'setup'          => 'settings',
			'setup_finish'   => 'settings',
			'setup_skip'     => 'settings',
			'export'         => 'export',
			'import_preview' => 'edit',
			'import_apply'   => 'edit',
		)[ $op ] ?? '';
		if ( ! $cap ) {
			wp_die( 'Unknown operation.', '', array( 'response' => 400 ) ); }
		Security::require( $cap );
		if ( in_array( $op, array( 'lead', 'stage', 'note', 'follow', 'done', 'merge', 'delete', 'bulk_delete', 'export', 'import_preview', 'import_apply' ), true ) ) {
			Security::require( 'view' ); }
		if ( ! Security::ok_nonce( 'htecl_' . $op . '_' . $id, $p['htecl_nonce'] ?? $p[ 'htecl_' . $op . '_nonce' ] ?? '' ) ) {
			wp_die( 'This action expired. Reload the page and try again.', '', array( 'response' => 403 ) ); }
		switch ( $op ) {
			case 'form':
				$title  = Security::text( $p['title'] ?? '' );
				$form   = $id ? Store::get( 'forms', $id ) : null;
				$config = self::form_config( $p, $form );
				if ( ! $title || is_string( $config ) ) {
					self::complete( 'forms', is_string( $config ) ? $config : 'Provide a form title.', $id ? array( 'id' => $id ) : array( 'new' => 1 ), true ); }
				$values = array(
					'title'  => $title,
					'config' => Store::json( $config ),
					'active' => ! empty( $p['active'] ) ? 1 : 0,
				);
				if ( $id ) {
					$ok = $form && Store::update( 'forms', $id, $values ); } else {
					$id = Store::insert( 'forms', $values + array( 'created' => Store::now() ) );
					$ok = (bool) $id; }
					self::complete( 'forms', $ok ? 'Form saved.' : 'Could not save the form.', $id ? array( 'id' => $id ) : array(), ! $ok );
				case 'form_delete':
					if ( ( $p['confirmed'] ?? '' ) !== '1' ) {
						self::complete( 'forms', 'Confirm form removal first.', array( 'id' => $id ), true ); }
					global $wpdb;
					$ok = 1 === $wpdb->delete( Store::table( 'forms' ), array( 'id' => $id ) );
					self::complete( 'forms', $ok ? 'Form removed. Existing leads were kept.' : 'Form not found.', array(), ! $ok );
				case 'setup':
					Security::require( 'forms' );
					$title = Security::text( $p['title'] ?? '' ) ?: 'Website enquiries';
					$prior = Settings::get();
					if ( $prior['setup'] ) {
						self::complete( 'overview', 'Setup has already ended.' ); }
					$error = Settings::save( 'setup', $p );
					if ( $error ) {
						self::complete( 'overview', $error, array( 'setup' => 1 ), true ); }
					$id = Store::insert(
						'forms',
						array(
							'title'   => $title,
							'active'  => 1,
							'config'  => Store::json( Capture::defaults() ),
							'created' => Store::now(),
						)
					);
					if ( ! $id ) {
						self::complete( 'overview', 'Settings saved but the form could not be created.', array( 'setup' => 1 ), true ); }
					$s               = Settings::get();
					$s['setup_form'] = $id;
					update_option( 'htecl_options', $s, false );
					self::complete( 'overview', 'Your first form is ready.', array( 'setup' => 1 ) );
				case 'setup_finish':
				case 'setup_skip':
					$s          = Settings::get();
					$s['setup'] = 1;
					update_option( 'htecl_options', $s, false );
					self::complete( 'overview', 'Setup closed. You can change these settings any time.' );
				case 'settings':
				case 'whatsapp':
				case 'notifications':
					$error = Settings::save( $op, $p );
					self::complete( $op, $error ?: 'Settings saved.', array(), (bool) $error );
				case 'delete':
				case 'bulk_delete':
							$ids = $op === 'delete' ? array( $id ) : self::ids( $p['selected'] ?? array() );
					$removed     = 0;
					if ( ( $p['confirmed'] ?? '' ) !== '1' ) {
						self::complete( 'leads', 'Confirm permanent deletion first.', array(), true ); }
					foreach ( $ids as $item ) {
						$removed += Store::delete( $item ) ? 1 : 0; }
							self::complete( 'leads', $removed . ' lead(s) deleted.', array(), $removed !== count( $ids ) || ! $ids );
				case 'lead':
				case 'stage':
								self::save_lead( $op, $id, $p );
				break;
			case 'note':
			case 'follow':
			case 'done':
					self::related( $op, $id, $p );
				break;
			case 'merge':
				Security::require( 'delete' );
				if ( ( $p['confirmed'] ?? '' ) !== '1' ) {
					self::complete( 'leads', 'Confirm you reviewed both records.', array( 'id' => $id ), true ); }
				Tools::merge( $id, absint( Security::scalar( $p['other'] ?? '' ) ) );
				break;
			case 'export':
				Tools::export( self::ids( $p['selected'] ?? array() ), ! empty( $p['all'] ) );
				break;
			case 'import_preview':
				Tools::preview( $p );
				break;
			case 'import_apply':
				Tools::apply( $p );
				break;
		}
	}
	public static function ids( $raw ): array {
		return array_values( array_unique( array_filter( array_map( static fn( $v )=>absint( Security::scalar( $v ) ), array_slice( (array) $raw, 0, 100 ) ) ) ) ); }
	private static function save_lead( string $op, int $id, array $p ): void {
		$status = Security::text( $p['status'] ?? '' );
		$values = array();
		$args   = array( 'id' => $id );
		if ( ! isset( Store::STAGES[ $status ] ) ) {
			self::complete( 'leads', 'Invalid stage.', $args, true ); }
		$values['status'] = $status;
		if ( $op === 'lead' ) {
			foreach ( Capture::MAP as $key => $_ ) {
				$raw            = trim( Security::scalar( $p[ $key ] ?? '' ) );
				$values[ $key ] = match ( $key ) {
					'email'=>Security::email( $raw ), 'phone'=>Security::phone( $raw, Settings::get()['country'] ), 'message'=>Security::body( $raw ), default=>Security::text( $raw )};
				if ( $raw !== '' && $values[ $key ] === '' && in_array( $key, array( 'email', 'phone' ), true ) ) {
					self::complete( 'leads', 'Enter a valid ' . $key . '.', $args, true ); }
			}
			$values['source'] = Security::text( $p['source'] ?? '' ) ?: 'Unavailable';
			$assigned         = absint( Security::scalar( $p['assigned'] ?? '' ) );
			if ( $assigned && ! user_can( $assigned, 'htecl_view' ) ) {
				self::complete( 'leads', 'Choose an assignee who can view leads.', $args, true ); }
			$values['assigned'] = $assigned;
		}
		$assignment = false;
		$ok         = Store::transaction(
			static function () use ( $id, $values, &$assignment ) {
				$old = Store::lock( $id );
				if ( ! $old ) {
					return false; }
				$next = $values + array(
					'score'   => Settings::score( $values + $old ),
					'updated' => Store::now(),
				);
				if ( ! Store::update( 'leads', $id, $next ) ) {
					return false; }
				if ( $old['status'] !== $values['status'] && ! Store::activity( $id, 'Stage changed from ' . Store::STAGES[ $old['status'] ] . ' to ' . Store::STAGES[ $values['status'] ] ) ) {
					return false; }
				if ( isset( $values['assigned'] ) && (int) $old['assigned'] !== $values['assigned'] ) {
					$assignment = $values['assigned'] > 0;
					if ( ! Store::activity( $id, $assignment ? 'Lead assigned to user #' . $values['assigned'] : 'Lead assignment cleared' ) ) {
						return false; }
				}
				return count( $values ) === 1 || Store::activity( $id, 'Lead details updated' );
			}
		);
		if ( $ok && $assignment ) {
			Settings::notify( $id, true ); }
		$return = ( $op === 'stage' && ( $p['back'] ?? '' ) === 'pipeline' ) ? 'pipeline' : 'leads';
		self::complete( $return, $ok ? 'Lead saved.' : 'Could not save the lead.', $return === 'leads' ? $args : array(), ! $ok );
	}
	private static function related( string $op, int $id, array $p ): void {
		$body = Security::body( $p['body'] ?? '', 2000 );
		$due  = Security::date( Security::text( $p['due'] ?? '' ) );
		$item = absint( Security::scalar( $p['item'] ?? '' ) );
		if ( ( $op === 'note' && ! $body ) || ( $op === 'follow' && ! $due ) ) {
			self::complete( 'leads', 'Provide the required note or date.', array( 'id' => $id ), true ); }
		$ok   = Store::transaction(
			static function () use ( $op, $id, $body, $due, $item ) {
				if ( ! Store::lock( $id ) ) {
					return false; }
				if ( $op === 'note' ) {
					$saved = Store::insert(
						'notes',
						array(
							'lead_id' => $id,
							'body'    => $body,
							'actor'   => get_current_user_id(),
							'created' => Store::now(),
						)
					); } elseif ( $op === 'follow' ) {
							$saved = Store::insert(
								'followups',
								array(
									'lead_id' => $id,
									'due'     => $due,
									'body'    => $body,
									'created' => Store::now(),
								)
							); } else {
								global $wpdb;
								$saved = 1 === $wpdb->update(
									Store::table( 'followups' ),
									array( 'done' => 1 ),
									array(
										'id'      => $item,
										'lead_id' => $id,
										'done'    => 0,
									)
								);
							}
							return $saved && Store::activity(
								$id,
								array(
									'note'   => 'Internal note added',
									'follow' => 'Follow-up scheduled',
									'done'   => 'Follow-up completed',
								)[ $op ]
							) && Store::update( 'leads', $id, array( 'updated' => Store::now() ) );
			}
		);
		$back = ( $p['back'] ?? '' ) === 'followups' ? 'followups' : 'leads';
		self::complete( $back, $ok ? 'Update saved.' : 'Could not save the update.', $back === 'leads' ? array( 'id' => $id ) : array(), ! $ok );
	}
	public static function render(): void {
		$page = self::page();
		if ( ! isset( self::PAGES[ $page ] ) ) {
			return; }
		Security::require( self::PAGES[ $page ][1] );
		$flash = get_transient( 'htecl_flash_' . get_current_user_id() );
		delete_transient( 'htecl_flash_' . get_current_user_id() );
		echo '<div class="wrap htecl-admin"><header class="htecl-hero"><div><span class="htecl-kicker">HTECH DIGITAL SOLUTIONS / LEADFLOW</span><h1>' . esc_html( self::PAGES[ $page ][0] ) . '</h1><p>Capture. Organize. Convert.</p></div><span class="htecl-pill">SELF-HOSTED CRM</span></header>';
		if ( $flash ) {
			echo '<div role="status" class="htecl-flash' . ( $flash[1] ? ' is-error' : '' ) . '">' . esc_html( $flash[0] ) . '</div>'; }
		match ( $page ) {
			'overview'=>Screens::overview(), 'leads'=>Screens::leads(), 'forms'=>Screens::forms(), 'pipeline'=>Screens::pipeline(),
			'followups'=>Screens::followups(), 'analytics'=>Screens::analytics(), 'tools'=>Tools::screen(), default=>Screens::settings( $page ),
		};
		echo '</div>';
	}
}
