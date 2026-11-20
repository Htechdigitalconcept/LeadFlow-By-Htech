<?php
namespace HTECH\LeadFlow;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class Tools {
	public const CSV = array( 'name', 'email', 'phone', 'company', 'message', 'source', 'status' );
	public static function boot(): void {
		add_action( 'htecl_cleanup', array( self::class, 'cleanup' ) );
		add_action(
			'admin_init',
			static function () {
				wp_add_privacy_policy_content( 'HTECH LeadFlow', '<p>Enquiry forms store submitted contact details and messages, submitted custom fields and consent responses, and available attribution (landing page, referrer and campaign parameters). Contact the site operator for access or deletion requests. If first-page attribution is enabled, this site temporarily remembers the first page and attribution in your browser tab using session storage. The site operator configures retention and email recipients. WhatsApp links open an external service when clicked.</p>' );
			}
		);
		add_filter(
			'wp_privacy_personal_data_exporters',
			static function ( array $set ) {
				$set['htecl'] = array(
					'exporter_friendly_name' => 'HTECH LeadFlow',
					'callback'               => array( self::class, 'privacy_export' ),
				);
				return $set;
			}
		);
		add_filter(
			'wp_privacy_personal_data_erasers',
			static function ( array $set ) {
				$set['htecl'] = array(
					'eraser_friendly_name' => 'HTECH LeadFlow',
					'callback'             => array( self::class, 'privacy_erase' ),
				);
				return $set;
			}
		);
	}
	public static function screen(): void {
		echo '<section class="htecl-card"><h2>Quick help</h2><ol><li>Open <strong>Forms</strong>, choose New form, set fields and save.</li><li>Copy its shortcode into a Gutenberg Shortcode block, an Elementor Shortcode widget or the page builder’s equivalent.</li><li>Open <strong>Leads</strong> to edit details, add private notes and schedule follow-ups. Move status in the Pipeline.</li><li>Enter your international number in <strong>WhatsApp Settings</strong>. Use <code>[htech_whatsapp]</code> or switch on the floating button.</li><li>In <strong>Leads</strong>, select records, then Export selected. Protect the downloaded file; it contains contact data.</li><li>Administrators start with full access. WordPress roles can be granted specific <code>htecl_*</code> capabilities by your site administrator. View access covers all lead records; assignment is not a visibility restriction.</li></ol></section>';
		if ( current_user_can( 'htecl_export' ) ) {
			echo '<section class="htecl-card"><h2>Export all leads</h2><p>Exports standard contact and CRM fields, form ID, dates, attribution and submission values. Private notes and follow-ups are not exported in CSV.</p>';
			Admin::start( 'export' );
			echo '<input type="hidden" name="all" value="1">';
			Screens::button( 'Download CSV' );
			echo '</form></section>';
		}
		if ( ! current_user_can( 'htecl_edit' ) ) {
			return; }
		$token = Screens::query( 'import' );
		$draft = self::draft( $token );
		echo '<section class="htecl-card"><h2>Import leads from CSV</h2><p>Use a comma-separated UTF-8 file with headers, no more than 200 data rows, 30 columns and 512 KB. Match by email or phone; duplicate rows are skipped. Preview data is private and expires after 15 minutes.</p>';
		if ( ! $draft || $draft['step'] !== 'review' ) {
			echo '<form class="htecl-stack" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			Admin::fields( 'import_preview' );
			echo '<label class="htecl-label">CSV file <input type="file" name="csv" accept=".csv,text/csv" required></label>';
			Screens::button( 'Upload for mapping' );
			echo '</form>';
		}
		if ( $draft && $draft['step'] === 'map' ) {
			echo '<h3>Map CSV columns</h3><p>Map at least a name, an email or a phone column. Headers and the first row are shown below; nothing has been imported.</p>';
			Admin::start( 'import_preview', 0, 'htecl-stack' );
			echo '<input type="hidden" name="token" value="' . Screens::a( $token ) . '">';
			$cols = array( '' => 'Skip' );
			foreach ( $draft['headers'] as $i => $header ) {
				$cols[ $i ] = ( $i + 1 ) . '. ' . $header . ' · ' . Security::text( $draft['rows'][0][ $i ] ?? '', 45 ); }
			foreach ( self::CSV as $field ) {
				$i = array_search( $field, array_map( 'sanitize_key', $draft['headers'] ), true );
				Screens::select( 'map[' . $field . ']', Capture::MAP[ $field ] ?? ucfirst( $field ), $cols, $i === false ? '' : $i );
			}
			Screens::button( 'Validate and preview' );
			echo '</form>';
		} elseif ( $draft && $draft['step'] === 'review' ) {
			echo '<h3>Review import</h3><p>' . Screens::e( count( $draft['valid'] ) ) . ' valid; ' . Screens::e( count( $draft['errors'] ) ) . ' will be skipped.</p><ul>';
			foreach ( $draft['errors'] as $error ) {
				echo '<li>' . Screens::e( $error ) . '</li>'; }
			echo '</ul><div class="htecl-scroll"><table class="widefat striped"><thead><tr><th>Row</th><th>Name</th><th>Email</th><th>Phone</th><th>Stage</th></tr></thead><tbody>';
			foreach ( array_slice( $draft['valid'], 0, 20, true ) as $num => $v ) {
				echo '<tr><td>' . Screens::e( $num ) . '</td><td>' . Screens::e( $v['name'] ) . '</td><td>' . Screens::e( $v['email'] ) . '</td><td>' . Screens::e( $v['phone'] ) . '</td><td>' . Screens::e( Store::STAGES[ $v['status'] ] ) . '</td></tr>'; }
			echo '</tbody></table></div><p>Showing up to 20 valid rows. Duplicates are checked again on import.</p>';
			if ( $draft['valid'] ) {
				Admin::start( 'import_apply' );
				echo '<input type="hidden" name="token" value="' . Screens::a( $token ) . '">';
				Screens::button( 'Import valid rows' );
				echo '</form>';
			}
			Screens::link( 'tools', 'Start another import' );
		}
		echo '</section>';
	}
	public static function export( array $ids, bool $all ): void {
		if ( ! $all && ! $ids ) {
			Admin::complete( 'leads', 'Select leads to export.', array(), true ); }
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="htech-leadflow-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		$stream = fopen( 'php://output', 'wb' );
		if ( ! $stream ) {
			exit; }
		$headers = array_merge( array( 'id' ), self::CSV, array( 'form_id', 'assigned', 'created', 'updated', 'score', 'fields', 'attribution' ) );
		fputcsv( $stream, $headers, ',', '"', '' );
		$write = static function ( $row ) use ( $headers, $stream ) {
			if ( ! $row ) {
				return; }
			fputcsv( $stream, array_map( static fn( $key )=>Security::csv( $row[ $key ] ?? '' ), $headers ), ',', '"', '' );
		};
		if ( $all ) {
			global $wpdb;
			$last = 0;
			$end  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM %i', Store::table( 'leads' ) ) );
			do {
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE id>%d AND id<=%d ORDER BY id LIMIT 200', Store::table( 'leads' ), $last, $end ), ARRAY_A ) ?: array();
				foreach ( $rows as $row ) {
					$write( $row );
					$last = (int) $row['id']; }
			} while ( count( $rows ) === 200 );
		} else {
			foreach ( $ids as $id ) {
				$write( Store::get( 'leads', $id ) ); }
		}
		fclose( $stream );
		exit;
	}
	private static function draft_key( string $token ): string {
		return 'htecl_import_' . get_current_user_id() . '_' . $token; }
	private static function draft( string $token ): ?array {
		if ( ! preg_match( '/^[a-zA-Z0-9]{24}$/D', $token ) ) {
			return null; }
		$value = get_transient( self::draft_key( $token ) );
		return is_array( $value ) ? $value : null;
	}
	public static function parse_csv( string $path ): array|string {
		$size = filesize( $path );
		if ( ! $size || $size > 524288 ) {
			return 'CSV must be between 1 byte and 512 KB.'; }
		$data = file_get_contents( $path, false, null, 0, 524289 );
		if ( ! is_string( $data ) || strlen( $data ) > 524288 || str_contains( $data, "\0" ) || ! preg_match( '//u', $data ) ) {
			return 'Use a plain UTF-8 CSV file.'; }
		$data = preg_replace( '/^\xEF\xBB\xBF/', '', $data );
		$h    = fopen( 'php://temp', 'w+b' );
		if ( ! $h ) {
			return 'Could not read CSV.'; }
		try {
			fwrite( $h, $data );
			rewind( $h );
			$headers = fgetcsv( $h, 0, ',', '"', '' );
			if ( ! $headers || ! $headers[0] || count( $headers ) > 30 ) {
				return 'The first row must contain 1–30 column headers.'; }
			$rows = array();
			while ( ( $row = fgetcsv( $h, 0, ',', '"', '' ) ) !== false ) {
				if ( $row === array( null ) ) {
					continue; }
				if ( count( $rows ) >= 200 || count( $row ) > 30 ) {
					return 'Limit CSV to 200 rows and 30 columns.'; }
				if ( count( $row ) !== count( $headers ) ) {
					return 'Each row must have the same number of columns as the header.'; }
				$rows[] = array_map( static fn( $value )=>Security::body( $value, 5000 ), $row );
			}
			if ( ! $rows ) {
				return 'Include at least one data row.'; }
			return array(
				'step'    => 'map',
				'headers' => array_map( static fn( $value )=>Security::text( $value, 100 ), $headers ),
				'rows'    => $rows,
			);
		} finally {
			fclose( $h ); }
	}
	public static function preview( array $p ): void {
		$token = Security::text( $p['token'] ?? '' );
		if ( ! $token ) {
			$f    = $_FILES['csv'] ?? array();
			$path = $f['tmp_name'] ?? '';
			if ( ( $f['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK || ! is_string( $path ) || ! is_uploaded_file( $path ) || strtolower( pathinfo( Security::scalar( $f['name'] ?? '' ), PATHINFO_EXTENSION ) ) !== 'csv' ) {
				Admin::complete( 'tools', 'Upload a valid .csv file.', array(), true ); }
			$parsed = self::parse_csv( $path );
			if ( is_string( $parsed ) ) {
				Admin::complete( 'tools', $parsed, array(), true ); }
			$token = wp_generate_password( 24, false, false );
			set_transient( self::draft_key( $token ), $parsed, 900 );
			Admin::complete( 'tools', 'CSV uploaded. Review column mappings.', array( 'import' => $token ) );
		}
		$draft = self::draft( $token );
		if ( ! $draft || $draft['step'] !== 'map' ) {
			Admin::complete( 'tools', 'Import preview expired. Upload again.', array(), true ); }
		$map   = array();
		$input = is_array( $p['map'] ?? null ) ? $p['map'] : array();
		foreach ( self::CSV as $key ) {
			$col = Security::scalar( $input[ $key ] ?? '' );
			if ( ctype_digit( $col ) && isset( $draft['headers'][ (int) $col ] ) ) {
				$map[ $key ] = (int) $col; }
		}
		if ( ! array_intersect_key(
			$map,
			array(
				'name'  => 1,
				'email' => 1,
				'phone' => 1,
			)
		) ) {
			Admin::complete( 'tools', 'Map a contact column.', array( 'import' => $token ), true ); }
		$valid  = array();
		$errors = array();
		$emails = array();
		$phones = array();
		foreach ( $draft['rows'] as $i => $row ) {
			$number = $i + 2;
			$values = self::import_values( $row, $map );
			if ( is_string( $values ) ) {
				$errors[] = 'Row ' . $number . ': ' . $values;
				continue; }
			if ( isset( $emails[ $values['email'] ] ) || isset( $phones[ $values['phone'] ] ) || Store::duplicates( $values['email'], $values['phone'] ) ) {
				$errors[] = 'Row ' . $number . ': possible duplicate; skipped.';
				continue; }
			if ( $values['email'] ) {
				$emails[ $values['email'] ] = true;
			} if ( $values['phone'] ) {
				$phones[ $values['phone'] ] = true; }
			$valid[ $number ] = $values;
		}
		set_transient(
			self::draft_key( $token ),
			array(
				'step'   => 'review',
				'valid'  => $valid,
				'errors' => $errors,
			),
			900
		);
		Admin::complete( 'tools', 'Review validation results before importing.', array( 'import' => $token ) );
	}
	public static function import_values( array $row, array $map ): array|string {
		$v = array_fill_keys( self::CSV, '' );
		foreach ( $map as $key => $i ) {
			$v[ $key ] = trim( Security::scalar( $row[ $i ] ?? '' ) ); }
		// Undo the safe spreadsheet prefix for re-imported international telephone numbers.
		if ( str_starts_with( $v['phone'], "'+" ) ) {
			$v['phone'] = substr( $v['phone'], 1 ); }
		$email = Security::email( $v['email'] );
		$phone = Security::phone( $v['phone'], Settings::get()['country'] );
		if ( ( $v['email'] !== '' && $email === '' ) || ( $v['phone'] !== '' && $phone === '' ) ) {
			return 'Invalid email or phone.'; }
		$v['email']   = $email;
		$v['phone']   = $phone;
		$v['name']    = Security::text( $v['name'] );
		$v['company'] = Security::text( $v['company'] );
		$v['message'] = Security::body( $v['message'] );
		if ( ! $v['name'] && ! $email && ! $phone ) {
			return 'No usable contact information.'; }
		$v['source'] = Security::text( $v['source'] ) ?: 'CSV import';
		$v['status'] = strtolower( Security::text( $v['status'] ) ) ?: 'new';
		if ( ! isset( Store::STAGES[ $v['status'] ] ) ) {
			return 'Unknown lead stage.'; }
		return $v + array(
			'fields'      => '{}',
			'attribution' => '{}',
			'score'       => Settings::score( $v ),
		);
	}
	public static function apply( array $p ): void {
		$token = Security::text( $p['token'] ?? '' );
		$draft = self::draft( $token );
		if ( ! $draft || $draft['step'] !== 'review' || ! delete_transient( self::draft_key( $token ) ) ) {
			Admin::complete( 'tools', 'This preview expired or was already used.', array(), true ); }
		$saved   = 0;
		$skipped = 0;
		$fail    = 0;
		foreach ( $draft['valid'] as $values ) {
			if ( Store::duplicates( $values['email'], $values['phone'] ) ) {
				++$skipped;
				continue; }
			Store::create( $values ) ? $saved++ : $fail++;
		}
		Admin::complete( 'tools', sprintf( '%d imported, %d skipped and %d could not be saved.', $saved, $skipped + count( $draft['errors'] ), $fail ), array(), $fail > 0 );
	}
	public static function merge( int $keeper, int $other ): void {
		if ( ! $keeper || ! $other || $keeper === $other ) {
			Admin::complete( 'leads', 'Select two different records.', array( 'id' => $keeper ), true ); }
		global $wpdb;
		$ok = Store::transaction(
			static function () use ( $keeper, $other, $wpdb ) {
				$first  = Store::lock( min( $keeper, $other ) );
				$second = Store::lock( max( $keeper, $other ) );
				if ( ! $first || ! $second ) {
					return false; }
				[$a,$b] = $keeper < $other ? array( $first, $second ) : array( $second, $first );
				$match  = ( $a['email'] && $a['email'] === $b['email'] ) || ( $a['phone'] && $a['phone'] === $b['phone'] );
				if ( ! $match || ( $a['email'] && $b['email'] && $a['email'] !== $b['email'] ) ) {
					return false; }
				$fields  = Store::decode( $a['fields'] );
				$archive = $b;
				unset( $archive['fingerprint'] );
				$fields[ 'merged_' . $other ] = array(
					'label' => 'Archived record #' . $other,
					'value' => Store::json( $archive ),
				);
				$values                       = array(
					'fields'  => Store::json( $fields ),
					'updated' => Store::now(),
				);
				foreach ( Capture::MAP as $key => $_ ) {
					$values[ $key ] = $a[ $key ] !== '' ? $a[ $key ] : $b[ $key ]; }
				$values['score'] = Settings::score( $values + $a );
				foreach ( array( 'notes', 'activities', 'followups' ) as $child ) {
					if ( false === $wpdb->update( Store::table( $child ), array( 'lead_id' => $keeper ), array( 'lead_id' => $other ) ) ) {
						return false; }
				}
				return Store::update( 'leads', $keeper, $values ) && 1 === $wpdb->delete( Store::table( 'leads' ), array( 'id' => $other ) ) && Store::activity( $keeper, 'Duplicate #' . $other . ' merged into this lead.' );
			}
		);
		Admin::complete( 'leads', $ok ? 'Records merged; history and original submission details were preserved.' : 'Could not merge. Records must still share matching contact data and cannot have different nonempty emails.', array( 'id' => $keeper ), ! $ok );
	}
	private static function privacy_rows( string $email, int $offset = 0 ): array {
		$email = Security::email( $email );
		if ( ! $email ) {
			return array(); }
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE email=%s ORDER BY id LIMIT 50 OFFSET %d', Store::table( 'leads' ), $email, $offset ), ARRAY_A ) ?: array();
	}
	public static function privacy_export( string $email, int $page = 1 ): array {
		$rows = self::privacy_rows( $email, max( 0, $page - 1 ) * 50 );
		$data = array();
		foreach ( $rows as $r ) {
			$pairs = array();
			foreach ( Capture::MAP as $key => $name ) {
				$pairs[] = array(
					'name'  => $name,
					'value' => $r[ $key ],
				); }
			foreach ( Store::decode( $r['fields'] ) as $key => $field ) {
				if ( ! str_starts_with( $key, 'merged_' ) ) {
					$pairs[] = array(
						'name'  => $field['label'] ?? 'Field',
						'value' => $field['value'] ?? '',
					); } else {
					$archive = Store::decode( $field['value'] ?? '' );
					foreach ( Capture::MAP as $archive_key => $label ) {
						if ( ! empty( $archive[ $archive_key ] ) ) {
							$pairs[] = array(
								'name'  => 'Merged record · ' . $label,
								'value' => $archive[ $archive_key ],
							); }
					}
					}
			}
			foreach ( Store::decode( $r['attribution'] ) as $key => $val ) {
				$pairs[] = array(
					'name'  => $key,
					'value' => $val,
				); }
			$data[] = array(
				'group_id'    => 'htecl_leads',
				'group_label' => 'HTECH LeadFlow enquiries',
				'item_id'     => 'htecl-' . $r['id'],
				'data'        => $pairs,
			);
		}
		return array(
			'data' => $data,
			'done' => count( $rows ) < 50,
		);
	}
	public static function privacy_erase( string $email, int $page = 1 ): array {
		$rows    = self::privacy_rows( $email );
		$removed = false;
		$failed  = false;
		foreach ( $rows as $r ) {
			if ( Store::delete( (int) $r['id'] ) ) {
				$removed = true;
			} else {
				$failed = true; }
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => $failed,
			'messages'       => $failed ? array( 'One or more leads could not be removed. Review them in LeadFlow.' ) : array(),
			'done'           => $failed || count( $rows ) < 50,
		);
	}
	public static function cleanup(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires<%d LIMIT 10000', Store::table( 'guard' ), time() ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET fingerprint=NULL WHERE fingerprint IS NOT NULL AND created<%s LIMIT 2000', Store::table( 'leads' ), gmdate( 'Y-m-d H:i:s', time() - 600 ) ) );
		$days = Settings::get()['retention'];
		if ( ! $days ) {
			return; }
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$rows   = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE created<%s ORDER BY created,id LIMIT 500', Store::table( 'leads' ), $cutoff ) );
		foreach ( $rows as $id ) {
			Store::delete( (int) $id ); }
	}
}
