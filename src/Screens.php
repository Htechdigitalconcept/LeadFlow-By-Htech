<?php
namespace HTECH\LeadFlow;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class Screens {
	public static function e( $value ): string {
		return esc_html( (string) $value ); }
	public static function a( $value ): string {
		return esc_attr( (string) $value ); }
	public static function link( string $page, string $label, array $args = array(), string $class = '' ): void {
		echo '<a class="' . self::a( $class ) . '" href="' . esc_url( Admin::url( $page, $args ) ) . '">' . self::e( $label ) . '</a>';
	}
	public static function button( string $label, bool $primary = true, string $extra = '' ): void {
		echo '<button type="submit" class="button ' . ( $primary ? 'button-primary' : '' ) . '" ' . $extra . '>' . self::e( $label ) . '</button>';
	}
	public static function input( string $name, string $label, $value = '', string $type = 'text', bool $required = false ): void {
		echo '<label class="htecl-label"><span>' . self::e( $label ) . '</span><input type="' . self::a( $type ) . '" name="' . self::a( $name ) . '" value="' . self::a( $value ) . '" ' . ( $required ? 'required' : '' ) . ( $type === 'number' ? ' min="0"' : '' ) . '></label>';
	}
	public static function textarea( string $name, string $label, $value = '', bool $required = false ): void {
		echo '<label class="htecl-label"><span>' . self::e( $label ) . '</span><textarea name="' . self::a( $name ) . '" rows="3" ' . ( $required ? 'required' : '' ) . '>' . esc_textarea( (string) $value ) . '</textarea></label>';
	}
	public static function check( string $name, string $label, $value = false ): void {
		echo '<label class="htecl-check"><input type="checkbox" name="' . self::a( $name ) . '" value="1" ' . checked( (bool) $value, true, false ) . '> ' . self::e( $label ) . '</label>'; }
	public static function select( string $name, string $label, array $options, $value = '' ): void {
		$id = wp_unique_id( 'htecl-select-' );
		echo '<div class="htecl-label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $options as $key => $text ) {
			echo '<option value="' . esc_attr( (string) $key ) . '" ' . selected( (string) $key, (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select></div>';
	}
	public static function nav( string $page, array $args, int $pg, bool $next ): void {
		if ( $pg === 1 && ! $next ) {
			return; }
		echo '<nav class="htecl-actions" aria-label="Pagination">';
		if ( $pg > 1 ) {
			self::link( $page, 'Previous', $args + array( 'pg' => $pg - 1 ), 'button' ); }
		echo '<span>Page ' . self::e( $pg ) . '</span>';
		if ( $next ) {
			self::link( $page, 'Next', $args + array( 'pg' => $pg + 1 ), 'button' ); }
		echo '</nav>';
	}
	public static function query( string $key ): string {
		return Security::text( wp_unslash( $_GET[ $key ] ?? '' ) ); }
	public static function pg(): int {
		return min( 100000, max( 1, absint( self::query( 'pg' ) ) ) ); }
	private static function counts( array $filters = array() ): array {
		$data = array_fill_keys( array_keys( Store::STAGES ), 0 );
		foreach ( Store::groups( 'status', $filters, 20 ) as $row ) {
			$data[ $row['label'] ] = (int) $row['total']; }
		return $data;
	}
	private static function stat( string $label, $value ): void {
		echo '<div class="htecl-stat"><span>' . self::e( $label ) . '</span><strong>' . self::e( $value ) . '</strong></div>'; }
	public static function overview(): void {
		$s = Settings::get();
		if ( current_user_can( 'htecl_settings' ) && current_user_can( 'htecl_forms' ) && ( ! $s['setup'] || self::query( 'setup' ) ) ) {
			if ( ! $s['setup'] ) {
				echo '<section class="htecl-card"><span class="htecl-kicker">GET STARTED</span><h2>Set up your lead workspace</h2>';
				if ( self::query( 'setup' ) ) {
					if ( ! empty( $s['setup_form'] ) && Store::get( 'forms', (int) $s['setup_form'] ) ) {
						echo '<p><strong>Step 7.</strong> Copy this into a Shortcode block or Elementor’s Shortcode widget:</p><code>[htech_lead_form id="' . self::e( $s['setup_form'] ) . '"]</code>';
						Admin::start( 'setup_finish' );
						self::button( 'Step 8 · Finish setup' );
						echo '</form>';
					} else {
						echo '<p><strong>Step 1.</strong> Welcome to HTECH LeadFlow. Your leads stay in this WordPress installation.</p>';
						Admin::start( 'setup', 0, 'htecl-stack' );
						self::input( 'business', 'Step 2 · Business name', $s['business'], 'text', true );
						self::input( 'email', 'Step 3 · Business email', $s['email'], 'email', true );
						self::input( 'whatsapp', 'Step 4 · WhatsApp number (optional)', $s['whatsapp'], 'tel' );
						self::input( 'title', 'Step 5 · First enquiry form', 'Website enquiries', 'text', true );
						self::check( 'notify', 'Step 6 · Email me when a visitor submits a form', $s['notify'] );
						self::input( 'recipient', 'Recipient override (optional)', $s['recipient'], 'email' );
						self::button( 'Save and show shortcode' );
						echo '</form>';
					}
				} else {
					self::link( 'overview', 'Open setup wizard', array( 'setup' => 1 ), 'button button-primary' ); }
				Admin::start( 'setup_skip' );
				self::button( 'Skip setup', false );
				echo '</form></section>';
			}
		}
		$c = self::counts();
		echo '<section class="htecl-stats" aria-label="Lead snapshot">';
		foreach ( array(
			'Total leads'        => array_sum( $c ),
			'New'                => $c['new'],
			'Qualified'          => $c['qualified'],
			'Converted'          => $c['converted'],
			'Overdue follow-ups' => self::follow_count(),
		) as $name => $total ) {
			self::stat( $name, $total ); }
		echo '</section><section class="htecl-card"><h2>Next actions</h2><div class="htecl-actions">';
		self::link( 'leads', 'Browse leads', array(), 'button button-primary' );
		self::link( 'followups', 'Today’s follow-ups', array( 'when' => 'today' ), 'button' );
		self::link( 'pipeline', 'Open pipeline', array(), 'button' );
		if ( current_user_can( 'htecl_forms' ) ) {
			self::link( 'forms', 'Create a form', array( 'new' => 1 ), 'button' ); }
		echo '</div><p>' . self::e( array_sum( $c ) ? 'Visit the pipeline to update a lead’s stage, or check Follow-ups for upcoming work.' : 'No leads yet. Publish an enquiry form on a page to begin capturing leads.' ) . '</p></section>';
	}
	private static function filters(): array {
		$out = array();
		foreach ( array( 'status', 'source', 'from', 'to', 'q', 'sort' ) as $k ) {
			if ( ( $v = self::query( $k ) ) !== '' ) {
				$out[ $k ] = $v;
			}
		} return $out; }
	private static function filter_ui( string $page, array $f, bool $full = false ): void {
		echo '<form class="htecl-card htecl-filters" method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . self::a( 'htecl-' . $page ) . '">';
		if ( $full ) {
			self::input( 'q', 'Search leads', $f['q'] ?? '', 'search' );
			self::select( 'status', 'Stage', array( '' => 'All stages' ) + Store::STAGES, $f['status'] ?? '' );
			self::input( 'source', 'Source (exact match)', $f['source'] ?? '' );
			self::select(
				'sort',
				'Sort by',
				array(
					''      => 'Newest first',
					'old'   => 'Oldest first',
					'name'  => 'Name',
					'score' => 'Lead score',
				),
				$f['sort'] ?? ''
			);
		}
		self::input( 'from', 'From', $f['from'] ?? '', 'date' );
		self::input( 'to', 'Through', $f['to'] ?? '', 'date' );
		self::button( 'Apply filters' );
		self::link( $page, 'Clear' );
		echo '</form>';
	}
	public static function leads(): void {
		if ( $id = absint( self::query( 'id' ) ) ) {
			self::detail( $id );
			return; }
		$f    = self::filters();
		$pg   = self::pg();
		$rows = Store::leads( $f, 31, ( $pg - 1 ) * 30 );
		self::filter_ui( 'leads', $f, true );
		if ( ! $rows ) {
			echo '<div class="htecl-card"><h2>No matching leads</h2><p>Try clearing filters, or publish a form to get your first enquiry.</p></div>';
			self::nav( 'leads', $f, $pg, false );
			return; }
		$export = current_user_can( 'htecl_export' );
		$delete = current_user_can( 'htecl_delete' );
		echo '<section class="htecl-card"><div class="htecl-actions"><h2>Lead records</h2><span class="htecl-chip">' . self::e( Store::count( $f ) ) . ' matching</span></div>';
		if ( $export || $delete ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="htecl_admin"><input type="hidden" name="id" value="0">'; }
		echo '<div class="htecl-scroll"><table class="widefat striped"><thead><tr>' . ( $export || $delete ? '<th scope="col">Select</th>' : '' ) . '<th scope="col">Lead</th><th scope="col">Stage</th><th scope="col">Source</th><th scope="col">Priority</th><th scope="col">Received</th></tr></thead><tbody>';
		$threshold = Settings::get()['score_threshold'];
		foreach ( array_slice( $rows, 0, 30 ) as $row ) {
			$id = (int) $row['id'];
			echo '<tr>';
			if ( $export || $delete ) {
				echo '<td><input aria-label="Select lead ' . self::a( $id ) . '" type="checkbox" name="selected[]" value="' . self::a( $id ) . '"></td>'; }
			echo '<td><strong>';
			self::link( 'leads', $row['name'] ?: 'Lead #' . $id, array( 'id' => $id ) );
			echo '</strong><br>' . self::e( $row['email'] ) . '</td><td><span class="htecl-chip">' . self::e( Store::STAGES[ $row['status'] ] ) . '</span></td><td>' . self::e( $row['source'] ) . '</td><td>' . self::e( ( (int) $row['score'] >= $threshold ? 'High' : 'Normal' ) . ' · ' . $row['score'] ) . '</td><td>' . self::e( get_date_from_gmt( $row['created'], 'M j, Y' ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		if ( $export || $delete ) {
			echo '<div class="htecl-actions">';
			if ( $export ) {
				self::button( 'Export selected', false, 'name="op" value="export"' );
				wp_nonce_field( 'htecl_export_0', 'htecl_export_nonce', false ); }
			if ( $delete ) {
				self::check( 'confirmed', 'Confirm permanent deletion of selected leads' );
				self::button( 'Delete selected', false, 'name="op" value="bulk_delete"' );
				wp_nonce_field( 'htecl_bulk_delete_0', 'htecl_bulk_delete_nonce', false ); }
			echo '</div></form>';
		}
		self::nav( 'leads', $f, $pg, count( $rows ) > 30 );
		echo '</section>';
	}
	private static function detail( int $id ): void {
		$r = Store::get( 'leads', $id );
		self::link( 'leads', '← All leads' );
		if ( ! $r ) {
			echo '<p>Lead not found.</p>';
			return; }
		$edit = current_user_can( 'htecl_edit' );
		$form = $r['form_id'] ? Store::get( 'forms', (int) $r['form_id'] ) : null;
		$s    = Settings::get();
		$msg  = strtr(
			$s['follow_text'],
			array(
				'[Name]'          => $r['name'] ?: 'there',
				'[Business Name]' => $s['business'],
			)
		);
		echo '<section class="htecl-card"><span class="htecl-kicker">LEAD #' . self::e( $id ) . '</span><h2>' . self::e( $r['name'] ?: 'Unnamed lead' ) . '</h2><p>' . self::e( Store::STAGES[ $r['status'] ] . ' · Score ' . $r['score'] . ' · ' . ( (int) $r['score'] >= $s['score_threshold'] ? 'High priority' : 'Normal priority' ) ) . '</p><p>Received ' . self::e( get_date_from_gmt( $r['created'], 'M j, Y H:i' ) ) . ' · Updated ' . self::e( get_date_from_gmt( $r['updated'], 'M j, Y H:i' ) ) . ' · Form: ' . self::e( $form['title'] ?? ( $r['form_id'] ? 'Removed form #' . $r['form_id'] : 'No associated form' ) ) . '</p>';
		if ( $url = Settings::whatsapp( $r['phone'], $msg ) ) {
			echo '<a class="button" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">Open WhatsApp conversation</a>'; } else {
			echo '<p>Provide a valid phone number to open a WhatsApp conversation.</p>'; }
			echo '</section><div class="htecl-detail-grid"><section class="htecl-card"><h2>Contact and enquiry</h2>';
			if ( $edit ) {
				Admin::start( 'lead', $id, 'htecl-stack' );
				foreach ( Capture::MAP as $key => $label ) {
					if ( $key === 'message' ) {
								self::textarea( $key, $label, $r[ $key ] );
					} else {
							self::input(
								$key,
								$label,
								$r[ $key ],
								array(
									'email' => 'email',
									'phone' => 'tel',
								)[ $key ] ?? 'text'
							); }
				}
				self::input( 'source', 'Source', $r['source'] );
				self::select( 'status', 'Stage', Store::STAGES, $r['status'] );
				$users     = get_users(
					array(
						'capability' => 'htecl_view',
						'number'     => 200,
						'orderby'    => 'display_name',
					)
				);
				$assignees = array( 0 => 'Unassigned' );
				foreach ( $users as $user ) {
					$assignees[ $user->ID ] = $user->display_name . ' (#' . $user->ID . ')'; }
				if ( $r['assigned'] && ! isset( $assignees[ $r['assigned'] ] ) ) {
					$assignees[ $r['assigned'] ] = 'Current user #' . $r['assigned'] . ' (verify access)'; }
				self::select( 'assigned', 'Assigned user', $assignees, $r['assigned'] );
				self::button( 'Save lead' );
				echo '</form>';
			} else {
				$assigned = $r['assigned'] ? get_userdata( (int) $r['assigned'] ) : null;
				self::kv(
					array_intersect_key( $r, Capture::MAP ) + array(
						'source'   => $r['source'],
						'assigned' => $assigned ? $assigned->display_name : 'Unassigned',
					)
				);
			}
			echo '</section><section class="htecl-card"><h2>Submission and attribution</h2>';
			$custom = Store::decode( $r['fields'] );
			$at     = Store::decode( $r['attribution'] );
			foreach ( $custom as $field ) {
				echo '<p><strong>' . self::e( $field['label'] ?? 'Field' ) . ( ! empty( $field['consent'] ) ? ' (consent)' : '' ) . '</strong><br>' . nl2br( self::e( $field['value'] ?? '' ) ) . '</p>'; }
			self::kv( $at );
			if ( ! $at ) {
				echo '<p>No attribution was recorded.</p>'; }
			echo '</section><section class="htecl-card"><h2>Internal notes</h2>';
			if ( $edit ) {
				Admin::start( 'note', $id, 'htecl-stack' );
				self::textarea( 'body', 'Private note', '', true );
				self::button( 'Add note' );
				echo '</form>'; }
			self::entries( Store::children( 'notes', $id ), 'No notes yet.' );
			echo '</section><section class="htecl-card"><h2>Follow-ups</h2>';
			if ( $edit ) {
				Admin::start( 'follow', $id, 'htecl-stack' );
				self::input( 'due', 'Due date', '', 'date', true );
				self::textarea( 'body', 'Follow-up note' );
				self::button( 'Schedule follow-up' );
				echo '</form>'; }
			$follow = Store::children( 'followups', $id );
			if ( ! $follow ) {
				echo '<p>No follow-ups scheduled.</p>'; }
			foreach ( $follow as $item ) {
				self::follow_item( $item, $edit ); }
			echo '</section><section class="htecl-card"><h2>Activity history</h2>';
			self::entries( Store::children( 'activities', $id ), 'No activity recorded.' );
			echo '</section>';
			$dupes = Store::duplicates( $r['email'], $r['phone'], $id );
			if ( $dupes ) {
				echo '<section class="htecl-card"><h2>Possible duplicates</h2><p>These leads share a normalized email address or phone number. Review them before merging. The kept lead’s conflicting contact details take precedence; original submission data is archived on the kept lead.</p>';
				foreach ( $dupes as $d ) {
					echo '<div class="htecl-entry">';
					self::link( 'leads', ( $d['name'] ?: 'Lead' ) . ' #' . $d['id'], array( 'id' => $d['id'] ) );
					if ( $edit && current_user_can( 'htecl_delete' ) ) {
						Admin::start( 'merge', $id, 'htecl-stack' );
						echo '<input type="hidden" name="other" value="' . self::a( $d['id'] ) . '">';
						echo '<label><input type="checkbox" name="confirmed" value="1" required> I reviewed both records and want to keep this lead.</label>';
						self::button( 'Merge into this lead', false );
						echo '</form>';
					}
					echo '</div>';
				}
				echo '</section>';
			}
			echo '</div>';
			if ( current_user_can( 'htecl_export' ) || current_user_can( 'htecl_delete' ) ) {
				echo '<section class="htecl-card htecl-actions">';
				if ( current_user_can( 'htecl_export' ) ) {
					Admin::start( 'export' );
					echo '<input type="hidden" name="selected[]" value="' . self::a( $id ) . '">';
					self::button( 'Export lead', false );
					echo '</form>'; }
				if ( current_user_can( 'htecl_delete' ) ) {
					Admin::start( 'delete', $id );
					echo '<label><input type="checkbox" name="confirmed" value="1" required> Permanently delete this lead</label> ';
					self::button( 'Delete lead', false );
					echo '</form>'; }
				echo '</section>';
			}
	}
	private static function kv( array $list ): void {
		echo '<dl class="htecl-kv">';
		foreach ( $list as $key => $v ) {
			if ( $v !== '' && is_scalar( $v ) ) {
				echo '<dt>' . self::e( ucfirst( str_replace( '_', ' ', $key ) ) ) . '</dt><dd>' . nl2br( self::e( $v ) ) . '</dd>';
			}
		} echo '</dl>';
	}
	private static function entries( array $items, string $empty ): void {
		if ( ! $items ) {
			echo '<p>' . self::e( $empty ) . '</p>'; }
		foreach ( $items as $item ) {
			$actor = $item['actor'] ? get_userdata( (int) $item['actor'] ) : null;
			echo '<div class="htecl-entry"><small>' . self::e( get_date_from_gmt( $item['created'], 'M j, Y H:i' ) . ( $actor ? ' · ' . $actor->display_name : '' ) ) . '</small><p>' . nl2br( self::e( $item['body'] ) ) . '</p></div>';
		}
	}
	public static function forms(): void {
		$id  = absint( self::query( 'id' ) );
		$new = self::query( 'new' ) !== '';
		if ( ! $id && ! $new ) {
			$pg   = self::pg();
			$rows = Store::forms( 31, ( $pg - 1 ) * 30 );
			echo '<section class="htecl-card"><div class="htecl-actions"><h2>Enquiry forms</h2>';
			self::link( 'forms', 'New form', array( 'new' => 1 ), 'button button-primary' );
			echo '</div>';
			if ( ! $rows ) {
				echo '<p>No forms on this page. Create an enquiry form and embed it using its shortcode.</p>'; }
			foreach ( array_slice( $rows, 0, 30 ) as $f ) {
				echo '<div class="htecl-entry">';
				self::link( 'forms', $f['title'], array( 'id' => $f['id'] ) );
				echo ' <span class="htecl-chip">' . ( (int) $f['active'] ? 'Active' : 'Inactive' ) . '</span> <code>[htech_lead_form id="' . self::e( $f['id'] ) . '"]</code></div>';
			}
			self::nav( 'forms', array(), $pg, count( $rows ) > 30 );
			echo '</section>';
			return;
		}
		$form = $id ? Store::get( 'forms', $id ) : null;
		self::link( 'forms', '← All forms' );
		if ( $id && ! $form ) {
			echo '<p>Form not found.</p>';
			return; }
		$c = $form ? Store::decode( $form['config'] ) : Capture::defaults();
		echo '<section class="htecl-card"><h2>' . ( $id ? 'Edit form' : 'Create a form' ) . '</h2>';
		if ( $id ) {
			echo '<p>Embed with <code>[htech_lead_form id="' . self::e( $id ) . '"]</code> · Created ' . self::e( get_date_from_gmt( $form['created'], 'M j, Y' ) ) . '</p>'; }
		Admin::start( 'form', $id, 'htecl-stack' );
		echo '<div class="htecl-filters">';
		self::input( 'title', 'Form title', $form['title'] ?? '', 'text', true );
		self::input( 'button', 'Submit button text', $c['button'] ?? '' );
		if ( current_user_can( 'htecl_settings' ) ) {
			self::input( 'recipient', 'Notification recipient override', $c['recipient'] ?? '', 'email' );
		}
		self::input( 'redirect', 'Same-site success redirect (optional)', $c['redirect'] ?? '', 'url' );
		self::select(
			'style',
			'Form style',
			array(
				'card'  => 'Card',
				'plain' => 'Plain',
			),
			$c['style'] ?? 'card'
		);
		self::check( 'active', 'Accept submissions', $form['active'] ?? true );
		echo '</div>';
		self::textarea( 'success', 'Success message', $c['success'] ?? '' );
		echo '<h3>Form fields</h3><p>Map fields to contact details when relevant. Unmapped fields are saved with the enquiry.</p><div data-htecl-fields class="htecl-stack">';
		foreach ( $c['fields'] ?? array() as $index => $field ) {
			self::builder_field( (string) $index, $field ); }
		echo '</div><template data-htecl-template>';
		self::builder_field( '__index__', array() );
		echo '</template><div class="htecl-actions"><button type="button" class="button" data-htecl-add>Add field</button>';
		self::button( 'Save form' );
		echo '</div></form></section>';
		if ( $id ) {
			echo '<section class="htecl-card">';
			Admin::start( 'form_delete', $id );
			echo '<label><input type="checkbox" name="confirmed" value="1" required> Remove this form; keep the leads it captured</label> ';
			self::button( 'Delete form', false );
			echo '</form></section>'; }
	}
	private static function builder_field( string $index, array $f ): void {
		$base = 'fields[' . $index . '][';
		echo '<fieldset class="htecl-builder htecl-filters"><legend>' . self::e( $f['label'] ?? 'New field' ) . '</legend><input type="hidden" name="' . self::a( $base . 'key]' ) . '" value="' . self::a( $f['key'] ?? '' ) . '">';
		self::input( $base . 'label]', 'Label', $f['label'] ?? '' );
		self::select( $base . 'type]', 'Type', array_combine( Capture::TYPES, array( 'Text', 'Email', 'Telephone', 'Dropdown', 'Textarea', 'Number', 'Checkbox' ) ), $f['type'] ?? 'text' );
		self::select( $base . 'map]', 'Map to lead field', array( '' => 'Keep as custom field' ) + Capture::MAP, $f['map'] ?? '' );
		self::input( $base . 'placeholder]', 'Placeholder', $f['placeholder'] ?? '' );
		self::input( $base . 'description]', 'Description / consent wording', $f['description'] ?? '' );
		self::textarea( $base . 'options]', 'Dropdown options (one per line)', implode( "\n", $f['options'] ?? array() ) );
		echo '<div>';
		self::check( $base . 'required]', 'Required', $f['required'] ?? false );
		self::check( $base . 'consent]', 'Consent checkbox', $f['consent'] ?? false );
		self::check( $base . 'remove]', 'Remove on save' );
		echo '</div></fieldset>';
	}
	public static function pipeline(): void {
		$table  = self::query( 'mode' ) === 'table';
		$pg     = self::pg();
		$counts = self::counts();
		$edit   = current_user_can( 'htecl_edit' );
		echo '<div class="htecl-actions">';
		self::link( 'pipeline', 'Board', array(), 'button ' . ( ! $table ? 'button-primary' : '' ) );
		self::link( 'pipeline', 'Table', array( 'mode' => 'table' ), 'button ' . ( $table ? 'button-primary' : '' ) );
		echo '</div><div class="htecl-stats">';
		foreach ( Store::STAGES as $status => $label ) {
			self::stat( $label, $counts[ $status ] );
		} echo '</div>';
		if ( $table ) {
			$rows = Store::leads( array(), 31, ( $pg - 1 ) * 30 );
			echo '<section class="htecl-card htecl-scroll"><table class="widefat striped"><thead><tr><th>Lead</th><th>Stage</th><th>Move to stage</th></tr></thead><tbody>';
			if ( ! $rows ) {
				echo '<tr><td colspan="3">No leads on this page.</td></tr>'; }
			foreach ( array_slice( $rows, 0, 30 ) as $r ) {
				echo '<tr><td>';
				self::link( 'leads', $r['name'] ?: 'Lead #' . $r['id'], array( 'id' => $r['id'] ) );
				echo '</td><td>' . self::e( Store::STAGES[ $r['status'] ] ) . '</td><td>';
				if ( $edit ) {
					self::move( $r );
				} else {
					echo 'View only';
				} echo '</td></tr>'; }
			echo '</tbody></table>';
			self::nav( 'pipeline', array( 'mode' => 'table' ), $pg, count( $rows ) > 30 );
			echo '</section>';
			return;
		}
		echo '<p>The board shows the 8 most recent leads per stage. Open the table for all leads.</p><div class="htecl-board">';
		foreach ( Store::STAGES as $status => $label ) {
			echo '<section class="htecl-lane"><h2>' . self::e( $label ) . ' <span>' . self::e( $counts[ $status ] ) . '</span></h2>';
			$rows = Store::leads( array( 'status' => $status ), 8 );
			if ( ! $rows ) {
				echo '<p>No leads in this stage.</p>'; }
			foreach ( $rows as $r ) {
				echo '<article class="htecl-tile">';
				self::link( 'leads', $r['name'] ?: 'Lead #' . $r['id'], array( 'id' => $r['id'] ) );
				echo '<small>' . self::e( $r['source'] ) . '</small>';
				if ( $edit ) {
					self::move( $r );
				} echo '</article>'; }
			echo '</section>';
		}
		echo '</div>';
	}
	private static function move( array $r ): void {
		Admin::start( 'stage', (int) $r['id'], 'htecl-actions htecl-move' );
		echo '<input type="hidden" name="back" value="pipeline">';
		self::select( 'status', 'Move lead ' . (int) $r['id'], Store::STAGES, $r['status'] );
		self::button( 'Move', false );
		echo '</form>';
	}
	private static function follow_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE done=0 AND due<%s', Store::table( 'followups' ), wp_date( 'Y-m-d' ) ) );
	}
	public static function followups(): void {
		global $wpdb;
		$when = self::query( 'when' );
		$lead = absint( self::query( 'lead' ) );
		$pg   = self::pg();
		$date = wp_date( 'Y-m-d' );
		$sql  = array(
			'today'    => 'done=0 AND due=%s',
			'overdue'  => 'done=0 AND due<%s',
			'upcoming' => 'done=0 AND due>%s',
			'done'     => 'done=1',
		)[ $when ] ?? 'done=0';
		$args = array( Store::table( 'followups' ) );
		if ( str_contains( $sql, '%s' ) ) {
			$args[] = $date; }
		if ( $lead ) {
			$sql   .= ' AND lead_id=%d';
			$args[] = $lead; }
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE $sql ORDER BY due ASC,id ASC LIMIT %d OFFSET %d", array_merge( $args, array( 31, ( $pg - 1 ) * 30 ) ) ), ARRAY_A ) ?: array();
		echo '<form method="get" class="htecl-card htecl-filters" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="htecl-followups">';
		self::select(
			'when',
			'Show',
			array(
				''         => 'All open',
				'overdue'  => 'Overdue',
				'today'    => 'Due today',
				'upcoming' => 'Upcoming',
				'done'     => 'Completed',
			),
			$when
		);
		self::input( 'lead', 'Lead ID (optional)', $lead ?: '', 'number' );
		self::button( 'Filter' );
		echo '</form><section class="htecl-card">';
		if ( ! $items ) {
			echo '<p>No matching follow-ups. Open a lead to schedule one.</p>'; }
		foreach ( array_slice( $items, 0, 30 ) as $item ) {
			echo '<div class="htecl-entry">';
			self::link( 'leads', 'Lead #' . $item['lead_id'], array( 'id' => $item['lead_id'] ) );
			self::follow_item( $item, current_user_can( 'htecl_edit' ), true );
			echo '</div>'; }
		self::nav(
			'followups',
			array(
				'when' => $when,
				'lead' => $lead,
			),
			$pg,
			count( $items ) > 30
		);
		echo '</section>';
	}
	private static function follow_item( array $item, bool $edit, bool $global = false ): void {
		echo '<div class="htecl-entry"><strong>' . self::e( $item['due'] ) . '</strong> <span class="htecl-chip">' . ( $item['done'] ? 'Completed' : ( $item['due'] < wp_date( 'Y-m-d' ) ? 'Overdue' : 'Open' ) ) . '</span><p>' . nl2br( self::e( $item['body'] ) ) . '</p>';
		if ( $edit && ! $item['done'] ) {
			Admin::start( 'done', (int) $item['lead_id'] );
			echo '<input type="hidden" name="item" value="' . self::a( $item['id'] ) . '"><input type="hidden" name="back" value="' . ( $global ? 'followups' : 'leads' ) . '">';
			self::button( 'Mark complete', false );
			echo '</form>'; }
		echo '</div>';
	}
	public static function analytics(): void {
		$f = array_intersect_key(
			self::filters(),
			array(
				'from' => 1,
				'to'   => 1,
			)
		);
		self::filter_ui( 'analytics', $f );
		$c     = self::counts( $f );
		$total = array_sum( $c );
		$today = current_datetime();
		$start = absint( get_option( 'start_of_week', 1 ) );
		$week  = $today->modify( '-' . ( ( (int) $today->format( 'w' ) - $start + 7 ) % 7 ) . ' days' )->format( 'Y-m-d' );
		echo '<article class="htecl-report"><div class="htecl-actions"><h2>Lead generation report</h2><button type="button" class="button" data-htecl-print>Print report</button></div><p>Reporting period: ' . self::e( ( $f['from'] ?? 'All earlier dates' ) . ' through ' . ( $f['to'] ?? 'present' ) ) . ' (site time zone).</p><div class="htecl-stats">';
		self::stat( 'Total leads', $total );
		self::stat( 'Conversion rate', Store::conversion( $c['converted'], $total ) . '%' );
		foreach ( array(
			'Today'      => $today->format( 'Y-m-d' ),
			'This week'  => $week,
			'This month' => $today->format( 'Y-m-01' ),
		) as $label => $from ) {
			self::stat(
				$label . ' (unfiltered)',
				Store::count(
					array(
						'from' => $from,
						'to'   => $today->format( 'Y-m-d' ),
					)
				)
			); }
		echo '</div><p>Conversion rate = leads currently marked Converted ÷ all leads received in the reporting period. No leads = 0%.</p><div class="htecl-detail-grid">';
		$status = array();
		foreach ( $c as $key => $count ) {
			$status[] = array(
				'label' => Store::STAGES[ $key ] ?? $key,
				'total' => $count,
			); }
		self::chart( 'Stage breakdown', $status );
		self::chart( 'Lead sources', Store::groups( 'source', $f ) );
		$forms = Store::groups( 'form_id', $f );
		foreach ( $forms as &$form ) {
			$id            = (int) $form['label'];
			$row           = $id ? Store::get( 'forms', $id ) : null;
			$form['label'] = $row['title'] ?? ( $id ? 'Removed form #' . $id : 'No associated form' );
		} unset( $form );
		self::chart( 'Top enquiry forms', $forms );
		self::chart( 'Top lead-generating pages', Store::groups( 'landing', $f, 12, true ) );
		self::chart( 'UTM campaigns', Store::groups( 'campaign', $f, 12, true ) );
		echo '</div></article>';
	}
	private static function chart( string $title, array $rows ): void {
		echo '<section class="htecl-card"><h3>' . self::e( $title ) . '</h3>';
		if ( ! $rows ) {
			echo '<p>No matching data.</p>'; }
		$max = max( 1, ...array_merge( array( 1 ), array_column( $rows, 'total' ) ) );
		foreach ( $rows as $row ) {
			echo '<div class="htecl-bar"><span>' . self::e( $row['label'] ) . '</span><strong>' . self::e( $row['total'] ) . '</strong><meter aria-hidden="true" min="0" max="' . self::a( $max ) . '" value="' . self::a( $row['total'] ) . '"></meter></div>'; }
		echo '</section>';
	}
	public static function settings( string $page ): void {
		$s = Settings::get();
		echo '<section class="htecl-card htecl-settings">';
		Admin::start( $page, 0, 'htecl-stack' );
		if ( $page === 'settings' ) {
			self::input( 'business', 'Business name', $s['business'], 'text', true );
			self::input( 'email', 'Business email', $s['email'], 'email', true );
			$pages = array( 0 => 'No linked policy page' );
			foreach ( get_pages(
				array(
					'number'      => 200,
					'post_status' => 'publish',
				)
			) as $p ) {
				$pages[ $p->ID ] = $p->post_title; }
			if ( $s['privacy_page'] && ! isset( $pages[ $s['privacy_page'] ] ) ) {
				$pages[ $s['privacy_page'] ] = 'Current page #' . $s['privacy_page']; }
			self::select( 'privacy_page', 'Privacy policy page', $pages, $s['privacy_page'] );
			self::check( 'visit_attribution', 'Remember the first page and campaign in each browser tab for 30 minutes (session storage)', $s['visit_attribution'] );
			self::input( 'retention', 'Delete leads after this many days (0 keeps indefinitely)', $s['retention'], 'number' );
			self::check( 'purge', 'Permanently remove all LeadFlow data when the plugin is uninstalled', $s['purge'] );
			echo '<h2>Lead scoring rules</h2><p>Scores are a checklist, not a prediction. Changes apply to new and subsequently edited leads. Qualified points apply to Qualified and Converted leads.</p>';
			foreach ( array(
				'email'     => 'Business email points (common free providers excluded)',
				'phone'     => 'Valid telephone points',
				'message'   => 'Enquiry of at least 100 characters points',
				'qualified' => 'Qualified or Converted stage points',
				'threshold' => 'High-priority threshold',
			) as $key => $label ) {
				self::input( 'score_' . $key, $label, $s[ 'score_' . $key ], 'number' ); }
		} elseif ( $page === 'whatsapp' ) {
			echo '<p>Click-to-chat opens WhatsApp. It does not send messages automatically. Embed with <code>[htech_whatsapp]</code>.</p>';
			self::input( 'whatsapp', 'Business WhatsApp number', $s['whatsapp'], 'tel' );
			self::input( 'country', 'Default country calling code', $s['country'], 'text', true );
			self::textarea( 'welcome', 'Prefilled welcome message', $s['welcome'] );
			self::textarea( 'follow_text', 'Lead message · [Name], [Business Name]', $s['follow_text'] );
			self::input( 'wa_label', 'Button label', $s['wa_label'] );
			self::input( 'wa_color', 'Button colour', $s['wa_color'], 'color' );
			self::select(
				'wa_position',
				'Floating button position',
				array(
					'left'  => 'Bottom left',
					'right' => 'Bottom right',
				),
				$s['wa_position']
			);
			foreach ( array(
				'floating' => 'Enable floating button',
				'desktop'  => 'Visible on desktop',
				'mobile'   => 'Visible on mobile',
			) as $key => $label ) {
				self::check( $key, $label, $s[ $key ] ); }
		} else {
			echo '<p>Delivery depends on WordPress mail configuration. Choose only the details recipients need. A form can override the default recipient.</p>';
			self::check( 'notify', 'Email on public submissions', $s['notify'] );
			self::check( 'assign_mail', 'Notify assigned user (dashboard link only)', $s['assign_mail'] );
			self::input( 'recipient', 'Default recipient (blank uses business email)', $s['recipient'], 'email' );
			self::input( 'subject', 'Subject · [Business Name]', $s['subject'] );
			self::textarea( 'mail_body', 'Message · [Business Name], [Details], [Link]', $s['mail_body'] );
			foreach ( Settings::MAIL_FIELDS as $key => $label ) {
				echo '<label><input type="checkbox" name="mail_fields[]" value="' . self::a( $key ) . '" ' . checked( in_array( $key, $s['mail_fields'], true ), true, false ) . '> ' . self::e( $label ) . '</label>'; }
		}
		self::button( 'Save settings' );
		echo '</form></section>';
	}
}
